<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Device;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumptionChangeAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_one_relevant_increase_alert_uses_the_first_month_as_its_permanent_baseline(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');
        [$household, $deviceIdentity] = $this->householdAndDevice();
        $this->settings(100);
        $this->previousReading($household, 100, '2026-06-15 12:00:00');
        $this->previousReading($household, 100, '2026-07-15 12:00:00');
        Sanctum::actingAs($deviceIdentity, ['device-upload']);

        $this->postJson('/api/water-data', $this->payload('increase-1', 200))
            ->assertCreated();

        $notice = Notification::query()->where('type', 'consumption_change')->firstOrFail();
        $this->assertSame('increase', $notice->metadata['change_direction']);
        $this->assertSame(100.0, (float) $notice->metadata['percentage_change']);
        $this->assertSame(200.0, (float) $notice->metadata['usage_percentage']);
        $this->assertSame(200.0, (float) $notice->metadata['current_liters']);
        $this->assertSame(100.0, (float) $notice->metadata['baseline_liters']);
        $this->assertSame('2026-07', $notice->metadata['baseline_month']);
        $this->assertSame('Your water use this month is 100.00% higher than your July baseline.', $notice->message);

        $this->postJson('/api/water-data', $this->payload('increase-2', 10))->assertCreated();
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_a_one_hundred_percent_decrease_is_identified_from_actual_usage(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');
        [$household, $deviceIdentity] = $this->householdAndDevice();
        $this->settings(100);
        $this->previousReading($household, 100, '2026-07-15 12:00:00');
        Sanctum::actingAs($deviceIdentity, ['device-upload']);

        $this->postJson('/api/water-data', $this->payload('decrease-1', 0))->assertCreated();

        $notice = Notification::query()->where('type', 'consumption_change')->firstOrFail();
        $this->assertSame('decrease', $notice->metadata['change_direction']);
        $this->assertSame(-100.0, (float) $notice->metadata['percentage_change']);
        $this->assertSame('Your water use this month is 100.00% lower than your July baseline.', $notice->message);
    }

    public function test_no_alert_is_sent_without_a_previous_month_baseline(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');
        [, $deviceIdentity] = $this->householdAndDevice();
        $this->settings(100);
        Sanctum::actingAs($deviceIdentity, ['device-upload']);

        $this->postJson('/api/water-data', $this->payload('no-baseline-1', 500))->assertCreated();

        $this->assertDatabaseMissing('notifications', ['type' => 'consumption_change']);
    }

    public function test_admin_analytics_reports_each_month_as_a_percentage_of_the_first_month(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create(['name' => 'Baseline Household']);
        $this->previousReading($household, 100, '2026-06-15 12:00:00');
        $this->previousReading($household, 150, '2026-07-15 12:00:00');
        $this->previousReading($household, 50, '2026-08-15 12:00:00');
        Sanctum::actingAs($admin);

        $this->getJson('/api/analytics/admin?range=last_12_months')
            ->assertOk()
            ->assertJsonPath('data.alerts.consumption_baselines.0.household.name', 'Baseline Household')
            ->assertJsonPath('data.alerts.consumption_baselines.0.baseline.month', '2026-07')
            ->assertJsonPath('data.alerts.consumption_baselines.0.baseline.usage_percentage', 100)
            ->assertJsonPath('data.alerts.consumption_baselines.0.months.1.usage_percentage', 100)
            ->assertJsonPath('data.alerts.consumption_baselines.0.latest.month', '2026-08')
            ->assertJsonPath('data.alerts.consumption_baselines.0.latest.usage_percentage', 33.33)
            ->assertJsonPath('data.alerts.consumption_baselines.0.latest.change_percent', -66.67);
    }

    /** @return array{User, User} */
    private function householdAndDevice(): array
    {
        $household = User::factory()->create(['role' => 'user']);
        $deviceIdentity = User::factory()->create([
            'role' => 'device',
            'assigned_user_id' => $household->id,
        ]);
        Device::create([
            'uuid' => (string) Str::uuid(),
            'asset_number' => 'CHANGE-'.Str::upper(Str::random(8)),
            'serial_number' => 'CORE-'.Str::upper(Str::random(8)),
            'name' => 'Comparison Meter',
            'household_id' => $household->id,
            'auth_user_id' => $deviceIdentity->id,
            'status' => 'active',
        ]);

        return [$household, $deviceIdentity];
    }

    private function settings(float $percentage): void
    {
        Billing::create([
            'water_rate' => 25,
            'subsidy_deduction' => 0,
            'threshold_liters' => 5000,
            'consumption_alert_enabled' => true,
            'consumption_change_threshold_percent' => $percentage,
            'consumption_alert_unit' => 'percentage',
            'consumption_alert_behavior' => 'household',
            'leak_flow_rate_threshold' => 100,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
            'is_active' => true,
        ]);
    }

    private function previousReading(User $household, float $liters, string $recordedAt): void
    {
        WaterReading::create([
            'user_id' => $household->id,
            'flow_rate' => 1,
            'liters_used' => $liters,
            'cubic_meter' => $liters / 1000,
            'recorded_at' => $recordedAt,
        ]);
    }

    /** @return array<string, int|float|string> */
    private function payload(string $readingId, float $liters): array
    {
        return [
            'reading_id' => $readingId,
            'flow_rate' => 1,
            'liters_used' => $liters,
            'cubic_meter' => $liters / 1000,
            'recorded_at' => '2026-08-15 12:00:00',
        ];
    }
}
