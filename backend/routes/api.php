<?php

use App\Http\Controllers\MockInsurerCallbackController;
use App\Http\Controllers\PolicyRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ── Health / Status ────────────────────────────────────────────────────────

Route::get('/health', function () {
    $dbConnected = false;
    $dbError     = null;

    try {
        DB::connection()->getPdo();
        $dbConnected = true;
    } catch (\Throwable $e) {
        $dbError = config('app.debug') ? $e->getMessage() : 'Database connection unavailable';
    }

    $status = $dbConnected ? 'ok' : 'degraded';

    return response()->json([
        'status'      => $status,
        'app'         => config('app.name', 'PolicyFlow'),
        'environment' => config('app.env'),
        'timestamp'   => now()->toIso8601String(),
        'services'    => [
            'database' => [
                'status' => $dbConnected ? 'connected' : 'disconnected',
                'driver' => config('database.default'),
                'error'  => $dbError,
            ],
        ],
    ], $dbConnected ? 200 : 503);
});

Route::get('/status', function () {
    return response()->json([
        'status'    => 'online',
        'app'       => config('app.name', 'PolicyFlow'),
        'version'   => '1.0.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// ── Policy Request API ─────────────────────────────────────────────────────

Route::prefix('policy-requests')->group(function () {
    // List (paginated, optional ?status= filter)
    Route::get('/', [PolicyRequestController::class, 'index']);

    // Intake — idempotent on (agent_id, client_request_id)
    Route::post('/', [PolicyRequestController::class, 'store']);

    // Detail with relations
    Route::get('/{id}', [PolicyRequestController::class, 'show']);

    // Submit to mock insurer
    Route::post('/{id}/submit', [PolicyRequestController::class, 'submit']);

    // Trigger reconciliation for NEEDS_RECONCILIATION policies
    Route::post('/{id}/reconcile', [PolicyRequestController::class, 'reconcile']);
});

// ── Mock Insurer Callback ──────────────────────────────────────────────────

Route::post('/mock-insurer/callback', [MockInsurerCallbackController::class, 'handle']);

// ── Auth (unused in this project — kept from scaffold) ────────────────────

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
