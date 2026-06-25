<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\BillingStatement;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingStatementAndExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_billing_statements_and_export_data(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        Billing::create([
            'water_rate' => 20,
            'subsidy_deduction' => 5,
            'threshold_liters' => 100,
            'leak_flow_rate_threshold' => 2,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
            'is_active' => true,
        ]);

        WaterReading::create([
            'user_id' => $user->id,
            'flow_rate' => 1.8,
            'liters_used' => 120,
            'cubic_meter' => 0.12,
            'estimated_bill' => 0,
            'rate_per_cubic_meter' => 20,
            'subsidy_deduction' => 5,
            'threshold_liters' => 100,
            'is_threshold_exceeded' => true,
            'is_possible_leak' => false,
            'recorded_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $generateResponse = $this->postJson('/api/billing-statements/generate', [
            'user_id' => $user->id,
            'month' => now()->format('Y-m'),
        ]);

        $generateResponse->assertCreated()
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('billing_statements', [
            'user_id' => $user->id,
            'status' => 'unpaid',
        ]);

        $this->get('/api/exports/readings')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->get('/api/exports/billing-statements')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->get('/api/audit-logs')
            ->assertOk()
            ->assertJsonFragment(['action' => 'billing_statement_generated']);

        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_user_can_view_own_billing_statements_only(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        BillingStatement::create([
            'user_id' => $user->id,
            'billing_month' => now()->startOfMonth()->toDateString(),
            'opening_balance' => 0,
            'total_liters' => 100,
            'total_cubic_meter' => 0.1,
            'water_rate' => 20,
            'subsidy_deduction' => 5,
            'current_charge' => 0,
            'amount_paid' => 0,
            'remaining_balance' => 0,
            'status' => 'paid',
            'generated_at' => now(),
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/billing-statements')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
