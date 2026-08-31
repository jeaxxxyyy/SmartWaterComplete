<?php

namespace Tests\Feature;

use App\Models\BillingStatement;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResidentWebsiteFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_household_can_submit_and_follow_a_service_request_and_admin_can_respond(): void
    {
        $household = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($household);

        $created = $this->postJson('/api/service-requests', [
            'category' => 'billing_dispute',
            'subject' => 'Please review this statement',
            'description' => 'The recorded charge needs clarification.',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'open');
        $requestId = $created->json('data.id');
        $this->assertStringStartsWith('REQ-', $created->json('data.reference_number'));

        Sanctum::actingAs($other);
        $this->getJson("/api/service-requests/{$requestId}")->assertForbidden();

        Sanctum::actingAs($admin);
        $this->patchJson("/api/service-requests/{$requestId}", [
            'status' => 'in_progress',
            'priority' => 'high',
            'assigned_to' => $admin->id,
        ])->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->postJson("/api/service-requests/{$requestId}/messages", [
            'message' => 'We are reviewing the billing calculation.',
        ])->assertCreated()->assertJsonPath('data.status', 'waiting_for_household');

        Sanctum::actingAs($household);
        $this->getJson("/api/service-requests/{$requestId}")
            ->assertOk()
            ->assertJsonPath('data.messages.1.message', 'We are reviewing the billing calculation.');
    }

    public function test_household_can_save_notification_language_low_bandwidth_and_consumption_goal_preferences(): void
    {
        Carbon::setTestNow('2026-08-15 12:00:00');
        $household = User::factory()->create();
        WaterReading::create([
            'user_id' => $household->id,
            'flow_rate' => 2,
            'liters_used' => 1500,
            'cubic_meter' => 1.5,
            'estimated_bill' => 60,
            'recorded_at' => now(),
        ]);
        Sanctum::actingAs($household);

        $this->putJson('/api/notification-preferences', [
            'announcements' => true,
            'billing_updates' => true,
            'consumption_alerts' => false,
            'leak_alerts' => true,
            'service_request_updates' => true,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '06:00',
            'preferred_locale' => 'fil',
            'low_bandwidth_mode' => true,
        ])->assertOk()
            ->assertJsonPath('data.preferred_locale', 'fil')
            ->assertJsonPath('data.low_bandwidth_mode', true)
            ->assertJsonPath('data.consumption_alerts', false);

        $this->putJson('/api/consumption-goal', [
            'month' => '2026-08',
            'target_liters' => 2500,
        ])->assertOk()
            ->assertJsonPath('data.used_liters', 1500)
            ->assertJsonPath('data.progress_percent', 60)
            ->assertJsonPath('data.status', 'at_risk');

        $this->assertDatabaseHas('users', ['id' => $household->id, 'preferred_locale' => 'fil', 'low_bandwidth_mode' => true]);
        $this->assertDatabaseHas('notification_preferences', ['user_id' => $household->id, 'consumption_alerts' => false]);
    }

    public function test_admin_records_offline_payments_and_household_downloads_statement_and_receipt(): void
    {
        Carbon::setTestNow('2026-08-31 10:00:00');
        $household = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $statement = $this->statement($household, ['remaining_balance' => 100, 'current_charge' => 100]);
        Sanctum::actingAs($admin);

        $payment = $this->postJson("/api/billing-statements/{$statement->id}/payments", [
            'amount' => 40,
            'payment_method' => 'cash',
            'received_at' => '2026-08-31 09:00:00',
        ])->assertCreated()
            ->assertJsonPath('data.amount', '40.00');
        $paymentId = $payment->json('data.id');
        $this->assertStringStartsWith('OR-', $payment->json('data.receipt_number'));
        $this->assertDatabaseHas('billing_statements', ['id' => $statement->id, 'status' => 'partial', 'amount_paid' => 40, 'remaining_balance' => 60]);

        Sanctum::actingAs($household);
        $this->get("/api/billing-statements/{$statement->id}/document")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get("/api/billing-payments/{$paymentId}/receipt")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_action_center_combines_open_requests_and_overdue_billing(): void
    {
        Carbon::setTestNow('2026-08-31 10:00:00');
        $household = User::factory()->create();
        $admin = User::factory()->admin()->create();
        ServiceRequest::create([
            'reference_number' => 'REQ-TEST-0001',
            'user_id' => $household->id,
            'category' => 'general_inquiry',
            'subject' => 'Open question',
            'description' => 'Please respond.',
            'priority' => 'normal',
            'status' => 'open',
        ]);
        $this->statement($household, ['due_date' => '2026-08-15', 'remaining_balance' => 100]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/action-center')
            ->assertOk()
            ->assertJsonPath('data.summary.open_service_requests', 1)
            ->assertJsonPath('data.summary.overdue_statements', 1);
    }

    public function test_pages_expose_installable_and_accessible_workflows(): void
    {
        $this->get('/household')
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('data-household-view="requests"', false)
            ->assertSee('data-notification-preferences-form', false)
            ->assertSee('data-install-app', false);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('data-admin-view="service-requests"', false)
            ->assertSee('data-action-center-list', false)
            ->assertSee('data-payment-form', false);
    }

    private function statement(User $household, array $overrides = []): BillingStatement
    {
        return BillingStatement::create([
            'user_id' => $household->id,
            'statement_number' => 'SW-202608-'.str_pad((string) $household->id, 6, '0', STR_PAD_LEFT),
            'billing_month' => '2026-08-01',
            'due_date' => '2026-09-15',
            'opening_balance' => 0,
            'total_liters' => 1000,
            'total_cubic_meter' => 1,
            'water_rate' => 40,
            'fixed_monthly_charge' => 60,
            'currency' => 'PHP',
            'subsidy_deduction' => 0,
            'current_charge' => 100,
            'amount_paid' => 0,
            'remaining_balance' => 100,
            'status' => 'unpaid',
            'generated_at' => now(),
            ...$overrides,
        ]);
    }
}
