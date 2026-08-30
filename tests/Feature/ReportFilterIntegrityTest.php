<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportFilterIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_report_filters_accept_households_and_reject_device_identities(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create(['role' => 'user']);
        $deviceIdentity = User::factory()->create([
            'role' => 'device',
            'assigned_user_id' => $household->id,
        ]);
        Sanctum::actingAs($admin);

        foreach ([
            '/api/readings',
            '/api/live-readings',
            '/api/consumption-history',
            '/api/live-state',
            '/api/summary',
            '/api/reports/consumption',
            '/api/reports/notifications',
        ] as $endpoint) {
            $separator = str_contains($endpoint, '?') ? '&' : '?';

            $this->getJson($endpoint.$separator.'user_id='.$household->id)->assertSuccessful();
            $this->getJson($endpoint.$separator.'user_id='.$deviceIdentity->id)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('user_id');
        }
    }
}
