<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminLeakMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_affected_household_and_current_resolution_state(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create([
            'role' => 'user',
            'account_number' => 'ACC-USER-0044',
            'address' => 'Barangay Test',
        ]);
        $leakReading = WaterReading::create([
            'user_id' => $household->id,
            'flow_rate' => 9,
            'liters_used' => 1,
            'cubic_meter' => 0.001,
            'estimated_bill' => 0,
            'rate_per_cubic_meter' => 40,
            'subsidy_deduction' => 0,
            'is_threshold_exceeded' => false,
            'is_possible_leak' => true,
            'leak_detection_status' => 'Leak Detected',
            'recorded_at' => now(),
        ]);
        Notification::create([
            'user_id' => $household->id,
            'water_reading_id' => $leakReading->id,
            'type' => 'possible_leak',
            'title' => 'Possible water leak detected',
            'message' => 'Leak notice',
            'sent_at' => now(),
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/reports/leaks')
            ->assertOk()
            ->assertJsonPath('data.0.user.name', $household->name)
            ->assertJsonPath('data.0.user.account_number', 'ACC-USER-0044')
            ->assertJsonPath('data.0.status', 'unresolved')
            ->assertJsonPath('data.0.flow_rate', 9);

        WaterReading::create([
            'user_id' => $household->id,
            'flow_rate' => 1,
            'liters_used' => 1,
            'cubic_meter' => 0.001,
            'estimated_bill' => 0,
            'rate_per_cubic_meter' => 40,
            'subsidy_deduction' => 0,
            'is_threshold_exceeded' => false,
            'is_possible_leak' => false,
            'leak_detection_status' => 'Normal',
            'recorded_at' => now()->addSecond(),
        ]);
        $this->getJson('/api/reports/leaks')->assertJsonPath('data.0.status', 'resolved');
    }
}
