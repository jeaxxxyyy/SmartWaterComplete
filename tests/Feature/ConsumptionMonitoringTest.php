<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Device;
use App\Models\User;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumptionMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_monitoring_returns_households_and_real_consumption_without_generic_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create([
            'name' => 'King Dela Cruz',
            'email' => 'king@example.com',
            'account_number' => 'ACC-KING-01',
            'address' => '12 Water Street',
        ]);
        $deviceUser = User::factory()->create(['role' => 'device', 'email' => 'meter-token@example.com']);
        $otherAdmin = User::factory()->admin()->create(['email' => 'other-admin@example.com']);
        $device = $this->createDevice($household, $deviceUser, [
            'name' => 'Kitchen CoreS3',
            'asset_number' => 'MWM-KING-01',
            'serial_number' => 'CORE-KING-01',
            'status' => 'active',
            'last_seen_at' => now(),
        ]);
        $this->createReading($household, $device, [
            'liters_used' => 2400,
            'cubic_meter' => 2.4,
            'estimated_bill' => 96,
            'is_threshold_exceeded' => true,
            'recorded_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/consumption-monitoring');

        $response->assertOk()
            ->assertJsonPath('meta.household_count', 1)
            ->assertJsonPath('meta.total_liters', 2400)
            ->assertJsonPath('meta.estimated_bill', 96)
            ->assertJsonPath('data.data.0.household.name', 'King Dela Cruz')
            ->assertJsonPath('data.data.0.device.asset_number', 'MWM-KING-01')
            ->assertJsonPath('data.data.0.summary.condition', 'high')
            ->assertJsonPath('data.data.0.summary.total_cubic_meter', 2.4)
            ->assertJsonMissing(['role' => 'admin'])
            ->assertJsonMissing(['email' => $otherAdmin->email])
            ->assertJsonMissing(['email' => $deviceUser->email]);
    }

    public function test_admin_searches_households_by_meter_and_filters_usage_and_meter_status_on_the_backend(): void
    {
        $admin = User::factory()->admin()->create();
        $first = User::factory()->create(['name' => 'First Household']);
        $second = User::factory()->create(['name' => 'Second Household', 'account_status' => User::ACCOUNT_STATUS_SUSPENDED]);
        $firstDeviceUser = User::factory()->create(['role' => 'device', 'email' => 'first-meter@example.com']);
        $secondDeviceUser = User::factory()->create(['role' => 'device', 'email' => 'second-meter@example.com']);
        $firstDevice = $this->createDevice($first, $firstDeviceUser, ['asset_number' => 'METER-FIRST', 'serial_number' => 'SERIAL-FIRST', 'status' => 'active']);
        $secondDevice = $this->createDevice($second, $secondDeviceUser, ['asset_number' => 'METER-SECOND', 'serial_number' => 'SERIAL-SECOND', 'status' => 'pending']);
        $this->createReading($first, $firstDevice, ['is_possible_leak' => true, 'recorded_at' => now()->subHours(2)]);
        $this->createReading($second, $secondDevice, ['recorded_at' => now()->subHours(3)]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/consumption-monitoring?search=SERIAL-FIRST')
            ->assertOk()
            ->assertJsonPath('meta.household_count', 1)
            ->assertJsonPath('data.data.0.household.name', 'First Household');

        $this->getJson('/api/consumption-monitoring?usage_condition=leak')
            ->assertOk()
            ->assertJsonPath('meta.household_count', 1)
            ->assertJsonPath('data.data.0.household.name', 'First Household');

        $this->getJson('/api/consumption-monitoring?meter_status=pending&account_status=suspended')
            ->assertOk()
            ->assertJsonPath('meta.household_count', 1)
            ->assertJsonPath('data.data.0.household.name', 'Second Household');
    }

    public function test_admin_history_returns_daily_weekly_monthly_totals_and_active_rate(): void
    {
        $anchor = Carbon::parse('2026-08-15 12:00:00', config('app.timezone'));
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create();
        $deviceUser = User::factory()->create(['role' => 'device', 'email' => 'history-meter@example.com']);
        $device = $this->createDevice($household, $deviceUser);
        Billing::create([
            'user_id' => null,
            'water_rate' => 40,
            'subsidy_deduction' => 0,
            'threshold_liters' => 10000,
            'leak_flow_rate_threshold' => 20,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
            'is_active' => true,
        ]);
        $this->createReading($household, $device, [
            'liters_used' => 1000,
            'cubic_meter' => 1,
            'estimated_bill' => 40,
            'recorded_at' => $anchor->copy()->subDay(),
        ]);
        $this->createReading($household, $device, [
            'liters_used' => 500,
            'cubic_meter' => 0.5,
            'estimated_bill' => 20,
            'recorded_at' => $anchor->copy()->subHours(2),
        ]);

        Sanctum::actingAs($admin);
        $this->getJson("/api/consumption-monitoring/{$household->id}/history?from=".$anchor->copy()->subDays(10)->toDateString().'&to='.$anchor->toDateString())
            ->assertOk()
            ->assertJsonPath('data.summary.total_liters', 1500)
            ->assertJsonPath('data.summary.estimated_bill', 60)
            ->assertJsonPath('data.active_billing.water_rate', 40)
            ->assertJsonCount(2, 'data.daily')
            ->assertJsonCount(1, 'data.weekly')
            ->assertJsonCount(1, 'data.monthly');
    }

    public function test_household_cannot_access_admin_consumption_monitoring(): void
    {
        $household = User::factory()->create();
        Sanctum::actingAs($household);

        $this->getJson('/api/consumption-monitoring')->assertForbidden();
    }

    public function test_consumption_export_is_household_scoped_and_rejects_non_household_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create();
        $deviceUser = User::factory()->create(['role' => 'device', 'email' => 'export-meter@example.com']);
        $device = $this->createDevice($household, $deviceUser);
        $this->createReading($household, $device);
        $otherAdmin = User::factory()->admin()->create(['email' => 'export-admin@example.com']);

        Sanctum::actingAs($admin);

        $this->get('/api/exports/readings?user_id='.$household->id)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->getJson('/api/exports/readings?user_id='.$otherAdmin->id)->assertNotFound();
    }

    private function createDevice(User $household, User $deviceUser, array $overrides = []): Device
    {
        return Device::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'asset_number' => 'METER-'.Str::upper(Str::random(8)),
            'serial_number' => 'SERIAL-'.Str::upper(Str::random(8)),
            'name' => 'CoreS3 Water Meter',
            'household_id' => $household->id,
            'auth_user_id' => $deviceUser->id,
            'status' => 'active',
        ], $overrides));
    }

    private function createReading(User $household, Device $device, array $overrides = []): WaterReading
    {
        return WaterReading::create(array_merge([
            'user_id' => $household->id,
            'device_id' => $device->id,
            'flow_rate' => 3,
            'liters_used' => 120,
            'cubic_meter' => 0.12,
            'estimated_bill' => 4.8,
            'rate_per_cubic_meter' => 40,
            'subsidy_deduction' => 0,
            'threshold_liters' => 10000,
            'is_threshold_exceeded' => false,
            'is_possible_leak' => false,
            'recorded_at' => now(),
        ], $overrides));
    }
}
