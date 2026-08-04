<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumptionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_returns_aggregated_totals_instead_of_raw_flow_readings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        foreach ([1.5, 2.5] as $liters) {
            WaterReading::create([
                'user_id' => $user->id, 'flow_rate' => 3, 'liters_used' => $liters,
                'cubic_meter' => $liters / 1000, 'estimated_bill' => 2,
                'rate_per_cubic_meter' => 40, 'subsidy_deduction' => 0,
                'is_threshold_exceeded' => false, 'is_possible_leak' => false,
                'recorded_at' => now(),
            ]);
        }

        $this->getJson('/api/consumption-history')->assertOk()
            ->assertJsonPath('data.daily.0.total_liters', 4)
            ->assertJsonPath('data.daily.0.estimated_bill', 4)
            ->assertJsonPath('data.summary.estimated_bill', 4)
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonMissingPath('data.daily.0.flow_rate');
    }

    public function test_selected_history_period_controls_aggregation_and_pagination_metadata(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach ([now()->startOfWeek(), now()->subWeek()->startOfWeek()] as $recordedAt) {
            WaterReading::create([
                'user_id' => $user->id,
                'flow_rate' => 3,
                'liters_used' => 5,
                'cubic_meter' => 0.005,
                'estimated_bill' => 1,
                'rate_per_cubic_meter' => 40,
                'subsidy_deduction' => 0,
                'is_threshold_exceeded' => false,
                'is_possible_leak' => false,
                'recorded_at' => $recordedAt,
            ]);
        }

        $this->getJson('/api/consumption-history?period=weekly&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.weekly')
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.meta.last_page', 2)
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.chart.0.value', 0.005);
    }
}
