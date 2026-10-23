<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

Route::get('/health', function () {
    $dbConnected = false;
    $dbError = null;

    try {
        DB::connection()->getPdo();
        $dbConnected = true;
    } catch (\Throwable $e) {
        $dbError = config('app.debug') ? $e->getMessage() : 'Database connection unavailable';
    }

    $status = $dbConnected ? 'ok' : 'degraded';

    return response()->json([
        'status' => $status,
        'app' => config('app.name', 'PolicyFlow'),
        'environment' => config('app.env'),
        'timestamp' => now()->toIso8601String(),
        'services' => [
            'database' => [
                'status' => $dbConnected ? 'connected' : 'disconnected',
                'driver' => config('database.default'),
                'error' => $dbError,
            ],
        ],
    ], $dbConnected ? 200 : 503);
});

Route::get('/status', function () {
    return response()->json([
        'status' => 'online',
        'app' => config('app.name', 'PolicyFlow'),
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

