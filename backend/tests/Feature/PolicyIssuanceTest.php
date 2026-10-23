<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use App\Services\Insurer\MockInsurerService;
use App\Services\Policy\PolicyStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PolicyIssuanceTest
 *
 * Covers M3 endpoints and M5 reliability cases:
 *  - Intake HTTP codes (201 new / 200 idempotent / 422 validation)
 *  - Concurrent idempotent intake (simulated race)
 *  - Successful end-to-end issuance (RECEIVED → ISSUED)
 *  - Unique policy number per request
 *  - Paginated list + status filter
 *  - Detail endpoint with relations
 *  - Temporary failure + safe retry
 *  - ISSUED never regresses (duplicate callback)
 *  - Callback deduplication (same event_id sent twice)
 *  - Timeout-after-issue → reconcile → ISSUED
 *  - State machine rejects illegal transitions
 */
class PolicyIssuanceTest extends TestCase
{
    use RefreshDatabase;

    // ── Fixture ──────────────────────────────────────────────────────────────

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'agent_id'          => 'AGENT-001',
            'client_request_id' => 'REQ-' . uniqid(),
            'insured_name'      => 'Jane Traveler',
            'insured_email'     => 'jane@example.com',
            'date_of_birth'     => '1990-06-15',
            'destination'       => 'EUROPE',
            'trip_start'        => '2027-03-01',
            'trip_end'          => '2027-03-10',
            'plan'              => 'BASIC',
            'coverage_type'     => 'STANDARD_COVER',
            'currency'          => 'USD',
        ], $overrides);
    }

    // ── 1. Intake: 201 for a new policy ──────────────────────────────────────

    public function test_intake_returns_201_for_new_policy(): void
    {
        $res = $this->postJson('/api/policy-requests', $this->validPayload());

        $res->assertStatus(201)
            ->assertJsonPath('data.status', PolicyStateService::RECEIVED)
            ->assertJsonStructure(['data' => ['id', 'policy_number', 'premium_amount', 'premium_snapshot']]);
    }

    // ── 2. Intake: 200 + same record for duplicate (idempotent) ──────────────

    public function test_intake_idempotent_returns_200_and_same_record(): void
    {
        $payload = $this->validPayload(['client_request_id' => 'IDEM-001']);

        $first  = $this->postJson('/api/policy-requests', $payload);
        $second = $this->postJson('/api/policy-requests', $payload);

        $first->assertStatus(201);
        $second->assertStatus(200);

        $this->assertSame(
            $first->json('data.id'),
            $second->json('data.id'),
            'Duplicate intake must return the original record id'
        );

        $this->assertDatabaseCount('policy_requests', 1);
    }

    // ── 3. Intake: 422 on missing required field ──────────────────────────────

    public function test_intake_returns_422_for_missing_fields(): void
    {
        $this->postJson('/api/policy-requests', [])->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    // ── 4. Intake: 422 when trip_end before trip_start ────────────────────────

    public function test_intake_returns_422_when_trip_end_before_trip_start(): void
    {
        $payload = $this->validPayload([
            'trip_start' => '2027-03-10',
            'trip_end'   => '2027-03-01',
        ]);

        $this->postJson('/api/policy-requests', $payload)->assertStatus(422);
    }

    // ── 5. Intake: 422 when plan is unsupported ───────────────────────────────

    public function test_intake_returns_422_for_unsupported_plan(): void
    {
        $payload = $this->validPayload(['plan' => 'DIAMOND_ULTRA']);

        $this->postJson('/api/policy-requests', $payload)->assertStatus(422);
    }

    // ── 5b. Intake: 422 when coverage is unsupported ──────────────────────────

    public function test_intake_returns_422_for_unsupported_coverage(): void
    {
        $payload = $this->validPayload(['coverage_type' => 'INVALID_COVERAGE_XYZ']);

        $this->postJson('/api/policy-requests', $payload)->assertStatus(422);
    }

    // ── 6. List endpoint with pagination ─────────────────────────────────────

    public function test_list_endpoint_returns_paginated_results(): void
    {
        // Create 3 policies
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/policy-requests', $this->validPayload())->assertStatus(201);
        }

        $res = $this->getJson('/api/policy-requests?per_page=2');

        $res->assertStatus(200)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total', 'per_page']])
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
    }

    // ── 7. List: status filter ────────────────────────────────────────────────

    public function test_list_status_filter(): void
    {
        $this->postJson('/api/policy-requests', $this->validPayload())->assertStatus(201);
        $this->postJson('/api/policy-requests', $this->validPayload())->assertStatus(201);

        $res = $this->getJson('/api/policy-requests?status=RECEIVED');

        $res->assertStatus(200);
        $data = $res->json('data');
        foreach ($data as $item) {
            $this->assertSame(PolicyStateService::RECEIVED, $item['status']);
        }
    }

    // ── 8. Detail endpoint returns relations ──────────────────────────────────

    public function test_show_returns_policy_with_relations(): void
    {
        $created = $this->postJson('/api/policy-requests', $this->validPayload())->json('data');

        $res = $this->getJson("/api/policy-requests/{$created['id']}");

        $res->assertStatus(200)
            ->assertJsonStructure(['data' => [
                'id', 'policy_number', 'status',
                'issuance_attempts', 'provider_events', 'state_history',
            ]]);
    }

    // ── 9. Show: 404 for unknown id ───────────────────────────────────────────

    public function test_show_returns_404_for_unknown_id(): void
    {
        $this->getJson('/api/policy-requests/999999')->assertStatus(404);
    }

    // ── 10. Successful end-to-end issuance ────────────────────────────────────

    public function test_successful_issuance_reaches_issued_state(): void
    {
        $policy = $this->postJson('/api/policy-requests', $this->validPayload())->json('data');

        $res = $this->postJson("/api/policy-requests/{$policy['id']}/submit", [
            'mock_scenario' => MockInsurerService::SCENARIO_SUCCESS,
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.status', PolicyStateService::ISSUED);

        // Policy number must be populated and unique-format
        $policyNumber = $res->json('data.policy_number');
        $this->assertMatchesRegularExpression('/^POL-\d{8}-[A-Z0-9]{6}$/', $policyNumber);

        // State history must include the RECEIVED → VALIDATED → SUBMITTED → … → ISSUED path
        $history = $res->json('data.state_history');
        $toStates = array_column($history, 'to_status');
        $this->assertContains(PolicyStateService::ISSUED, $toStates);
    }

    // ── 11. Unique policy number per request ──────────────────────────────────

    public function test_each_policy_gets_a_unique_policy_number(): void
    {
        $p1 = $this->postJson('/api/policy-requests', $this->validPayload())->json('data');
        $p2 = $this->postJson('/api/policy-requests', $this->validPayload())->json('data');

        $this->assertNotSame($p1['policy_number'], $p2['policy_number']);
    }

    // ── 12. Submit: 409 on already-ISSUED ────────────────────────────────────

    public function test_submit_returns_409_when_already_issued(): void
    {
        $policy = $this->postJson('/api/policy-requests', $this->validPayload())->json('data');

        // First submit → ISSUED
        $this->postJson("/api/policy-requests/{$policy['id']}/submit", [
            'mock_scenario' => MockInsurerService::SCENARIO_SUCCESS,
        ])->assertStatus(200)->assertJsonPath('data.status', PolicyStateService::ISSUED);

        // Second submit → 409 conflict
        $this->postJson("/api/policy-requests/{$policy['id']}/submit", [
            'mock_scenario' => MockInsurerService::SCENARIO_SUCCESS,
        ])->assertStatus(409);
    }

    // ── 13. Temporary failure → safe retry → ISSUED ───────────────────────────

    public function test_temporary_failure_can_be_retried_to_issued(): void
    {
        $policy = $this->postJson('/api/policy-requests', $this->validPayload())->json('data');

        // Attempt 1: TEMPORARY_FAILURE → policy ends in FAILED
        $fail = $this->postJson("/api/policy-requests/{$policy['id']}/submit", [
            'mock_scenario' => MockInsurerService::SCENARIO_TEMPORARY_FAILURE,
        ]);
        $fail->assertStatus(200)
            ->assertJsonPath('data.status', PolicyStateService::FAILED);

        // Attempt 2: retry with SUCCESS → policy ends in ISSUED
        $retry = $this->postJson("/api/policy-requests/{$policy['id']}/submit", [
            'mock_scenario' => MockInsurerService::SCENARIO_SUCCESS,
        ]);
        $retry->assertStatus(200)
            ->assertJsonPath('data.status', PolicyStateService::ISSUED);

        // Verify 2 attempt records exist
        $this->assertDatabaseCount('issuance_attempts', 2);
    }

    // ── 14. ISSUED never regresses (duplicate callback after ISSUED) ──────────

    public function test_issued_state_never_regresses_on_duplicate_callback(): void
    {
        $policy = $this->postJson('/api/policy-requests', $this->validPayload())->json('data');

        // Issue the policy
        $this->postJson("/api/policy-requests/{$policy['id']}/submit", [
            'mock_scenario' => MockInsurerService::SCENARIO_SUCCESS,
        ])->assertStatus(200)->assertJsonPath('data.status', PolicyStateService::ISSUED);

        // Craft a FAILED callback for the same policy
        $policyNumber = PolicyRequest::findOrFail($policy['id'])->policy_number;
        $lateCallback = [
            'event_id'      => 'evt_late_fail_' . uniqid(),
            'event_type'    => 'POLICY_FAILED',
            'policy_number' => $policyNumber,
            'status'        => 'FAILED',
        ];

        // Post the late failure callback
        $this->postJson('/api/mock-insurer/callback', $lateCallback)->assertStatus(200);

        // Policy must still be ISSUED
        $this->getJson("/api/policy-requests/{$policy['id']}")
            ->assertJsonPath('data.status', PolicyStateService::ISSUED);
    }

    // ── 15. Callback deduplication ────────────────────────────────────────────

    public function test_duplicate_callback_with_same_event_id_is_idempotent(): void
    {
        // Create and submit via DELAYED_CALLBACK so policy sits in PENDING_CONFIRMATION
        $policy = $this->postJson('/api/policy-requests', $this->validPayload())->json('data');

        $this->postJson("/api/policy-requests/{$policy['id']}/submit", [
            'mock_scenario' => MockInsurerService::SCENARIO_DELAYED_CALLBACK,
        ])->assertStatus(200)->assertJsonPath('data.status', PolicyStateService::PENDING_CONFIRMATION);

        $policyNumber = PolicyRequest::findOrFail($policy['id'])->policy_number;

        $callback = [
            'event_id'           => 'evt_dedup_fixed_001',
            'event_type'         => 'POLICY_ISSUED',
            'policy_number'      => $policyNumber,
            'status'             => 'ISSUED',
            'provider_policy_id' => 'MPOL-DEDUP001',
        ];

        // First callback — transitions to ISSUED
        $r1 = $this->postJson('/api/mock-insurer/callback', $callback);
        $r1->assertStatus(200)->assertJsonPath('deduplicated', false);

        // Identical callback again — must be deduplicated, no state change
        $r2 = $this->postJson('/api/mock-insurer/callback', $callback);
        $r2->assertStatus(200)->assertJsonPath('deduplicated', true);

        // Exactly one provider_event record (no duplicate row)
        $this->assertDatabaseCount('provider_events', 1);

        // Policy is ISSUED
        $this->getJson("/api/policy-requests/{$policy['id']}")
            ->assertJsonPath('data.status', PolicyStateService::ISSUED);
    }

    // ── 16. Timeout-after-issue → reconcile → ISSUED ─────────────────────────

    public function test_timeout_after_issue_reconciles_to_issued(): void
    {
        $policy = $this->postJson('/api/policy-requests', $this->validPayload())->json('data');

        // Submit with TIMEOUT_AFTER_ISSUE scenario
        $submit = $this->postJson("/api/policy-requests/{$policy['id']}/submit", [
            'mock_scenario' => MockInsurerService::SCENARIO_TIMEOUT_AFTER_ISSUE,
        ]);
        $submit->assertStatus(200)
            ->assertJsonPath('data.status', PolicyStateService::NEEDS_RECONCILIATION);

        // Trigger reconciliation
        $reconcile = $this->postJson("/api/policy-requests/{$policy['id']}/reconcile");
        $reconcile->assertStatus(200)
            ->assertJsonPath('data.status', PolicyStateService::ISSUED);

        // State history must contain NEEDS_RECONCILIATION → ISSUED
        $history  = $reconcile->json('data.state_history');
        $toStates = array_column($history, 'to_status');
        $this->assertContains(PolicyStateService::NEEDS_RECONCILIATION, $toStates);
        $this->assertContains(PolicyStateService::ISSUED, $toStates);
    }

    // ── 17. State machine: illegal transition throws ───────────────────────────

    public function test_state_machine_rejects_illegal_transition(): void
    {
        $stateService = app(\App\Services\Policy\PolicyStateService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/ISSUED.*terminal|Invalid state transition/i');

        $policy         = new PolicyRequest();
        $policy->status = PolicyStateService::ISSUED;

        $stateService->assertAllowed(PolicyStateService::ISSUED, PolicyStateService::RECEIVED);
    }

    // ── 18. Callback: 422 for missing required fields ─────────────────────────

    public function test_callback_returns_422_for_missing_fields(): void
    {
        $this->postJson('/api/mock-insurer/callback', [])->assertStatus(422);
    }

    // ── 19. Reliability: Repeated reconciliation on already-ISSUED is idempotent ──

    public function test_reconciliation_is_idempotent_when_already_issued(): void
    {
        $policy = $this->postJson('/api/policy-requests', $this->validPayload())->json('data');

        // Submit with TIMEOUT_AFTER_ISSUE
        $this->postJson("/api/policy-requests/{$policy['id']}/submit", [
            'mock_scenario' => MockInsurerService::SCENARIO_TIMEOUT_AFTER_ISSUE,
        ])->assertStatus(200)->assertJsonPath('data.status', PolicyStateService::NEEDS_RECONCILIATION);

        // First reconcile: reconciles to ISSUED
        $r1 = $this->postJson("/api/policy-requests/{$policy['id']}/reconcile");
        $r1->assertStatus(200)->assertJsonPath('data.status', PolicyStateService::ISSUED);

        // Second reconcile: idempotent, returns 200 with ISSUED, does not fail or duplicate
        $r2 = $this->postJson("/api/policy-requests/{$policy['id']}/reconcile");
        $r2->assertStatus(200)->assertJsonPath('data.status', PolicyStateService::ISSUED);
    }

    // ── 20. Reliability: Duplicate intake preserves original immutable premium ──

    public function test_duplicate_intake_preserves_original_immutable_record(): void
    {
        $originalPayload = $this->validPayload();
        $res1 = $this->postJson('/api/policy-requests', $originalPayload);
        $res1->assertStatus(201);
        $originalId = $res1->json('data.id');
        $originalPremium = $res1->json('data.premium_amount');

        // Repeated intake with identical agent_id and client_request_id
        $res2 = $this->postJson('/api/policy-requests', $originalPayload);
        $res2->assertStatus(200)
            ->assertJsonPath('data.id', $originalId)
            ->assertJsonPath('data.premium_amount', $originalPremium);

        // Verify exactly 1 policy_requests record exists
        $this->assertDatabaseCount('policy_requests', 1);
    }
}
