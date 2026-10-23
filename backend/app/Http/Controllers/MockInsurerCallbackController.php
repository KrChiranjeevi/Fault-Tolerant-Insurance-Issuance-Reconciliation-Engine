<?php

namespace App\Http\Controllers;

use App\Services\Policy\IssuanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MockInsurerCallbackController extends Controller
{
    public function __construct(
        protected IssuanceService $issuanceService,
    ) {}

    /**
     * POST /api/mock-insurer/callback
     *
     * Receives a callback from the mock insurer.  This endpoint is idempotent:
     * duplicate callbacks with the same event_id are silently deduplicated and
     * HTTP 200 is returned so the caller does not need to retry.
     *
     * Out-of-order / late callbacks for an already-ISSUED policy are recorded
     * as provider events but do not mutate policy state.
     */
    public function handle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id'           => 'required|string|max:255',
            'event_type'         => 'required|string|max:100',
            'policy_number'      => 'required|string|max:100',
            'status'             => 'required|string|in:ISSUED,FAILED',
            'provider_policy_id' => 'nullable|string|max:255',
            'issued_at'          => 'nullable|string',
        ]);

        try {
            $result = $this->issuanceService->processCallback($data);
        } catch (\Exception $e) {
            Log::error('Callback processing error', ['payload' => $data, 'error' => $e->getMessage()]);
            return response()->json([
                'error'   => 'callback_processing_failed',
                'message' => $e->getMessage(),
            ], 422);
        }

        $policy = $result['policy'];

        return response()->json([
            'accepted'     => true,
            'deduplicated' => $result['deduplicated'],
            'skipped'      => $result['skipped'] ?? false,
            'policy' => [
                'id'           => $policy->id,
                'policy_number' => $policy->policy_number,
                'status'       => $policy->status,
            ],
        ], 200);
    }
}
