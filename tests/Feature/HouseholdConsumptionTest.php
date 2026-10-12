<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\Device;

class HouseholdConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private string $originalTimezone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalTimezone = (string) config('app.timezone');
        Carbon::setTestNow(Carbon::parse('2026-08-06 10:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        config(['app.timezone' => $this->originalTimezone]);
        parent::tearDown();
    }

    public function test_periods_use_the_configured_timezone_and_interval_readings(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-08-06 10:00:00', config('app.timezone')));
        $household = User::factory()->create(['role' => 'user']);
        $device = $this->device($household);

        $this->reading($household, 100, Carbon::parse('2026-08-06 09:00:00', config('app.timezone')), ['liters_used' => 100], $device);
        $this->reading($household, 200, Carbon::parse('2026-08-05 09:00:00', config('app.timezone')), ['liters_used' => 200], $device);

        Sanctum::actingAs($household);

        $this->getJson('/api/household-consumption?period=daily')
            ->assertOk()
            ->assertJsonPath('data.label', 'DAILY CONSUMPTION')
            ->assertJsonPath('data.caption', 'TODAY')
            ->assertJsonPath('data.cubic_meters', 0.1)
            ->assertJsonPath('data.timezone', 'Asia/Manila');

        $this->getJson('/api/household-consumption?period=weekly')
            ->assertOk()
            ->assertJsonPath('data.label', 'WEEKLY CONSUMPTION')
            ->assertJsonPath('data.cubic_meters', 0.3)
            ->assertJsonCount(8, 'data.trend');

        $this->getJson('/api/household-consumption?period=monthly')
            ->assertOk()
            ->assertJsonPath('data.label', 'MONTHLY CONSUMPTION')
            ->assertJsonCount(12, 'data.trend')
            ->assertJsonPath('data.trend.0.period', '2025-09')
            ->assertJsonPath('data.trend.11.period', '2026-08')
            ->assertJsonPath('data.week_starts_on', 'Monday');
    }

    public function test_monthly_summary_compares_real_current_and_previous_periods(): void
    {
        $household = User::factory()->create(['role' => 'user']);
        $device = $this->device($household);
        $this->reading($household, 180, Carbon::parse('2026-07-15 09:00:00'), ['liters_used' => 180], $device);
        $this->reading($household, 240, Carbon::parse('2026-08-05 09:00:00'), ['liters_used' => 240], $device);

        Sanctum::actingAs($household);

        $this->getJson('/api/household-consumption?period=monthly')
            ->assertOk()
            ->assertJsonPath('data.summary.current.cubic_meters', 0.24)
            ->assertJsonPath('data.summary.previous.cubic_meters', 0.18)
            ->assertJsonPath('data.summary.change_percent', 33.3)
            ->assertJsonPath('data.summary.average_cubic_meters', 0.21);
    }

    public function test_meter_state_is_returned_before_reading_aggregation(): void
    {
        $household = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($household);

        $this->getJson('/api/household-consumption?period=monthly')
            ->assertOk()
            ->assertJsonPath('data.availability', 'no_meter')
            ->assertJsonPath('data.meter', null);

        $this->device($household, 'pending');

        $this->getJson('/api/household-consumption?period=monthly')
            ->assertOk()
            ->assertJsonPath('data.availability', 'pending_activation')
            ->assertJsonPath('data.has_data', false);
    }

    public function test_availability_distinguishes_first_reading_invalid_readings_and_empty_periods(): void
    {
        $household = User::factory()->create(['role' => 'user']);
        $device = $this->device($household);
        Sanctum::actingAs($household);

        $this->getJson('/api/household-consumption?period=daily')
            ->assertOk()
            ->assertJsonPath('data.availability', 'waiting_for_first_reading');

        $this->reading($household, 100, Carbon::parse('2026-08-05 09:00:00'), ['liters_used' => 100], $device);

        $this->getJson('/api/household-consumption?period=daily')
            ->assertOk()
            ->assertJsonPath('data.availability', 'no_period_data')
            ->assertJsonPath('data.has_data', false);

        $otherHousehold = User::factory()->create(['role' => 'user']);
        $otherDevice = $this->device($otherHousehold);
        $this->reading($otherHousehold, 100, Carbon::parse('2026-08-06 09:00:00'), ['liters_used' => 100, 'reading_status' => 'invalid'], $otherDevice);
        Sanctum::actingAs($otherHousehold);

        $this->getJson('/api/household-consumption?period=daily')
            ->assertOk()
            ->assertJsonPath('data.availability', 'no_valid_readings')
            ->assertJsonPath('data.has_data', false);
    }

    public function test_cumulative_readings_use_safe_differences_and_count_resets(): void
    {
        $household = User::factory()->create(['role' => 'user']);
        $device = $this->device($household);
        $this->reading($household, 1000, Carbon::parse('2026-08-05 23:00:00'), ['total_water_liters' => 1000], $device);
        $this->reading($household, 1150, Carbon::parse('2026-08-06 08:00:00'), ['total_water_liters' => 1150], $device);
        $this->reading($household, 50, Carbon::parse('2026-08-06 09:00:00'), ['total_water_liters' => 50], $device);
        $this->reading($household, 75, Carbon::parse('2026-08-06 10:00:00'), ['total_water_liters' => 75], $device);

        Sanctum::actingAs($household);

        $this->getJson('/api/household-consumption?period=daily')
            ->assertOk()
            ->assertJsonPath('data.cubic_meters', 0.225)
            ->assertJsonPath('data.valid_reading_count', 3);
    }

    public function test_live_state_uses_the_meter_heartbeat_instead_of_flow_rate(): void
    {
        $household = User::factory()->create(['role' => 'user']);
        $device = $this->device($household);
        $device->update(['last_seen_at' => now()]);
        $this->reading($household, 0, now(), ['liters_used' => 0], $device);
        Sanctum::actingAs($household);

        $this->getJson('/api/live-state')
            ->assertOk()
            ->assertJsonPath('data.device_state.status', 'active')
            ->assertJsonPath('data.device_state.is_live', true);

        $device->update(['last_seen_at' => now()->subSeconds(config('devices.offline_seconds') + 1)]);

        $this->getJson('/api/live-state')
            ->assertOk()
            ->assertJsonPath('data.device_state.is_live', false);
    }

    public function test_zero_is_data_but_missing_readings_are_not_zero(): void
    {
        $household = User::factory()->create(['role' => 'user']);
        $this->reading($household, 0, Carbon::parse('2026-08-06 09:00:00'), ['liters_used' => 0], $this->device($household));
        Sanctum::actingAs($household);

        $this->getJson('/api/household-consumption?period=daily')
            ->assertOk()
            ->assertJsonPath('data.has_data', true)
            ->assertJsonPath('data.cubic_meters', 0)
            ->assertJsonPath('data.liters', 0);

        $otherHousehold = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($otherHousehold);

        $this->getJson('/api/household-consumption?period=daily')
            ->assertOk()
            ->assertJsonPath('data.has_data', false)
            ->assertJsonPath('data.liters', null)
            ->assertJsonPath('data.cubic_meters', null)
            ->assertJsonPath('data.has_readings', false);
    }

    private function reading(User $household, float $liters, Carbon $recordedAt, array $payload, ?Device $device = null): WaterReading
    {
        return WaterReading::create([
            'user_id' => $household->id,
            'device_id' => $device?->id,
            'flow_rate' => 0,
            'liters_used' => $liters,
            'cubic_meter' => $liters / 1000,
            'device_payload' => $payload,
            'recorded_at' => $recordedAt,
        ]);
    }

    private function device(User $household, string $status = 'active'): Device
    {
        $deviceUser = User::factory()->create(['role' => 'device', 'assigned_user_id' => $household->id]);

        return Device::create([
            'uuid' => (string) Str::uuid(),
            'asset_number' => 'METER-'.Str::upper(Str::random(8)),
            'serial_number' => 'SERIAL-'.Str::upper(Str::random(8)),
            'name' => 'Household meter',
            'household_id' => $household->id,
            'auth_user_id' => $deviceUser->id,
            'status' => $status,
        ]);
    }
}
