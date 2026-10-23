<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the /api/health endpoint returns structured operational status.
     */
    public function test_health_endpoint_returns_ok_with_database_connected(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'app',
                'environment',
                'timestamp',
                'services' => [
                    'database' => [
                        'status',
                        'driver',
                    ],
                ],
            ])
            ->assertJson([
                'status' => 'ok',
                'services' => [
                    'database' => [
                        'status' => 'connected',
                    ],
                ],
            ]);
    }

    /**
     * Test the /api/status endpoint returns lightweight status.
     */
    public function test_status_endpoint_returns_online(): void
    {
        $response = $this->getJson('/api/status');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'online',
                'app' => 'PolicyFlow',
            ]);
    }

    /**
     * Test that CORS headers are returned for configured frontend development origin.
     */
    public function test_cors_headers_are_returned_for_react_origin(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
        ])->getJson('/api/status');

        $response->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
