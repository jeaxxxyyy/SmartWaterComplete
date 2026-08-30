<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\BillingStatement;
use App\Models\Device;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;
use Tests\TestCase;

class HouseholdBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_household_receives_a_real_current_estimate_with_a_complete_breakdown(): void
    {
        $household = User::factory()->create(['name' => 'Billing Household']);
        $otherHousehold = User::factory()->create(['name' => 'Other Household']);
        $meter = $this->createMeter($household);
        Billing::create([
            'water_rate' => 40,
            'fixed_monthly_charge' => 75,
            'subsidy_deduction' => 5,
            'currency' => 'PHP',
            'effective_date' => now()->startOfMonth(),
            'threshold_liters' => 10000,
            'leak_flow_rate_threshold' => 20,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
            'is_active' => true,
        ]);
        WaterReading::create([
            'user_id' => $household->id,
            'device_id' => $meter->id,
            'flow_rate' => 2,
            'liters_used' => 1500,
            'cubic_meter' => 1.5,
            'estimated_bill' => 60,
            'rate_per_cubic_meter' => 40,
            'recorded_at' => now()->subHour(),
        ]);
        WaterReading::create([
            'user_id' => $otherHousehold->id,
            'flow_rate' => 2,
            'liters_used' => 9999,
            'cubic_meter' => 9.999,
            'estimated_bill' => 399.96,
            'rate_per_cubic_meter' => 40,
            'recorded_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($household);

        $this->getJson('/api/household-billing')
            ->assertOk()
            ->assertJsonPath('data.record_type', 'estimated')
            ->assertJsonPath('data.has_rate_configuration', true)
            ->assertJsonPath('data.reading_count', 1)
            ->assertJsonPath('data.calculation.total_cubic_meter', 1.5)
            ->assertJsonPath('data.calculation.usage_charge', 60)
            ->assertJsonPath('data.calculation.fixed_monthly_charge', 75)
            ->assertJsonPath('data.calculation.subsidy_deduction', 5)
            ->assertJsonPath('data.calculation.current_charge', 130)
            ->assertJsonMissing(['name' => 'Other Household']);
    }

    public function test_household_billing_prefers_the_own_finalized_statement_and_returns_history(): void
    {
        $household = User::factory()->create();
        $otherHousehold = User::factory()->create();
        $this->createMeter($household);
        BillingStatement::create([
            'user_id' => $household->id,
            'billing_month' => now()->startOfMonth(),
            'total_liters' => 2000,
            'total_cubic_meter' => 2,
            'water_rate' => 40,
            'fixed_monthly_charge' => 75,
            'currency' => 'PHP',
            'subsidy_deduction' => 5,
            'current_charge' => 150,
            'amount_paid' => 50,
            'remaining_balance' => 100,
            'status' => 'partial',
            'generated_at' => now(),
        ]);
        BillingStatement::create([
            'user_id' => $otherHousehold->id,
            'billing_month' => now()->startOfMonth(),
            'total_liters' => 9000,
            'total_cubic_meter' => 9,
            'water_rate' => 40,
            'current_charge' => 360,
            'remaining_balance' => 360,
            'status' => 'unpaid',
        ]);

        Sanctum::actingAs($household);

        $this->getJson('/api/household-billing')
            ->assertOk()
            ->assertJsonPath('data.record_type', 'finalized')
            ->assertJsonPath('data.calculation.current_charge', 150)
            ->assertJsonPath('data.calculation.remaining_balance', 100)
            ->assertJsonCount(1, 'data.history');
    }

    public function test_admin_cannot_access_household_billing(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/household-billing')->assertForbidden();
    }

    public function test_visible_example_calculates_the_estimate_from_meter_interval_usage(): void
    {
        $household = User::factory()->create();
        $meter = $this->createMeter($household);
        Billing::create([
            'water_rate' => 40,
            'effective_date' => now()->startOfMonth(),
            'is_active' => true,
        ]);
        WaterReading::create([
            'user_id' => $household->id,
            'device_id' => $meter->id,
            'flow_rate' => 0,
            'liters_used' => 440,
            'cubic_meter' => 0.44,
            'device_payload' => ['total_water_liters' => 440],
            'recorded_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($household);

        $this->getJson('/api/household-billing')
            ->assertOk()
            ->assertJsonPath('data.record_type', 'estimated')
            ->assertJsonPath('data.consumption.liters', 440)
            ->assertJsonPath('data.consumption.cubic_meters', 0.44)
            ->assertJsonPath('data.rate.amount', 40)
            ->assertJsonPath('data.estimate.amount', 17.6)
            ->assertJsonPath('data.calculation.current_charge', 17.6)
            ->assertJsonPath('data.latest_reading.total_liters', 440)
            ->assertJsonPath('data.meter.id', $meter->id);
    }

    public function test_household_without_an_assigned_meter_receives_a_safe_not_found_response(): void
    {
        $household = User::factory()->create();
        Sanctum::actingAs($household);

        $this->getJson('/api/household-billing')
            ->assertNotFound()
            ->assertJsonPath('message', 'No active water meter is assigned to this household.');
    }

    public function test_no_readings_does_not_return_a_zero_estimate(): void
    {
        $household = User::factory()->create();
        $this->createMeter($household);
        Billing::create(['water_rate' => 40, 'effective_date' => now()->startOfMonth(), 'is_active' => true]);
        Sanctum::actingAs($household);

        $this->getJson('/api/household-billing')
            ->assertOk()
            ->assertJsonPath('data.record_type', 'unavailable')
            ->assertJsonPath('data.estimate.amount', null)
            ->assertJsonPath('data.estimate.formatted', 'Unavailable')
            ->assertJsonPath('data.reading_count', 0);
    }

    public function test_no_active_rate_returns_a_specific_error_and_can_succeed_after_retry(): void
    {
        $household = User::factory()->create();
        $meter = $this->createMeter($household);
        WaterReading::create([
            'user_id' => $household->id,
            'device_id' => $meter->id,
            'flow_rate' => 0,
            'liters_used' => 440,
            'cubic_meter' => 0.44,
            'recorded_at' => now()->subHour(),
        ]);
        Sanctum::actingAs($household);

        $this->getJson('/api/household-billing')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No active water rate is configured for this billing period.');

        Billing::create(['water_rate' => 40, 'effective_date' => now()->startOfMonth(), 'is_active' => true]);

        $this->getJson('/api/household-billing')
            ->assertOk()
            ->assertJsonPath('data.estimate.amount', 17.6);
    }

    public function test_future_rates_are_ignored_and_latest_applicable_rate_is_selected(): void
    {
        $household = User::factory()->create();
        $meter = $this->createMeter($household);
        Billing::create(['water_rate' => 10, 'effective_date' => now()->subMonth()->startOfMonth(), 'is_active' => true]);
        Billing::create(['water_rate' => 100, 'effective_date' => now()->addDay(), 'is_active' => true]);
        WaterReading::create([
            'user_id' => $household->id,
            'device_id' => $meter->id,
            'flow_rate' => 0,
            'liters_used' => 1000,
            'cubic_meter' => 1,
            'recorded_at' => now()->subHour(),
        ]);
        Sanctum::actingAs($household);

        $this->getJson('/api/household-billing')
            ->assertOk()
            ->assertJsonPath('data.rate.amount', 10)
            ->assertJsonPath('data.estimate.amount', 10);
    }

    public function test_cumulative_device_totals_are_not_summed_as_interval_usage(): void
    {
        $household = User::factory()->create();
        $meter = $this->createMeter($household);
        Billing::create(['water_rate' => 40, 'effective_date' => now()->startOfMonth(), 'is_active' => true]);
        foreach ([100, 200] as $index => $total) {
            WaterReading::create([
                'user_id' => $household->id,
                'device_id' => $meter->id,
                'reading_id' => 'reading-'.$index,
                'flow_rate' => 0,
                'liters_used' => 100,
                'cubic_meter' => 0.1,
                'device_payload' => ['total_water_liters' => $total],
                'recorded_at' => now()->subMinutes(10 - $index),
            ]);
        }
        Sanctum::actingAs($household);

        $this->getJson('/api/household-billing')
            ->assertOk()
            ->assertJsonPath('data.consumption.liters', 200)
            ->assertJsonPath('data.consumption.cubic_meters', 0.2)
            ->assertJsonPath('data.estimate.amount', 8);
    }

    public function test_household_cannot_receive_a_meter_assigned_to_another_household(): void
    {
        $household = User::factory()->create();
        $otherHousehold = User::factory()->create();
        $this->createMeter($otherHousehold);
        Billing::create(['water_rate' => 40, 'effective_date' => now()->startOfMonth(), 'is_active' => true]);
        Sanctum::actingAs($household);

        $this->getJson('/api/household-billing')->assertNotFound();
    }

    private function createMeter(User $household): Device
    {
        $deviceUser = User::factory()->create([
            'role' => 'device',
            'assigned_user_id' => $household->id,
        ]);

        return Device::create([
            'uuid' => (string) Str::uuid(),
            'asset_number' => 'MWM-'.Str::upper(Str::random(8)),
            'serial_number' => 'SERIAL-'.Str::upper(Str::random(8)),
            'name' => 'Test Household Meter',
            'household_id' => $household->id,
            'auth_user_id' => $deviceUser->id,
            'status' => 'active',
            'last_seen_at' => now(),
            'last_telemetry_at' => now(),
        ]);
    }
}
