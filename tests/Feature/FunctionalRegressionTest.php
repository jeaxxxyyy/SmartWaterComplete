<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\BillingStatement;
use App\Models\ConsumptionGoal;
use App\Models\NotificationPreference;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\BillingStatementService;
use App\Services\WaterConsumptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FunctionalRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_goal_month_is_preserved_on_the_thirty_first_and_after_reload(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 31)->setTime(12, 0));
        Sanctum::actingAs($household = User::factory()->create());

        $this->putJson('/api/consumption-goal', ['month' => '2026-02', 'target_liters' => 2500])
            ->assertOk()->assertJsonPath('data.month', '2026-02');
        $this->getJson('/api/consumption-goal?month=2026-02')
            ->assertOk()->assertJsonPath('data.target_liters', 2500);
        $this->putJson('/api/consumption-goal', ['month' => '2026-02', 'target_liters' => 3000])
            ->assertOk()->assertJsonPath('data.target_liters', 3000);
        $this->assertSame('2026-02-01', ConsumptionGoal::where('user_id', $household->id)->firstOrFail()->month->toDateString());
        $this->assertDatabaseCount('consumption_goals', 1);
    }

    public function test_invalid_goal_month_returns_validation_errors(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (['invalid', '2026-13', '2026-02-01'] as $month) {
            $this->getJson('/api/consumption-goal?month='.$month)
                ->assertUnprocessable()->assertJsonValidationErrors('month');
        }
    }

    public function test_generating_a_shorter_month_on_the_thirty_first_uses_the_requested_month(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 31)->setTime(12, 0));
        $household = User::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->postJson('/api/billing-statements/generate', ['user_id' => $household->id, 'month' => '2026-02'])
            ->assertCreated();
        $statement = BillingStatement::findOrFail($response->json('data.id'));
        $this->assertSame('2026-02-01', $statement->billing_month->toDateString());
        $this->assertSame('2026-03-15', $statement->due_date->toDateString());
    }

    public function test_regeneration_preserves_the_existing_statement_and_custom_due_date(): void
    {
        $household = User::factory()->create();
        $service = app(BillingStatementService::class);
        $month = now()->startOfMonth();
        $statement = $service->generateForUser($household, $month);
        $dueDate = $month->copy()->addMonthsNoOverflow(2)->startOfMonth();
        $statement->update(['due_date' => $dueDate]);

        $regenerated = $service->generateForUser($household, $month);

        $this->assertSame($statement->id, $regenerated->id);
        $this->assertSame($statement->statement_number, $regenerated->statement_number);
        $this->assertSame($dueDate->toDateString(), $regenerated->due_date->toDateString());
        $this->assertDatabaseCount('billing_statements', 1);
    }

    public function test_finalizing_current_usage_uses_the_active_rate_and_ignores_future_rates(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(12, 0));
        $household = User::factory()->create();
        Billing::create(['water_rate' => 25, 'fixed_monthly_charge' => 50, 'effective_date' => '2026-09-01', 'is_active' => true]);
        Billing::create(['water_rate' => 40, 'fixed_monthly_charge' => 100, 'effective_date' => '2026-10-20', 'is_active' => true]);
        Billing::create(['water_rate' => 100, 'fixed_monthly_charge' => 1000, 'effective_date' => '2026-11-01', 'is_active' => true]);
        WaterReading::create(['user_id' => $household->id, 'flow_rate' => 2, 'liters_used' => 1000, 'cubic_meter' => 1, 'recorded_at' => now()]);

        $statement = app(BillingStatementService::class)->generateForUser($household);

        $this->assertSame('40.00', $statement->water_rate);
        $this->assertSame('140.00', $statement->current_charge);
    }

    public function test_historical_estimates_use_the_rate_from_the_selected_period(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(12, 0));
        $household = User::factory()->create();
        Billing::create(['water_rate' => 25, 'effective_date' => '2026-09-01', 'is_active' => true]);
        Billing::create(['water_rate' => 40, 'effective_date' => '2026-10-01', 'is_active' => true]);
        WaterReading::create(['user_id' => $household->id, 'flow_rate' => 2, 'liters_used' => 1000, 'cubic_meter' => 1, 'recorded_at' => '2026-09-15 12:00:00']);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/billing-management?bill_type=estimated&from=2026-09-01&to=2026-09-30')
            ->assertOk()->assertJsonPath('data.data.0.water_rate', 25)
            ->assertJsonPath('data.data.0.current_charge', 25);
    }

    public function test_voiding_one_payment_retains_the_other_payment_and_partial_status(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(12, 0));
        $household = User::factory()->create();
        Billing::create(['water_rate' => 40, 'fixed_monthly_charge' => 100, 'is_active' => true]);
        $statement = app(BillingStatementService::class)->generateForUser($household);
        Sanctum::actingAs(User::factory()->admin()->create());
        $endpoint = '/api/billing-statements/'.$statement->id.'/payments';
        $this->postJson($endpoint, ['amount' => 40, 'payment_method' => 'cash'])->assertCreated();
        $second = $this->postJson($endpoint, ['amount' => 60, 'payment_method' => 'cash'])->assertCreated();

        $this->patchJson('/api/billing-payments/'.$second->json('data.id').'/void', ['note' => 'Duplicate cash entry'])
            ->assertOk();

        $this->assertDatabaseHas('billing_statements', ['id' => $statement->id, 'amount_paid' => 40, 'remaining_balance' => 60, 'status' => 'partial']);
    }

    public function test_mysql_time_preferences_can_be_reloaded_and_saved_again(): void
    {
        Sanctum::actingAs($household = User::factory()->create());
        // MySQL TIME columns return seconds even when the input was HH:MM.
        NotificationPreference::create(['user_id' => $household->id, 'quiet_hours_start' => '22:00:00', 'quiet_hours_end' => '06:00:00']);

        $preferences = $this->getJson('/api/notification-preferences')->assertOk()
            ->assertJsonPath('data.quiet_hours_start', '22:00')
            ->assertJsonPath('data.quiet_hours_end', '06:00')->json('data');
        $this->putJson('/api/notification-preferences', $preferences)->assertOk();
        $this->getJson('/api/notification-preferences')->assertOk()->assertJsonPath('data.quiet_hours_start', '22:00');
    }

    public function test_quiet_hours_require_both_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $preferences = $this->getJson('/api/notification-preferences')->assertOk()->json('data');
        $preferences['quiet_hours_start'] = '22:00';

        $this->putJson('/api/notification-preferences', $preferences)
            ->assertUnprocessable()->assertJsonValidationErrors('quiet_hours_end');
    }

    public function test_new_preferences_include_database_defaults_on_the_first_load(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/notification-preferences')->assertOk()
            ->assertJsonPath('data.announcements', true)
            ->assertJsonPath('data.billing_updates', true)
            ->assertJsonPath('data.leak_alerts', true);
    }

    public function test_rate_effective_date_survives_a_json_round_trip_in_manila(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Sanctum::actingAs(User::factory()->admin()->create());
        $settings = ['water_rate' => 40, 'fixed_monthly_charge' => 0, 'subsidy_deduction' => 0, 'currency' => 'PHP', 'effective_date' => '2026-10-23'];

        $this->postJson('/api/billing-settings/rates', $settings)->assertCreated()
            ->assertJsonPath('data.effective_date', '2026-10-23');
    }

    public function test_immediate_alert_changes_do_not_rewrite_historical_settings(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(12, 0));
        Billing::create(['water_rate' => 25, 'effective_date' => '2026-09-01', 'leak_flow_rate_threshold' => 30, 'is_active' => true]);
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson('/api/billing-settings/leak-settings', [
            'leak_flow_rate_threshold' => 8,
            'leak_continuous_duration_minutes' => 0,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
            'leak_alert_behavior' => 'household',
            'leak_alert_enabled' => true,
        ])->assertCreated();

        $service = app(WaterConsumptionService::class);
        $this->assertSame('8.00', $service->resolveSettingsForUser(null)->leak_flow_rate_threshold);
        $this->assertSame('30.00', $service->resolveSettingsForUser(null, now()->subMonth())->leak_flow_rate_threshold);
    }

    public function test_staff_reply_reopens_resolved_request_without_a_stale_resolution_date(): void
    {
        $household = User::factory()->create();
        $item = ServiceRequest::create([
            'user_id' => $household->id, 'reference_number' => 'REQ-REGRESSION',
            'category' => 'general_inquiry', 'subject' => 'Meter question', 'description' => 'Please check.',
            'priority' => 'normal', 'status' => 'resolved', 'resolved_at' => now()->subDay(),
        ]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/service-requests/'.$item->id.'/messages', ['message' => 'We need more details.'])
            ->assertCreated()->assertJsonPath('data.status', 'waiting_for_household')
            ->assertJsonPath('data.resolved_at', null);
    }
}
