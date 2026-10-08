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

    public function test_dashboard_cache_is_user_scoped_and_logout_has_a_transition(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('sessionStorage.setItem(key', $script);
        $this->assertStringContainsString('`${dashboardCachePrefix}:${user.id}:${url}`', $script);
        $this->assertStringContainsString("url.startsWith('/api/live-')", $script);
        $this->assertStringContainsString('warmDashboardCache(response.data.user)', $script);
        $this->assertStringContainsString("document.body.classList.add('logout-is-transitioning')", $script);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('data-logout-transition', false);
    }

    public function test_browser_and_api_responses_include_baseline_security_headers(): void
    {
        foreach (['/login', '/api/ping'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
                ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        }
    }

    public function test_web_login_tokens_expire(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('household-password'),
            'created_by' => User::factory()->admin()->create()->id,
            'approved_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'household-password',
        ])->assertOk();

        $tokenId = (int) explode('|', $response->json('data.token'), 2)[0];
        $this->assertNotNull($user->tokens()->findOrFail($tokenId)->expires_at);
    }

    public function test_device_secrets_are_ignored_and_a_safe_template_is_available(): void
    {
        $gitignore = file_get_contents(base_path('.gitignore'));
        $template = file_get_contents(base_path('arduino/SmartWater_Modular/secrets.example.h'));

        $this->assertStringContainsString('/arduino/SmartWater_Modular/secrets.h', $gitignore);
        $this->assertStringContainsString('YOUR_WIFI_SSID', $template);
        $this->assertStringContainsString('YOUR_WIFI_PASSWORD', $template);
        $this->assertStringContainsString('YOUR_DEVICE_UUID', $template);
        $this->assertStringContainsString('YOUR_ACTIVATION_CODE', $template);
    }

    public function test_production_environment_template_uses_safe_defaults(): void
    {
        $environment = file_get_contents(base_path('.env.production.example'));

        $this->assertStringContainsString('APP_ENV=production', $environment);
        $this->assertStringContainsString('APP_DEBUG=false', $environment);
        $this->assertStringContainsString('APP_URL=https://', $environment);
        $this->assertStringContainsString('SESSION_ENCRYPT=true', $environment);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $environment);
        $this->assertStringContainsString('LOG_LEVEL=warning', $environment);
    }
}
