<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiveConsumptionReadingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_household_live_readings_are_scoped_ordered_and_precise(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 15:20:20', config('app.timezone')));
        $household = User::factory()->create();
        $otherHousehold = User::factory()->create();

        foreach (range(0, 4) as $index) {
            WaterReading::create([
                'user_id' => $household->id,
                'flow_rate' => 0.08 + ($index * 0.01),
                'liters_used' => 0.004 + ($index * 0.0005),
                'cubic_meter' => 0.000004 + ($index * 0.0000005),
                'estimated_bill' => 0,
                'rate_per_cubic_meter' => 20,
                'subsidy_deduction' => 0,
                'recorded_at' => now()->subSeconds((4 - $index) * 3),
            ]);
        }

        WaterReading::create([
            'user_id' => $otherHousehold->id,
            'flow_rate' => 9,
            'liters_used' => 99,
            'cubic_meter' => 0.099,
            'estimated_bill' => 0,
            'rate_per_cubic_meter' => 20,
            'subsidy_deduction' => 0,
            'recorded_at' => now(),
        ]);

        Sanctum::actingAs($household);

        $this->getJson('/api/live-readings?limit=30')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'readings')
            ->assertJsonPath('readings.0.liters_used', 0.004)
            ->assertJsonPath('readings.4.liters_used', 0.006)
            ->assertJsonPath('latest.flow_rate', 0.12)
            ->assertJsonPath('status.value', 'live');
    }

    public function test_admin_can_select_a_household_for_live_readings(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create();
        WaterReading::create([
            'user_id' => $household->id,
            'flow_rate' => 1.25,
            'liters_used' => 0.125,
            'cubic_meter' => 0.000125,
            'estimated_bill' => 0,
            'rate_per_cubic_meter' => 20,
            'subsidy_deduction' => 0,
            'recorded_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/live-readings?user_id='.$household->id)
            ->assertOk()
            ->assertJsonCount(1, 'readings')
            ->assertJsonPath('readings.0.flow_rate', 1.25);
    }
}
