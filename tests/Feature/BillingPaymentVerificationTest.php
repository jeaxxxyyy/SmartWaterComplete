<?php

namespace Tests\Feature;

use App\Models\BillingStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingPaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_records_walk_in_cash_payment_and_household_sees_paid_status(): void
    {
        $household = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $statement = $this->statement($household);
        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/billing-statements/{$statement->id}/payments", ['amount' => 100, 'payment_method' => 'cash'])->assertCreated()->assertJsonPath('data.status', 'confirmed');
        $this->assertStringStartsWith('OR-', $response->json('data.receipt_number'));
        $this->assertDatabaseHas('billing_statements', ['id' => $statement->id, 'status' => 'paid', 'amount_paid' => 100, 'remaining_balance' => 0]);
    }

    public function test_only_cash_is_accepted_and_void_restores_the_balance(): void
    {
        $household = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $statement = $this->statement($household);
        Sanctum::actingAs($admin);
        $this->postJson("/api/billing-statements/{$statement->id}/payments", ['amount' => 5, 'payment_method' => 'bank_deposit'])->assertUnprocessable();
        $id = $this->postJson("/api/billing-statements/{$statement->id}/payments", ['amount' => 100, 'payment_method' => 'cash'])->json('data.id');
        $this->patchJson("/api/billing-payments/{$id}/void", ['note' => 'Entry recorded in error'])->assertOk();
        $this->assertDatabaseHas('billing_statements', ['id' => $statement->id, 'status' => 'unpaid', 'amount_paid' => 0, 'remaining_balance' => 100]);
    }

    private function statement(User $user): BillingStatement
    {
        return BillingStatement::create(['user_id' => $user->id, 'statement_number' => 'SW-202610-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT), 'billing_month' => '2026-10-01', 'due_date' => '2026-11-15', 'opening_balance' => 0, 'total_liters' => 1000, 'total_cubic_meter' => 1, 'water_rate' => 40, 'fixed_monthly_charge' => 60, 'currency' => 'PHP', 'subsidy_deduction' => 0, 'current_charge' => 100, 'amount_paid' => 0, 'remaining_balance' => 100, 'status' => 'unpaid', 'generated_at' => now()]);
    }
}
