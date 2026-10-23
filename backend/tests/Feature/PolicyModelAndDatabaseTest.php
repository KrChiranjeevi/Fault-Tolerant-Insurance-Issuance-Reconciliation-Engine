<?php

namespace Tests\Feature;

use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use App\Services\Policy\PolicyService;
use App\Services\Policy\PolicyStateService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyModelAndDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected PolicyService $policyService;

    protected function setUp(): void
    {
        parent::setUp();
        // Use Laravel's container so all constructor dependencies are injected
        $this->policyService = app(PolicyService::class);
    }

    /**
     * Test creating a policy request transactionally saves record and initial state history.
     */
    public function test_can_create_policy_request_and_state_history_transactionally(): void
    {
        $policy = $this->policyService->createQuote([
            'agent_id'          => 'agent_007',
            'client_request_id' => 'req_abc123',
            'insured_name'      => 'Alice Johnson',
            'insured_email'     => 'alice@example.com',
            'date_of_birth'     => '1995-04-10',
            'destination'       => 'EUROPE',
            'trip_start'        => '2026-10-01',
            'trip_end'          => '2026-10-05',
            'plan'              => 'STANDARD',
            'coverage_type'     => 'COMPREHENSIVE',
        ]);

        $this->assertDatabaseHas('policy_requests', [
            'id'                => $policy->id,
            'policy_number'     => $policy->policy_number,
            'agent_id'          => 'agent_007',
            'client_request_id' => 'req_abc123',
            'status'            => PolicyStateService::RECEIVED,
            'trip_days'         => 5,
        ]);

        $this->assertDatabaseHas('policy_state_history', [
            'policy_request_id' => $policy->id,
            'to_status'         => PolicyStateService::RECEIVED,
            'trigger_event'     => 'POLICY_REQUEST_CREATED',
            'actor_type'        => 'AGENT',
            'actor_id'          => 'agent_007',
        ]);

        $this->assertNotNull($policy->premium_snapshot);
        $this->assertSame('168.75', (string) $policy->premium_amount);
    }

    /**
     * Test idempotency constraint: (agent_id, client_request_id) must be unique.
     */
    public function test_agent_id_and_client_request_id_unique_constraint(): void
    {
        $payload = [
            'agent_id'          => 'agent_42',
            'client_request_id' => 'idem_key_999',
            'insured_name'      => 'Bob Smith',
            'insured_email'     => 'bob@example.com',
            'date_of_birth'     => '1985-02-15',
            'destination'       => 'DOMESTIC',
            'trip_start'        => '2026-11-01',
            'trip_end'          => '2026-11-03',
            'plan'              => 'BASIC',
            'coverage_type'     => 'MEDICAL_ONLY',
        ];

        // First attempt succeeds
        $this->policyService->createQuote($payload);

        // Second attempt with same (agent_id, client_request_id) must fail
        $this->expectException(QueryException::class);
        $this->policyService->createQuote($payload);
    }

    /**
     * Test unique policy number constraint.
     */
    public function test_policy_number_unique_constraint(): void
    {
        $policy = $this->policyService->createQuote([
            'agent_id'          => 'agent_01',
            'client_request_id' => 'req_1',
            'insured_name'      => 'Charlie',
            'insured_email'     => 'charlie@example.com',
            'date_of_birth'     => '1990-01-01',
            'destination'       => 'ASIA',
            'trip_start'        => '2026-12-01',
            'trip_end'          => '2026-12-05',
            'plan'              => 'BASIC',
            'coverage_type'     => 'MEDICAL_ONLY',
        ]);

        $this->expectException(QueryException::class);

        // Attempt to insert duplicate policy number directly
        PolicyRequest::create([
            'policy_number'    => $policy->policy_number,
            'agent_id'         => 'agent_02',
            'client_request_id' => 'req_2',
            'status'           => PolicyStateService::RECEIVED,
            'insured_name'     => 'Duplicate',
            'insured_email'    => 'dup@example.com',
            'date_of_birth'    => '1990-01-01',
            'traveler_age'     => 36,
            'destination'      => 'ASIA',
            'trip_start'       => '2026-12-01',
            'trip_end'         => '2026-12-05',
            'trip_days'        => 5,
            'plan'             => 'BASIC',
            'coverage_type'    => 'MEDICAL_ONLY',
            'currency'         => 'USD',
            'premium_amount'   => '60.00',
            'premium_snapshot' => ['test' => true],
        ]);
    }

    /**
     * Test unique provider event ID constraint.
     */
    public function test_provider_event_id_unique_constraint(): void
    {
        ProviderEvent::create([
            'provider_event_id' => 'evt_external_unique_123',
            'provider_code'     => 'TEST_PROVIDER',
            'event_type'        => 'policy.issued',
            'status'            => 'RECEIVED',
            'payload'           => ['sample' => 'data'],
        ]);

        $this->expectException(QueryException::class);

        ProviderEvent::create([
            'provider_event_id' => 'evt_external_unique_123',
            'provider_code'     => 'TEST_PROVIDER',
            'event_type'        => 'policy.issued',
            'status'            => 'RECEIVED',
            'payload'           => ['sample' => 'data_duplicate'],
        ]);
    }

    /**
     * Test that changing future config rates does not mutate the existing saved snapshot.
     */
    public function test_premium_snapshot_immutability(): void
    {
        $policy = $this->policyService->createQuote([
            'agent_id'          => 'agent_01',
            'client_request_id' => 'req_snap_1',
            'insured_name'      => 'Snapshot Test',
            'insured_email'     => 'snap@example.com',
            'date_of_birth'     => '1990-01-01',
            'destination'       => 'EUROPE',
            'trip_start'        => '2026-11-01',
            'trip_end'          => '2026-11-05',
            'plan'              => 'STANDARD',
            'coverage_type'     => 'COMPREHENSIVE',
        ]);

        $savedAmount = (string) $policy->premium_amount;

        // Mutate config at runtime (simulates a future rate change)
        config(['pricing.plans.STANDARD.base_daily_rate' => '999.00']);

        // Reload fresh from DB — snapshot must be unchanged
        $freshPolicy = PolicyRequest::find($policy->id);

        $this->assertSame($savedAmount, (string) $freshPolicy->premium_amount);
        $this->assertSame('15.00', $freshPolicy->premium_snapshot['rates_and_multipliers']['base_daily_rate']);
        $this->assertSame($savedAmount, $freshPolicy->premium_snapshot['amount']);
    }

    /**
     * Test IssuanceAttempt relationship and state transition logging.
     */
    public function test_issuance_attempt_and_state_transition(): void
    {
        $policy = $this->policyService->createQuote([
            'agent_id'          => 'agent_01',
            'client_request_id' => 'req_issue_1',
            'insured_name'      => 'David Lee',
            'insured_email'     => 'david@example.com',
            'date_of_birth'     => '1992-05-15',
            'destination'       => 'DOMESTIC',
            'trip_start'        => '2026-12-01',
            'trip_end'          => '2026-12-03',
            'plan'              => 'BASIC',
            'coverage_type'     => 'MEDICAL_ONLY',
        ]);

        // Add issuance attempt
        $attempt = $policy->issuanceAttempts()->create([
            'attempt_number'   => 1,
            'provider_code'    => 'PRIMARY_PROVIDER',
            'status'           => 'SUCCESS',
            'request_payload'  => ['policy_number' => $policy->policy_number],
            'response_payload' => ['confirmation_code' => 'CONF_123'],
            'attempted_at'     => now(),
        ]);

        $this->assertInstanceOf(IssuanceAttempt::class, $attempt);
        $this->assertSame(1, $policy->issuanceAttempts()->count());

        // Use the state service for a valid transition
        $stateService = app(PolicyStateService::class);
        $stateService->transition($policy, PolicyStateService::VALIDATED, 'PRE_SUBMIT', 'SYSTEM');
        $stateService->transition($policy, PolicyStateService::SUBMITTED, 'SUBMIT_ATTEMPT', 'SYSTEM');
        $stateService->transition($policy, PolicyStateService::PENDING_CONFIRMATION, 'PROVIDER_ACCEPTED', 'SYSTEM');
        $stateService->transition($policy, PolicyStateService::ISSUED, 'PROVIDER_SUCCESS', 'SYSTEM');

        $this->assertSame(PolicyStateService::ISSUED, $policy->status);
        // Initial (RECEIVED) + 4 transitions = 5 entries
        $this->assertSame(5, $policy->stateHistories()->count());
    }
}
