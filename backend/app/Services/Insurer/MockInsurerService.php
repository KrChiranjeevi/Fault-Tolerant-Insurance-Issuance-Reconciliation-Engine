<?php

namespace App\Services\Insurer;

use Illuminate\Support\Str;

/**
 * MockInsurerService
 *
 * A fully deterministic, in-process mock insurer.  Scenario selection is driven
 * by the `mock_scenario` field in the submission payload so tests can request
 * exact behaviour without sleeping or waiting for real timers.
 *
 * Scenarios
 * ---------
 * SUCCESS              – policy issued synchronously in the callback.
 * TEMPORARY_FAILURE    – first attempt returns a retryable error; subsequent
 *                        attempts (attempt_number > 1) succeed.
 * TIMEOUT_AFTER_ISSUE  – the insurer issues the policy but the original HTTP
 *                        callback is never delivered. A later reconciliation
 *                        poll returns the issued reference.
 * DELAYED_CALLBACK     – HTTP response is ACCEPTED (async); callback arrives
 *                        later (caller must drive the callback separately).
 * DUPLICATE_CALLBACK   – SUCCESS, but the caller is expected to POST the
 *                        callback twice with the same provider_event_id.
 *
 * All returned event IDs are deterministic given the same inputs so tests
 * can reproduce them without side effects.
 */
class MockInsurerService
{
    public const SCENARIO_SUCCESS             = 'SUCCESS';
    public const SCENARIO_TEMPORARY_FAILURE   = 'TEMPORARY_FAILURE';
    public const SCENARIO_TIMEOUT_AFTER_ISSUE = 'TIMEOUT_AFTER_ISSUE';
    public const SCENARIO_DELAYED_CALLBACK    = 'DELAYED_CALLBACK';
    public const SCENARIO_DUPLICATE_CALLBACK  = 'DUPLICATE_CALLBACK';

    /**
     * Submit a policy to the mock insurer.
     *
     * @param array $payload  {policy_number, premium_amount, currency, scenario?, attempt_number?}
     * @return array          {status, provider_reference?, callback_payload?, error_code?, error_message?}
     */
    public function submit(array $payload): array
    {
        $scenario      = strtoupper($payload['mock_scenario'] ?? self::SCENARIO_SUCCESS);
        $attemptNumber = (int) ($payload['attempt_number'] ?? 1);
        $policyNumber  = $payload['policy_number'];

        return match ($scenario) {
            self::SCENARIO_SUCCESS           => $this->handleSuccess($policyNumber),
            self::SCENARIO_TEMPORARY_FAILURE => $this->handleTemporaryFailure($policyNumber, $attemptNumber),
            self::SCENARIO_TIMEOUT_AFTER_ISSUE => $this->handleTimeoutAfterIssue($policyNumber),
            self::SCENARIO_DELAYED_CALLBACK  => $this->handleDelayedCallback($policyNumber),
            self::SCENARIO_DUPLICATE_CALLBACK => $this->handleDuplicateCallback($policyNumber),
            default                          => $this->handleSuccess($policyNumber),
        };
    }

    /**
     * Status lookup / reconciliation poll.
     * Called when a policy is stuck in NEEDS_RECONCILIATION.
     *
     * @param string $policyNumber
     * @param string $providerReference  Reference returned during the original submit
     * @return array {found, issued, provider_policy_id?, issued_at?}
     */
    public function lookupStatus(string $policyNumber, string $providerReference): array
    {
        // For TIMEOUT_AFTER_ISSUE the insurer DID issue the policy.
        // We recognise this by the deterministic reference prefix.
        if (str_starts_with($providerReference, 'TIMEOUT-')) {
            return [
                'found'              => true,
                'issued'             => true,
                'provider_policy_id' => 'RECOVERED-' . $policyNumber,
                'issued_at'          => now()->toIso8601String(),
                'event_id'           => 'evt_reconcile_' . md5($policyNumber),
            ];
        }

        return ['found' => false, 'issued' => false];
    }

    // -----------------------------------------------------------------------
    // Private scenario handlers
    // -----------------------------------------------------------------------

    private function handleSuccess(string $policyNumber): array
    {
        $eventId = 'evt_' . md5('SUCCESS' . $policyNumber);

        return [
            'status'           => 'CALLBACK_DELIVERED',
            'provider_reference' => 'REF-' . strtoupper(substr(md5($policyNumber), 0, 8)),
            'callback_payload' => $this->buildSuccessCallback($policyNumber, $eventId),
        ];
    }

    private function handleTemporaryFailure(string $policyNumber, int $attempt): array
    {
        if ($attempt <= 1) {
            return [
                'status'        => 'TEMPORARY_FAILURE',
                'error_code'    => 'INSURER_UNAVAILABLE',
                'error_message' => 'Mock insurer temporarily unavailable. Retry.',
            ];
        }

        // Subsequent attempts succeed
        $eventId = 'evt_' . md5('TEMP_RETRY' . $policyNumber . $attempt);

        return [
            'status'             => 'CALLBACK_DELIVERED',
            'provider_reference' => 'REF-RETRY-' . strtoupper(substr(md5($policyNumber), 0, 8)),
            'callback_payload'   => $this->buildSuccessCallback($policyNumber, $eventId),
        ];
    }

    private function handleTimeoutAfterIssue(string $policyNumber): array
    {
        // Insurer accepted and internally issued the policy, but the callback
        // was never delivered. The caller must reconcile via lookupStatus().
        return [
            'status'             => 'ACCEPTED_NO_CALLBACK',
            'provider_reference' => 'TIMEOUT-' . strtoupper(substr(md5($policyNumber), 0, 8)),
        ];
    }

    private function handleDelayedCallback(string $policyNumber): array
    {
        return [
            'status'             => 'ACCEPTED_ASYNC',
            'provider_reference' => 'ASYNC-' . strtoupper(substr(md5($policyNumber), 0, 8)),
        ];
    }

    private function handleDuplicateCallback(string $policyNumber): array
    {
        $eventId = 'evt_' . md5('DUPLICATE' . $policyNumber);

        return [
            'status'             => 'CALLBACK_DELIVERED',
            'provider_reference' => 'REF-DUP-' . strtoupper(substr(md5($policyNumber), 0, 8)),
            'callback_payload'   => $this->buildSuccessCallback($policyNumber, $eventId),
            // The same callback will be posted twice by the test / caller
            'duplicate_event_id' => $eventId,
        ];
    }

    private function buildSuccessCallback(string $policyNumber, string $eventId): array
    {
        return [
            'event_id'          => $eventId,
            'event_type'        => 'POLICY_ISSUED',
            'policy_number'     => $policyNumber,
            'provider_policy_id' => 'MPOL-' . strtoupper(substr(md5($policyNumber), 0, 10)),
            'status'            => 'ISSUED',
            'issued_at'         => now()->toIso8601String(),
        ];
    }
}
