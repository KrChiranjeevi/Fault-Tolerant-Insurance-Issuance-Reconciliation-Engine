<?php

namespace App\Services\Policy;

use App\Models\PolicyRequest;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PolicyService
 *
 * Handles initial policy request creation.
 * Submission / callback processing is delegated to IssuanceService.
 */
class PolicyService
{
    public function __construct(
        protected PricingService     $pricingService,
        protected PolicyStateService $stateService,
    ) {}

    /**
     * Create a new policy request with an immutable pricing snapshot.
     * Uses a DB transaction so price calculation and record creation are atomic.
     */
    public function createPolicyRequest(array $data): PolicyRequest
    {
        return DB::transaction(function () use ($data) {
            // 1. Calculate price → immutable snapshot (BCMath precision)
            $pricing = $this->pricingService->calculatePremium([
                'plan'          => $data['plan'],
                'destination'   => $data['destination'],
                'coverage_type' => $data['coverage_type'],
                'date_of_birth' => $data['date_of_birth'],
                'trip_start'    => $data['trip_start'],
                'trip_end'      => $data['trip_end'],
                'currency'      => $data['currency'] ?? 'USD',
            ]);

            // 2. Generate a unique policy number
            $policyNumber = $this->generatePolicyNumber();

            // 3. Persist the policy request in RECEIVED state
            $policy = PolicyRequest::create([
                'policy_number'     => $policyNumber,
                'agent_id'          => (string) $data['agent_id'],
                'client_request_id' => (string) $data['client_request_id'],
                'status'            => PolicyStateService::RECEIVED,
                'insured_name'      => $data['insured_name'],
                'insured_email'     => $data['insured_email'],
                'date_of_birth'     => $data['date_of_birth'],
                'traveler_age'      => $pricing['traveler_age'],
                'destination'       => $pricing['destination'],
                'trip_start'        => $data['trip_start'],
                'trip_end'          => $data['trip_end'],
                'trip_days'         => $pricing['trip_days'],
                'plan'              => $pricing['plan'],
                'coverage_type'     => $pricing['coverage_type'],
                'currency'          => $pricing['currency'],
                'premium_amount'    => $pricing['amount'],
                'premium_snapshot'  => $pricing['snapshot'],
                'metadata'          => $data['metadata'] ?? null,
            ]);

            // 4. Audit: record the initial state transition (null → RECEIVED)
            $policy->stateHistories()->create([
                'from_status'   => null,
                'to_status'     => PolicyStateService::RECEIVED,
                'trigger_event' => 'POLICY_REQUEST_CREATED',
                'actor_type'    => 'AGENT',
                'actor_id'      => (string) $data['agent_id'],
                'metadata'      => [
                    'client_request_id' => $data['client_request_id'],
                    'premium_amount'    => $pricing['amount'],
                ],
            ]);

            return $policy;
        });
    }

    /**
     * Alias kept for any code that still calls createQuote() from M2 tests.
     *
     * @deprecated Use createPolicyRequest() instead.
     */
    public function createQuote(array $data): PolicyRequest
    {
        return $this->createPolicyRequest($data);
    }

    /**
     * Generate a unique policy number in the format POL-YYYYMMDD-XXXXXX.
     */
    protected function generatePolicyNumber(): string
    {
        do {
            $number = sprintf('POL-%s-%s', date('Ymd'), strtoupper(Str::random(6)));
        } while (PolicyRequest::where('policy_number', $number)->exists());

        return $number;
    }
}
