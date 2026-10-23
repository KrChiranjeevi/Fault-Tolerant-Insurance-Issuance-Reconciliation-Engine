<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePolicyRequest;
use App\Models\PolicyRequest;
use App\Services\Insurer\MockInsurerService;
use App\Services\Policy\IssuanceService;
use App\Services\Policy\PolicyService;
use App\Services\Policy\PolicyStateService;
use App\Services\Pricing\Exceptions\PricingException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PolicyRequestController extends Controller
{
    public function __construct(
        protected PolicyService   $policyService,
        protected IssuanceService $issuanceService,
        protected PolicyStateService $stateService,
    ) {}

    // -----------------------------------------------------------------------
    // POST /api/policy-requests
    // -----------------------------------------------------------------------

    /**
     * Intake a new policy request.
     *
     * Idempotent on (agent_id, client_request_id): repeated calls with the
     * same pair return the existing record (HTTP 200) without creating another.
     * Under concurrent requests for the same pair only one INSERT wins; the
     * loser catches the unique-constraint violation and fetches the winner's row.
     */
    public function store(StorePolicyRequest $request): JsonResponse
    {
        $data    = $request->validated();
        $agentId = (string) $data['agent_id'];
        $crId    = (string) $data['client_request_id'];

        // Fast-path: already exists?
        $existing = PolicyRequest::where('agent_id', $agentId)
            ->where('client_request_id', $crId)
            ->first();

        if ($existing) {
            return $this->policyResponse($existing, 200);
        }

        try {
            $policy = $this->policyService->createPolicyRequest($data);
            return $this->policyResponse($policy, 201);

        } catch (UniqueConstraintViolationException) {
            // Concurrent request won the INSERT race — fetch and return their row
            $policy = PolicyRequest::where('agent_id', $agentId)
                ->where('client_request_id', $crId)
                ->firstOrFail();

            return $this->policyResponse($policy, 200);

        } catch (PricingException $e) {
            // Unsupported plan, destination, coverage or invalid date range
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => ['pricing' => [$e->getMessage()]],
            ], 422);
        }
    }

    // -----------------------------------------------------------------------
    // GET /api/policy-requests/{id}
    // -----------------------------------------------------------------------

    public function show(string $id): JsonResponse
    {
        $policy = PolicyRequest::with(['issuanceAttempts', 'providerEvents', 'stateHistories'])
            ->findOrFail($id);

        return $this->policyResponse($policy, 200, withRelations: true);
    }

    // -----------------------------------------------------------------------
    // POST /api/policy-requests/{id}/submit
    // -----------------------------------------------------------------------

    /**
     * Submit the policy to the mock insurer.
     *
     * Accepts optional `mock_scenario` in the request body so callers and tests
     * can exercise different insurer paths without changing application code.
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'mock_scenario' => 'nullable|string|in:SUCCESS,TEMPORARY_FAILURE,TIMEOUT_AFTER_ISSUE,DELAYED_CALLBACK,DUPLICATE_CALLBACK',
        ]);

        $policy = PolicyRequest::findOrFail($id);

        // Only RECEIVED/VALIDATED/FAILED policies may be submitted
        $submitableStates = [
            PolicyStateService::RECEIVED,
            PolicyStateService::VALIDATED,
            PolicyStateService::FAILED,
        ];

        if (!in_array($policy->status, $submitableStates, true)) {
            return response()->json([
                'error'   => 'conflict',
                'message' => "Policy cannot be submitted from status '{$policy->status}'.",
                'status'  => $policy->status,
            ], 409);
        }

        // Validate if still in RECEIVED
        if ($policy->status === PolicyStateService::RECEIVED) {
            $policy = $this->stateService->transition(
                $policy, PolicyStateService::VALIDATED, 'PRE_SUBMIT_VALIDATION'
            );
        }

        $scenario = $request->input('mock_scenario', MockInsurerService::SCENARIO_SUCCESS);

        try {
            $policy = $this->issuanceService->submit($policy, $scenario);
        } catch (\RuntimeException $e) {
            Log::error('Policy submission error', ['policy_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'submission_failed', 'message' => $e->getMessage()], 422);
        }

        return $this->policyResponse($policy->fresh(['issuanceAttempts', 'providerEvents', 'stateHistories']), 200, withRelations: true);
    }

    // -----------------------------------------------------------------------
    // GET /api/policy-requests  (paginated list)
    // -----------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page'     => 'nullable|integer|min:1',
        ]);

        $query = PolicyRequest::query()->latest();

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper($status));
        }

        $perPage = (int) $request->query('per_page', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->map(fn ($p) => $this->formatPolicy($p)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /api/policy-requests/{id}/reconcile
    // -----------------------------------------------------------------------

    public function reconcile(string $id): JsonResponse
    {
        $policy = PolicyRequest::findOrFail($id);

        try {
            $policy = $this->issuanceService->reconcile($policy);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'reconcile_failed', 'message' => $e->getMessage()], 422);
        }

        return $this->policyResponse($policy->fresh(['issuanceAttempts', 'providerEvents', 'stateHistories']), 200, withRelations: true);
    }

    // -----------------------------------------------------------------------
    // Formatting helpers
    // -----------------------------------------------------------------------

    private function policyResponse(PolicyRequest $policy, int $status, bool $withRelations = false): JsonResponse
    {
        $data = $this->formatPolicy($policy, $withRelations);
        return response()->json(['data' => $data], $status);
    }

    private function formatPolicy(PolicyRequest $policy, bool $withRelations = false): array
    {
        $sumInsured = is_numeric($policy->coverage_type) ? (int) $policy->coverage_type : $policy->coverage_type;

        $base = [
            'id'                => $policy->id,
            'policy_number'     => $policy->policy_number,
            'status'            => $policy->status,
            'agent_id'          => $policy->agent_id,
            'client_request_id' => $policy->client_request_id,
            'insured_name'      => $policy->insured_name,
            'customer_name'     => $policy->insured_name,
            'insured_email'     => $policy->insured_email,
            'date_of_birth'     => $policy->date_of_birth?->toDateString(),
            'traveler_age'      => $policy->traveler_age,
            'destination'       => $policy->destination,
            'trip_start'        => $policy->trip_start?->toDateString(),
            'trip_end'          => $policy->trip_end?->toDateString(),
            'trip_days'         => $policy->trip_days,
            'plan'              => $policy->plan,
            'plan_code'         => $policy->plan,
            'coverage_type'     => $policy->coverage_type,
            'sum_insured'       => $sumInsured,
            'currency'          => $policy->currency,
            'premium_amount'    => $policy->premium_amount,
            'premium_snapshot'  => $policy->premium_snapshot,
            'metadata'          => $policy->metadata,
            'created_at'        => $policy->created_at?->toIso8601String(),
            'updated_at'        => $policy->updated_at?->toIso8601String(),
        ];

        if ($withRelations && $policy->relationLoaded('issuanceAttempts')) {
            $base['issuance_attempts'] = $policy->issuanceAttempts->map(fn ($a) => [
                'id'               => $a->id,
                'attempt_number'   => $a->attempt_number,
                'provider_code'    => $a->provider_code,
                'status'           => $a->status,
                'error_code'       => $a->error_code,
                'error_message'    => $a->error_message,
                'attempted_at'     => $a->attempted_at?->toIso8601String(),
            ])->values();

            $base['provider_events'] = $policy->providerEvents->map(fn ($e) => [
                'id'                => $e->id,
                'provider_event_id' => $e->provider_event_id,
                'event_type'        => $e->event_type,
                'status'            => $e->status,
                'processed_at'      => $e->processed_at?->toIso8601String(),
            ])->values();

            $stateHistories = $policy->stateHistories->map(fn ($h) => [
                'id'              => $h->id,
                'from_status'     => $h->from_status,
                'to_status'       => $h->to_status,
                'trigger_event'   => $h->trigger_event,
                'actor_type'      => $h->actor_type,
                'created_at'      => $h->created_at?->toIso8601String(),
                'transitioned_at' => $h->created_at?->toIso8601String(),
            ])->values();

            $base['state_history'] = $stateHistories;
            $base['state_histories'] = $stateHistories;
        }

        return $base;
    }
}
