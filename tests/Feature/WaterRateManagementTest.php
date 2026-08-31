<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\BillingStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WaterRateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_consumption_alert_form_uses_monthly_percentage_comparison(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('name="percentage_threshold"', false)
            ->assertDontSee('name="threshold_unit"', false)
            ->assertSee('data-admin-consumption-baselines', false)
            ->assertSee('First recorded month = 100%. Every consecutive month is measured against that same baseline');
    }

    public function test_admin_can_save_each_configuration_section_without_losing_the_other_sections(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/billing-settings/rates', [
            'water_rate' => 42.50,
            'fixed_monthly_charge' => 75,
            'subsidy_deduction' => 10,
            'effective_date' => '2026-08-15',
            'currency' => 'PHP',
        ])->assertCreated()
            ->assertJsonPath('data.water_rate', '42.50')
            ->assertJsonPath('data.fixed_monthly_charge', '75.00');

        $this->postJson('/api/billing-settings/consumption-alerts', [
            'percentage_threshold' => 100,
            'notification_behavior' => 'household_admin',
            'consumption_alert_enabled' => true,
        ])->assertCreated()
            ->assertJsonPath('data.consumption_change_threshold_percent', '100.00')
            ->assertJsonPath('data.consumption_alert_unit', 'percentage');

        $this->postJson('/api/billing-settings/leak-settings', [
            'leak_flow_rate_threshold' => 18,
            'leak_continuous_duration_minutes' => 15,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 5,
            'leak_alert_behavior' => 'household_admin',
            'leak_alert_enabled' => true,
        ])->assertCreated()
            ->assertJsonPath('data.leak_continuous_duration_minutes', 15);

        $settings = Billing::query()->latest('id')->firstOrFail();
        $this->assertSame('42.50', $settings->water_rate);
        $this->assertSame('75.00', $settings->fixed_monthly_charge);
        $this->assertSame('100.00', $settings->consumption_change_threshold_percent);
        $this->assertSame('18.00', $settings->leak_flow_rate_threshold);
        $this->assertSame('household_admin', $settings->consumption_alert_behavior);
        $this->assertSame('household_admin', $settings->leak_alert_behavior);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_household_users_cannot_change_water_rate_management(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/billing-settings/rates', [
            'water_rate' => 40,
            'fixed_monthly_charge' => 0,
            'subsidy_deduction' => 0,
            'currency' => 'PHP',
        ])->assertForbidden();

        $this->postJson('/api/billing-settings/consumption-alerts', [
            'percentage_threshold' => 100,
            'notification_behavior' => 'household',
        ])->assertForbidden();

        $this->postJson('/api/billing-settings/leak-settings', [
            'leak_flow_rate_threshold' => 10,
            'leak_continuous_duration_minutes' => 0,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
            'leak_alert_behavior' => 'household',
        ])->assertForbidden();
    }

    public function test_each_section_has_its_own_validation_contract(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/billing-settings/rates', [
            'water_rate' => 40,
            'fixed_monthly_charge' => 0,
            'subsidy_deduction' => 0,
            'currency' => 'peso',
        ])->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['currency']]);

        $this->postJson('/api/billing-settings/consumption-alerts', [
            'percentage_threshold' => 0,
            'notification_behavior' => 'household',
        ])->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['percentage_threshold']]);

        $this->postJson('/api/billing-settings/leak-settings', [
            'leak_flow_rate_threshold' => 10,
            'leak_continuous_duration_minutes' => 2000,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
            'leak_alert_behavior' => 'household',
        ])->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['leak_continuous_duration_minutes']]);
    }

    public function test_fixed_monthly_charge_is_applied_once_to_the_monthly_statement(): void
    {
        $household = User::factory()->create();
        Billing::create([
            'water_rate' => 40,
            'fixed_monthly_charge' => 75,
            'subsidy_deduction' => 0,
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
            'subsidy_deduction' => 0,
            'recorded_at' => now(),
        ]);

        $statement = app(BillingStatementService::class)->generateForUser($household);

        $this->assertSame('115.00', $statement->current_charge);
    }
}
