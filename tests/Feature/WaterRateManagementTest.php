<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\BillingStatementService;
use App\Services\WaterConsumptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WaterRateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_leak_settings_survive_reload_and_apply_to_flow_before_and_after_scheduled_rates(): void
    {
        $this->travelTo(now()->startOfDay());
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create();
        Billing::create(['water_rate' => 25, 'effective_date' => now()->subMonth(), 'leak_flow_rate_threshold' => 30, 'is_active' => true]);
        foreach ([1 => 40, 2 => 50] as $months => $rate) {
            Billing::create(['water_rate' => $rate, 'effective_date' => now()->addMonths($months), 'leak_flow_rate_threshold' => 30, 'is_active' => true]);
        }
        Sanctum::actingAs($admin);
        $payload = [
            'leak_flow_rate_threshold' => 8.5,
            'leak_continuous_duration_minutes' => 0,
            'quiet_hours_start' => 21,
            'quiet_hours_end' => 6,
            'leak_alert_behavior' => 'household_admin',
            'leak_alert_enabled' => true,
        ];
        $this->postJson('/api/billing-settings/leak-settings', $payload)->assertCreated();
        $this->getJson('/api/billing-settings')->assertOk()
            ->assertJsonPath('data.water_rate', '25.00')
            ->assertJsonPath('data.leak_flow_rate_threshold', '8.50')
            ->assertJsonPath('data.quiet_hours_start', 21)
            ->assertJsonPath('data.quiet_hours_end', 6)
            ->assertJsonPath('data.leak_alert_behavior', 'household_admin')
            ->assertJsonPath('data.leak_alert_enabled', true)
            ->assertJsonPath('scheduled.water_rate', '40.00');

        Sanctum::actingAs($household);
        $this->getJson('/api/billing-settings')->assertOk()->assertJsonPath('data.leak_flow_rate_threshold', '8.50');
        $service = app(WaterConsumptionService::class);
        $normal = $service->storeReading(['user_id' => $household->id, 'flow_rate' => 8.49, 'liters_used' => 1]);
        $leak = $service->storeReading(['user_id' => $household->id, 'flow_rate' => 8.5, 'liters_used' => 1]);
        $this->assertFalse($normal['reading']->is_possible_leak);
        $this->assertTrue($leak['reading']->is_possible_leak);
        $this->assertDatabaseHas('notifications', ['user_id' => $household->id, 'type' => 'possible_leak']);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'possible_leak']);
        foreach ([1 => '40.00', 2 => '50.00'] as $months => $rate) {
            $settings = $service->resolveSettingsForUser($household->id, now()->addMonths($months));
            $this->assertSame($rate, $settings->water_rate);
            $this->assertSame('8.50', $settings->leak_flow_rate_threshold);
        }
    }

    public function test_leak_duration_does_not_reuse_a_high_reading_before_flow_stopped(): void
    {
        $household = User::factory()->create();
        $settings = Billing::create(['water_rate' => 25, 'leak_flow_rate_threshold' => 8, 'leak_continuous_duration_minutes' => 10, 'is_active' => true]);
        $service = app(WaterConsumptionService::class);
        foreach ([30 => 9, 20 => 0, 5 => 9] as $minutes => $flow) {
            $service->storeReading(['user_id' => $household->id, 'flow_rate' => $flow, 'liters_used' => 1, 'recorded_at' => now()->subMinutes($minutes)]);
        }
        $this->assertFalse($service->detectPossibleLeak(9, $settings, now(), $household->id));
        $this->assertTrue($service->detectPossibleLeak(9, $settings, now()->addMinutes(5), $household->id));
    }

    public function test_repeated_leak_saves_preserve_zero_and_false_values_and_other_sections(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Billing::create(['water_rate' => 25, 'effective_date' => now()->subDay(), 'is_active' => true]);
        // A more recently inserted but older rate must not supply the merge date.
        Billing::create(['water_rate' => 10, 'effective_date' => now()->subMonth(), 'is_active' => true]);
        $payload = [
            'leak_flow_rate_threshold' => 12.25,
            'leak_continuous_duration_minutes' => 15,
            'quiet_hours_start' => 0,
            'quiet_hours_end' => 8,
            'leak_alert_behavior' => 'household',
            'leak_alert_enabled' => true,
        ];
        $this->postJson('/api/billing-settings/leak-settings', $payload)->assertCreated();
        $this->getJson('/api/billing-settings')->assertOk()
            ->assertJsonPath('data.water_rate', '25.00')
            ->assertJsonPath('data.leak_flow_rate_threshold', '12.25')
            ->assertJsonPath('data.leak_continuous_duration_minutes', 15)
            ->assertJsonPath('data.quiet_hours_start', 0);
        $payload = array_merge($payload, [
            'leak_flow_rate_threshold' => 0,
            'leak_continuous_duration_minutes' => 0,
            'leak_alert_enabled' => false,
            'leak_alert_behavior' => 'disabled',
        ]);
        $this->postJson('/api/billing-settings/leak-settings', $payload)->assertCreated();
        $this->postJson('/api/billing-settings/consumption-alerts', ['notification_behavior' => 'household_admin'])->assertCreated();
        $this->postJson('/api/billing-settings/rates', [
            'water_rate' => 45, 'fixed_monthly_charge' => 5, 'subsidy_deduction' => 0,
            'currency' => 'PHP', 'effective_date' => now()->addMonth()->toDateString(),
        ])->assertCreated();
        $response = $this->getJson('/api/billing-settings')->assertOk();
        foreach (['data', 'scheduled'] as $key) {
            $response->assertJsonPath($key.'.leak_flow_rate_threshold', '0.00')
                ->assertJsonPath($key.'.leak_continuous_duration_minutes', 0)
                ->assertJsonPath($key.'.quiet_hours_start', 0)
                ->assertJsonPath($key.'.leak_alert_enabled', false)
                ->assertJsonPath($key.'.leak_alert_behavior', 'disabled');
        }
        $response->assertJsonPath('data.water_rate', '25.00')->assertJsonPath('scheduled.water_rate', '45.00');
    }

    public function test_admin_consumption_alert_form_includes_monthly_comparison_graph(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertDontSee('name="threshold_liters"', false)
            ->assertDontSee('name="percentage_threshold"', false)
            ->assertSee('data-admin-consumption-baselines', false)
            ->assertSee('data-consumption-alert-comparison', false)
            ->assertSee('data-consumption-alert-baseline-chart', false)
            ->assertSee('July = 100%')
            ->assertSee('August, September, October, and later months are measured against it');
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
            'notification_behavior' => 'household_admin',
            'consumption_alert_enabled' => true,
        ])->assertCreated()
            ->assertJsonPath('data.threshold_liters', '0.00')
            ->assertJsonPath('data.consumption_change_threshold_percent', '0.00')
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
        $this->assertSame('0.00', $settings->threshold_liters);
        $this->assertSame('0.00', $settings->consumption_change_threshold_percent);
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
            'notification_behavior' => 'invalid',
        ])->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['notification_behavior']]);

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
