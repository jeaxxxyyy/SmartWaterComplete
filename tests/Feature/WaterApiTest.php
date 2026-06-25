<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WaterApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_water_readings_create_estimated_billing_and_alerts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-18 23:00:00'));

        $user = User::factory()->create();

        Billing::create([
            'water_rate' => 25,
            'subsidy_deduction' => 5,
            'threshold_liters' => 100,
            'leak_flow_rate_threshold' => 2,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/water-data', [
            'user_id' => $user->id,
            'flow_rate' => 3.5,
            'liters_used' => 150,
            'cubic_meter' => 0.15,
            'recorded_at' => '2026-06-18 23:00:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.reading.estimated_bill', '0.00')
            ->assertJsonPath('data.reading.is_threshold_exceeded', true)
            ->assertJsonPath('data.reading.is_possible_leak', true);

        $this->assertDatabaseHas('water_readings', [
            'user_id' => $user->id,
            'flow_rate' => 3.5,
            'liters_used' => 150,
            'cubic_meter' => 0.15,
            'estimated_bill' => 0,
            'is_threshold_exceeded' => true,
            'is_possible_leak' => true,
        ]);

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'type' => 'threshold_exceeded',
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => 'possible_leak',
            'user_id' => $user->id,
        ]);

    }

    public function test_billing_settings_can_be_saved_and_retrieved(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/billing-settings', [
            'user_id' => $user->id,
            'water_rate' => 30,
            'subsidy_deduction' => 10,
            'threshold_liters' => 200,
            'leak_flow_rate_threshold' => 4,
            'quiet_hours_start' => 21,
            'quiet_hours_end' => 5,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.water_rate', '30.00');

        $this->getJson('/api/billing-settings?user_id=' . $user->id)
            ->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.water_rate', '30.00');
    }

    public function test_notifications_can_be_marked_as_read(): void
    {
        $user = User::factory()->create();

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'threshold_exceeded',
            'title' => 'Water usage threshold reached',
            'message' => 'Your water consumption has reached the configured threshold.',
            'metadata' => [],
            'sent_at' => now(),
        ]);

        $this->patchJson('/api/notifications/' . $notification->id . '/read')
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_readings_are_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        WaterReading::create([
            'user_id' => $user->id,
            'flow_rate' => 1.5,
            'liters_used' => 50,
            'cubic_meter' => 0.05,
            'estimated_bill' => 0,
            'rate_per_cubic_meter' => 20,
            'subsidy_deduction' => 5,
            'threshold_liters' => 100,
            'is_threshold_exceeded' => false,
            'is_possible_leak' => false,
            'recorded_at' => now(),
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/readings')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_authenticated_user_can_view_dashboard_summary(): void
    {
        $user = User::factory()->create();

        Billing::create([
            'user_id' => null,
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
            'flow_rate' => 2.1,
            'liters_used' => 40,
            'cubic_meter' => 0.04,
            'estimated_bill' => 0,
            'rate_per_cubic_meter' => 20,
            'subsidy_deduction' => 5,
            'threshold_liters' => 100,
            'is_threshold_exceeded' => false,
            'is_possible_leak' => false,
            'recorded_at' => now(),
        ]);

        Notification::create([
            'user_id' => $user->id,
            'type' => 'threshold_exceeded',
            'title' => 'Threshold',
            'message' => 'Reached threshold',
            'metadata' => [],
            'is_read' => false,
            'sent_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/summary')
            ->assertOk()
            ->assertJsonPath('data.current_month.reading_count', 1)
            ->assertJsonPath('data.notifications.total', 1)
            ->assertJsonPath('data.notifications.unread', 1);
    }
}
