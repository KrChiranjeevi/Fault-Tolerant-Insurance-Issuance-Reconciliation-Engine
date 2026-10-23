<?php

namespace App\Services\Policy;

use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use App\Services\Insurer\MockInsurerService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * IssuanceService
 *
 * Orchestrates submission to the mock insurer, attempt persistence,
 * callback processing, deduplication, and reconciliation.
 */
class IssuanceService
{
    public function __construct(
        protected PolicyStateService  $stateService,
        protected MockInsurerService  $insurerService,
    ) {}

    // -----------------------------------------------------------------------
    // Submit a policy request to the mock insurer
    // -----------------------------------------------------------------------

    /**
     * Submit a VALIDATED (or FAILED-retryable) policy to the mock insurer.
     *
     * Steps:
     *  1. Lock the row to prevent concurrent double-submissions.
     *  2. Assert the state transition VALIDATED → SUBMITTED is legal.
     *  3. Record an IssuanceAttempt.
     *  4. Call the mock insurer synchronously.
     *  5. Persist the response, update the attempt, advance the state.
     *  6. If the insurer delivered a callback synchronously, process it.
     *
     * @throws RuntimeException on illegal state
     */
    public function submit(PolicyRequest $policy, string $mockScenario = MockInsurerService::SCENARIO_SUCCESS): PolicyRequest
    {
        return DB::transaction(function () use ($policy, $mockScenario) {
            // Lock the row — prevents concurrent submissions for the same policy
            $policy = PolicyRequest::lockForUpdate()->findOrFail($policy->id);

            // Allow FAILED → SUBMITTED retry path (state machine validates)
            $this->stateService->transition(
                $policy,
                PolicyStateService::SUBMITTED,
                'SUBMIT_TO_INSURER',
                'SYSTEM',
                null,
                ['mock_scenario' => $mockScenario]
            );

            $attemptNumber = $policy->issuanceAttempts()->count() + 1;

            $requestPayload = [
                'policy_number'  => $policy->policy_number,
                'premium_amount' => $policy->premium_amount,
                'currency'       => $policy->currency,
                'plan'           => $policy->plan,
                'destination'    => $policy->destination,
                'insured_name'   => $policy->insured_name,
                'trip_start'     => $policy->trip_start?->toDateString(),
                'trip_end'       => $policy->trip_end?->toDateString(),
                'mock_scenario'  => $mockScenario,
                'attempt_number' => $attemptNumber,
            ];

            // Create attempt record
            $attempt = IssuanceAttempt::create([
                'policy_request_id' => $policy->id,
                'attempt_number'    => $attemptNumber,
                'provider_code'     => 'MOCK_INSURER',
                'status'            => 'PENDING',
                'request_payload'   => $requestPayload,
                'attempted_at'      => now(),
            ]);

            // Call mock insurer (no real HTTP)
            $response = $this->insurerService->submit($requestPayload);

            // Persist response on attempt
            $attempt->update([
                'response_payload' => $response,
                'status'           => $response['status'],
                'error_code'       => $response['error_code'] ?? null,
                'error_message'    => $response['error_message'] ?? null,
            ]);

            // Refresh policy from DB after state transition
            $policy->refresh();

            return match ($response['status']) {
                'CALLBACK_DELIVERED'  => $this->handleSyncCallback($policy, $response, $attempt),
                'TEMPORARY_FAILURE'   => $this->handleTemporaryFailure($policy, $response),
                'ACCEPTED_NO_CALLBACK' => $this->handleTimeoutScenario($policy, $response),
                'ACCEPTED_ASYNC'      => $this->handleAsyncAccepted($policy, $response),
                default               => throw new RuntimeException("Unknown insurer response status: {$response['status']}"),
            };
        });
    }

    // -----------------------------------------------------------------------
    // Callback processing — called by the callback controller
    // -----------------------------------------------------------------------

    /**
     * Process an inbound callback from the (mock) insurer.
     *
     * Idempotent: if the provider_event_id was already processed, returns
     * the existing event without mutating the policy.
     *
     * @throws RuntimeException on unknown policy or illegal state
     */
    public function processCallback(array $callbackPayload): array
    {
        $eventId     = $callbackPayload['event_id'];
        $eventType   = $callbackPayload['event_type'] ?? 'POLICY_ISSUED';
        $policyNumber = $callbackPayload['policy_number'];
        $status      = $callbackPayload['status'] ?? 'ISSUED';

        return DB::transaction(function () use ($eventId, $eventType, $policyNumber, $status, $callbackPayload) {
            // 1. Deduplicate by provider_event_id with a DB lock
            $existing = ProviderEvent::where('provider_event_id', $eventId)->lockForUpdate()->first();

            if ($existing) {
                return [
                    'deduplicated' => true,
                    'event'        => $existing,
                    'policy'       => PolicyRequest::find($existing->policy_request_id),
                ];
            }

            // 2. Locate the policy
            $policy = PolicyRequest::where('policy_number', $policyNumber)
                ->lockForUpdate()
                ->firstOrFail();

            // 3. Record the event regardless of whether we can act on it
            $event = ProviderEvent::create([
                'provider_event_id' => $eventId,
                'policy_request_id' => $policy->id,
                'provider_code'     => 'MOCK_INSURER',
                'event_type'        => $eventType,
                'status'            => 'PROCESSED',
                'payload'           => $callbackPayload,
                'processed_at'      => now(),
            ]);

            // 4. Only advance state if ISSUED is not already set (idempotent guard)
            if ($policy->status === PolicyStateService::ISSUED) {
                return ['deduplicated' => false, 'skipped' => true, 'event' => $event, 'policy' => $policy];
            }

            // 5. Transition based on callback status
            if ($status === 'ISSUED') {
                $this->stateService->transition(
                    $policy,
                    PolicyStateService::ISSUED,
                    'CALLBACK_RECEIVED',
                    'INSURER',
                    'MOCK_INSURER',
                    ['event_id' => $eventId, 'provider_policy_id' => $callbackPayload['provider_policy_id'] ?? null]
                );

                // Persist the provider's policy ID on the policy record
                $policy->update([
                    'metadata' => array_merge($policy->metadata ?? [], [
                        'provider_policy_id' => $callbackPayload['provider_policy_id'] ?? null,
                        'issued_at'          => $callbackPayload['issued_at'] ?? now()->toIso8601String(),
                    ]),
                ]);
            } else {
                $this->stateService->transition(
                    $policy,
                    PolicyStateService::FAILED,
                    'CALLBACK_FAILED',
                    'INSURER',
                    'MOCK_INSURER',
                    ['event_id' => $eventId]
                );
            }

            $policy->refresh();

            return ['deduplicated' => false, 'skipped' => false, 'event' => $event, 'policy' => $policy];
        });
    }

    // -----------------------------------------------------------------------
    // Reconciliation — for TIMEOUT_AFTER_ISSUE scenario
    // -----------------------------------------------------------------------

    /**
     * Poll the mock insurer for a policy stuck in NEEDS_RECONCILIATION.
     *
     * @throws RuntimeException if policy is not in NEEDS_RECONCILIATION
     */
    public function reconcile(PolicyRequest $policy): PolicyRequest
    {
        if ($policy->status !== PolicyStateService::NEEDS_RECONCILIATION) {
            throw new RuntimeException(
                "Cannot reconcile policy in status '{$policy->status}'. Expected NEEDS_RECONCILIATION."
            );
        }

        $providerRef = $policy->metadata['provider_reference'] ?? '';

        $lookupResult = $this->insurerService->lookupStatus($policy->policy_number, $providerRef);

        return DB::transaction(function () use ($policy, $lookupResult, $providerRef) {
            $policy = PolicyRequest::lockForUpdate()->findOrFail($policy->id);

            if ($lookupResult['issued']) {
                // Record reconciliation event
                ProviderEvent::create([
                    'provider_event_id' => $lookupResult['event_id'],
                    'policy_request_id' => $policy->id,
                    'provider_code'     => 'MOCK_INSURER',
                    'event_type'        => 'RECONCILIATION_CONFIRMED',
                    'status'            => 'PROCESSED',
                    'payload'           => $lookupResult,
                    'processed_at'      => now(),
                ]);

                $this->stateService->transition(
                    $policy,
                    PolicyStateService::ISSUED,
                    'RECONCILIATION_CONFIRMED',
                    'SYSTEM',
                    null,
                    $lookupResult
                );

                $policy->update([
                    'metadata' => array_merge($policy->metadata ?? [], [
                        'provider_policy_id' => $lookupResult['provider_policy_id'],
                        'issued_at'          => $lookupResult['issued_at'],
                        'reconciled'         => true,
                    ]),
                ]);
            } else {
                $this->stateService->transition(
                    $policy,
                    PolicyStateService::FAILED,
                    'RECONCILIATION_FAILED',
                    'SYSTEM',
                    null,
                    $lookupResult
                );
            }

            return $policy->refresh();
        });
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    private function handleSyncCallback(PolicyRequest $policy, array $response, IssuanceAttempt $attempt): PolicyRequest
    {
        $callbackResult = $this->processCallback($response['callback_payload']);
        return $callbackResult['policy'];
    }

    private function handleTemporaryFailure(PolicyRequest $policy, array $response): PolicyRequest
    {
        $this->stateService->transition(
            $policy,
            PolicyStateService::FAILED,
            'INSURER_TEMPORARY_FAILURE',
            'SYSTEM',
            null,
            ['error_code' => $response['error_code'], 'error_message' => $response['error_message']]
        );

        return $policy->refresh();
    }

    private function handleTimeoutScenario(PolicyRequest $policy, array $response): PolicyRequest
    {
        $this->stateService->transition(
            $policy,
            PolicyStateService::PENDING_CONFIRMATION,
            'ACCEPTED_PENDING_CALLBACK',
            'SYSTEM',
        );

        // Immediately mark as needing reconciliation (no callback expected)
        $this->stateService->transition(
            $policy,
            PolicyStateService::NEEDS_RECONCILIATION,
            'CALLBACK_TIMEOUT',
            'SYSTEM',
            null,
            ['provider_reference' => $response['provider_reference']]
        );

        $policy->update([
            'metadata' => array_merge($policy->metadata ?? [], [
                'provider_reference' => $response['provider_reference'],
            ]),
        ]);

        return $policy->refresh();
    }

    private function handleAsyncAccepted(PolicyRequest $policy, array $response): PolicyRequest
    {
        $this->stateService->transition(
            $policy,
            PolicyStateService::PENDING_CONFIRMATION,
            'ACCEPTED_ASYNC',
            'SYSTEM',
            null,
            ['provider_reference' => $response['provider_reference']]
        );

        $policy->update([
            'metadata' => array_merge($policy->metadata ?? [], [
                'provider_reference' => $response['provider_reference'],
            ]),
        ]);

        return $policy->refresh();
    }
}
