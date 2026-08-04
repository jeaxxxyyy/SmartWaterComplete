<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\BillingStatement;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_billing_management_returns_real_estimated_and_finalized_household_records(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create([
            'name' => 'Billing Household',
            'email' => 'billing-household@example.com',
            'account_number' => 'ACC-BILLING-001',
        ]);
        $otherAdmin = User::factory()->admin()->create(['email' => 'billing-admin-two@example.com']);
        Billing::create([
            'water_rate' => 40,
            'fixed_monthly_charge' => 75,
            'subsidy_deduction' => 5,
            'threshold_liters' => 10000,
            'leak_flow_rate_threshold' => 20,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
            'is_active' => true,
        ]);
        WaterReading::create([
            'user_id' => $household->id,
            'flow_rate' => 2,
            'liters_used' => 1000,
            'cubic_meter' => 1,
            'estimated_bill' => 40,
            'rate_per_cubic_meter' => 40,
            'subsidy_deduction' => 5,
            'threshold_liters' => 10000,
            'recorded_at' => now(),
        ]);
        $statement = BillingStatement::create([
            'user_id' => $household->id,
            'billing_month' => now()->startOfMonth()->toDateString(),
            'opening_balance' => 0,
            'total_liters' => 1000,
            'total_cubic_meter' => 1,
            'water_rate' => 40,
            'fixed_monthly_charge' => 75,
            'currency' => 'PHP',
            'subsidy_deduction' => 5,
            'current_charge' => 110,
            'amount_paid' => 0,
            'remaining_balance' => 110,
            'status' => 'unpaid',
            'generated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/billing-management?per_page=50');

        $response->assertOk()
            ->assertJsonPath('meta.finalized_count', 1)
            ->assertJsonPath('meta.estimated_count', 1)
            ->assertJsonPath('meta.total_records', 2)
            ->assertJsonFragment(['record_type' => 'finalized', 'status' => 'unpaid'])
            ->assertJsonFragment(['record_type' => 'estimated', 'status' => 'estimated'])
            ->assertJsonFragment(['email' => $household->email])
            ->assertJsonMissing(['email' => $otherAdmin->email]);

        $this->getJson('/api/billing-management?bill_type=finalized&search=ACC-BILLING-001')
            ->assertOk()
            ->assertJsonPath('meta.total_records', 1)
            ->assertJsonPath('data.data.0.id', $statement->id);

        $this->getJson('/api/billing-management/'.$statement->id)
            ->assertOk()
            ->assertJsonPath('data.record_type', 'finalized')
            ->assertJsonPath('data.calculation.fixed_monthly_charge', 75)
            ->assertJsonPath('data.calculation.current_charge', 110)
            ->assertJsonCount(1, 'data.readings');
    }

    public function test_household_users_cannot_access_admin_billing_management(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/billing-management')->assertForbidden();
    }

    public function test_billing_management_supports_a_real_empty_state(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/billing-management?from=2026-01-01&to=2026-01-31')
            ->assertOk()
            ->assertJsonPath('meta.total_records', 0)
            ->assertJsonCount(0, 'data.data');
    }
}
