<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_attempts_are_rate_limited(): void
    {
        $request = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10']);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $request->postJson('/api/login', [
                'email' => 'rate-limit@example.com',
                'password' => 'incorrect-password',
            ])->assertUnprocessable();
        }

        $request->postJson('/api/login', [
            'email' => 'rate-limit@example.com',
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }

    public function test_household_password_attempts_are_rate_limited(): void
    {
        $household = User::factory()->create([
            'role' => 'user',
            'password' => Hash::make('current-password'),
        ]);
        Sanctum::actingAs($household);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->patchJson('/api/account/password', [
                'current_password' => 'incorrect-password',
                'password' => 'replacement-password',
                'password_confirmation' => 'replacement-password',
            ])->assertUnprocessable();
        }

        $this->patchJson('/api/account/password', [
            'current_password' => 'incorrect-password',
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])->assertTooManyRequests();
    }

    public function test_frontend_uses_lightweight_live_polling_and_request_timeouts(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('const REQUEST_TIMEOUT_MS = 15000;', $script);
        $this->assertStringContainsString("requestJson('/api/live-state')", $script);
        $this->assertStringContainsString("requestJson('/api/live-readings?limit=30')", $script);
        $this->assertStringContainsString('refreshLiveDashboard();', $script);
        $this->assertStringContainsString('const FULL_DASHBOARD_REFRESH_MS = 60000;', $script);
    }
}
