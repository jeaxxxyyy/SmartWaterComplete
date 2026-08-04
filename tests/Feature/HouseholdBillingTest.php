<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\BillingStatement;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_household_receives_a_real_current_estimate_with_a_complete_breakdown(): void
    {
        $household = User::factory()->create(['name' => 'Billing Household']);
        $otherHousehold = User::factory()->create(['name' => 'Other Household']);
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
}
