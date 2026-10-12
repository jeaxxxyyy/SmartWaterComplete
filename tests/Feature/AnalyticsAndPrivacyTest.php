<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Device;
use App\Models\LeakAlert;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_overview_uses_a_compact_operational_header_and_real_dashboard_sections(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Operations Dashboard')
            ->assertSee('Analytics period')
            ->assertSee('Pending activation')
            ->assertSee('Municipal work requiring attention')
            ->assertDontSee('Run the Smart Water Monitoring System from a single operations console.');
    }

    public function test_admin_analytics_uses_real_records_and_reports_device_health(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create(['role' => 'user']);
        $secondHousehold = User::factory()->create(['role' => 'user', 'is_active' => false]);
        $deviceIdentity = User::factory()->create(['role' => 'device', 'assigned_user_id' => $household->id]);
        $connected = Device::create([
            'uuid' => (string) Str::uuid(), 'asset_number' => 'AN-1', 'serial_number' => 'SN-1',
            'name' => 'Connected meter', 'household_id' => $household->id, 'auth_user_id' => $deviceIdentity->id,
            'status' => 'active', 'last_seen_at' => now(),
        ]);
        $waiting = Device::create([
            'uuid' => (string) Str::uuid(), 'asset_number' => 'AN-2', 'serial_number' => 'SN-2',
            'name' => 'Waiting meter', 'household_id' => $secondHousehold->id,
            'auth_user_id' => User::factory()->create(['role' => 'device', 'assigned_user_id' => $secondHousehold->id])->id,
            'status' => 'active', 'last_seen_at' => now()->subDay(),
        ]);

        WaterReading::create([
            'user_id' => $household->id, 'device_id' => $connected->id, 'flow_rate' => 1,
            'liters_used' => 120, 'cubic_meter' => .12, 'estimated_bill' => 3.5,
            'recorded_at' => now()->subDay(), 'server_connection_status' => 'connected',
        ]);
        Notification::create([
            'user_id' => $household->id, 'type' => 'possible_leak', 'title' => 'Leak', 'message' => 'Review flow',
            'sent_at' => now(),
        ]);
        LeakAlert::create([
            'user_id' => $household->id,
            'latest_water_reading_id' => $connected->readings()->latest('id')->value('id'),
            'notification_id' => Notification::where('user_id', $household->id)->latest('id')->value('id'),
            'status' => LeakAlert::STATUS_DETECTED,
            'detected_at' => now()->subDay(),
            'last_detected_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/analytics/admin?range=last_30_days')
            ->assertOk()
            ->assertJsonPath('data.households.total', 2)
            ->assertJsonPath('data.households.active', 1)
            ->assertJsonPath('data.households.inactive', 1)
            ->assertJsonPath('data.households.suspended', 0)
            ->assertJsonPath('data.devices.total', 2)
            ->assertJsonPath('data.devices.connected', 1)
            ->assertJsonPath('data.devices.offline', 1)
            ->assertJsonPath('data.devices.pending_activation', 0)
            ->assertJsonPath('data.devices.waiting_for_first_reading', 1)
            ->assertJsonPath('data.consumption.has_data', true)
            ->assertJsonPath('data.alerts.possible_leak_count', 1)
            ->assertJsonPath('data.alerts.unresolved_leak_alerts', 1)
            ->assertJsonPath('data.period.timezone', config('app.timezone'));
    }

    public function test_household_analytics_and_billing_settings_are_scoped_to_the_authenticated_household(): void
    {
        $household = User::factory()->create(['role' => 'user']);
        $otherHousehold = User::factory()->create(['role' => 'user']);
        WaterReading::create([
            'user_id' => $household->id, 'flow_rate' => 0, 'liters_used' => 0,
            'cubic_meter' => 0, 'estimated_bill' => 0, 'recorded_at' => now()->subDay(),
        ]);
        WaterReading::create([
            'user_id' => $otherHousehold->id, 'flow_rate' => 5, 'liters_used' => 999,
            'cubic_meter' => .999, 'estimated_bill' => 999, 'recorded_at' => now()->subDay(),
        ]);
        Notification::create([
            'user_id' => $otherHousehold->id, 'type' => 'announcement', 'title' => 'Private notice', 'message' => 'Private',
        ]);
        Billing::create([
            'user_id' => $otherHousehold->id, 'water_rate' => 999, 'subsidy_deduction' => 0,
            'threshold_liters' => 100, 'leak_flow_rate_threshold' => 2, 'quiet_hours_start' => 22,
            'quiet_hours_end' => 4, 'is_active' => true,
        ]);

        Sanctum::actingAs($household);

        $this->getJson('/api/analytics/household?range=last_30_days')
            ->assertOk()
            ->assertJsonPath('data.current_period.total_liters', 0)
            ->assertJsonMissing(['title' => 'Private notice']);

        $this->getJson('/api/billing-settings?user_id='.$otherHousehold->id)
            ->assertOk()
            ->assertJsonPath('data.water_rate', '0.00');
    }

    public function test_analytics_rejects_an_excessively_large_custom_range(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/analytics/household?range=custom&from=2024-01-01&to=2026-01-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('range');
    }
}
