<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Device;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WaterApiTest extends TestCase
{
    use RefreshDatabase;

    private function createDeviceFor(User $household): User
    {
        $device = User::factory()->create([
            'role' => 'device',
            'assigned_user_id' => $household->id,
        ]);
        Device::create([
            'uuid' => (string) Str::uuid(),
            'asset_number' => 'TEST-'.Str::upper(Str::random(10)),
            'serial_number' => 'CORE-'.Str::upper(Str::random(10)),
            'name' => 'Test Meter',
            'household_id' => $household->id,
            'auth_user_id' => $device->id,
            'status' => 'active',
        ]);

        return $device;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_water_readings_create_estimated_billing_and_alerts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-18 23:00:00'));

        $user = User::factory()->create();
        $device = $this->createDeviceFor($user);
        Sanctum::actingAs($device, ['device-upload']);

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
            'reading_id' => 'billing-alert-1',
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
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

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

        $this->getJson('/api/billing-settings?user_id='.$user->id)
            ->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.water_rate', '30.00');
    }

    public function test_leak_status_uses_configured_flow_rate_and_notifies_once_per_event(): void
    {
        $user = User::factory()->create();
        $device = $this->createDeviceFor($user);
        Sanctum::actingAs($device, ['device-upload']);
        Billing::create([
            'water_rate' => 25,
            'subsidy_deduction' => 0,
            'threshold_liters' => 500,
            'leak_flow_rate_threshold' => 8,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
            'is_active' => true,
        ]);

        $sequence = 0;
        $reading = function (float $flow, string $deviceStatus = 'Normal') use (&$sequence): array {
            ++$sequence;

            return [
            'reading_id' => 'leak-test-'.$sequence,
            'flow_rate' => $flow,
            'liters_used' => 1,
            'cubic_meter' => 0.001,
            'leak_detection_status' => $deviceStatus,
            ];
        };

        $this->postJson('/api/water-data', $reading(3, 'Leak Detected'))
            ->assertCreated()
            ->assertJsonPath('data.reading.is_possible_leak', false)
            ->assertJsonPath('data.reading.leak_detection_status', 'Normal');

        $this->postJson('/api/water-data', $reading(8))
            ->assertCreated()
            ->assertJsonPath('data.reading.is_possible_leak', true)
            ->assertJsonPath('data.reading.leak_detection_status', 'Leak Detected');
        $this->postJson('/api/water-data', $reading(9))->assertCreated();
        $this->postJson('/api/water-data', $reading(10))->assertCreated();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => 'possible_leak']);

        // A second event on the same municipal calendar day does not duplicate the notice.
        $this->postJson('/api/water-data', $reading(2))->assertCreated();
        $this->postJson('/api/water-data', $reading(8.5))->assertCreated();
        $this->assertDatabaseCount('notifications', 1);

        // If it remains unresolved, the next day creates exactly one daily reminder.
        Carbon::setTestNow(now()->addDay()->startOfDay()->addHour());
        $this->postJson('/api/water-data', $reading(9))->assertCreated();
        $this->postJson('/api/water-data', $reading(10))->assertCreated();
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'possible_leak',
            'title' => 'Unresolved water leak reminder',
        ]);
        Carbon::setTestNow();
    }

    public function test_notifications_can_be_marked_as_read(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'threshold_exceeded',
            'title' => 'Water usage threshold reached',
            'message' => 'Your water consumption has reached the configured threshold.',
            'metadata' => [],
            'sent_at' => now(),
        ]);

        $this->patchJson('/api/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_user_can_delete_one_or_clear_all_notifications_without_erasing_municipal_records(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $first = Notification::create([
            'user_id' => $user->id, 'type' => 'announcement', 'title' => 'First',
            'message' => 'First notice', 'sent_at' => now(),
        ]);
        $second = Notification::create([
            'user_id' => $user->id, 'type' => 'possible_leak', 'title' => 'Leak',
            'message' => 'Leak notice', 'sent_at' => now(),
        ]);
        $other = Notification::create([
            'user_id' => $otherUser->id, 'type' => 'announcement', 'title' => 'Other',
            'message' => 'Other notice', 'sent_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/notifications/received/'.$other->id)->assertForbidden();
        $this->deleteJson('/api/notifications/received/'.$first->id)->assertOk();
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'data.data');
        $this->deleteJson('/api/notifications/received/all')
            ->assertOk()->assertJsonPath('data.deleted_count', 1);
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(0, 'data.data');

        $this->assertDatabaseHas('notifications', ['id' => $first->id]);
        $this->assertDatabaseHas('notifications', ['id' => $second->id]);
        $this->assertDatabaseHas('notifications', ['id' => $other->id, 'user_deleted_at' => null]);
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
            ->assertJsonCount(0, 'data');
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
            ->assertJsonPath('data.monthly_usage.11.reading_count', 1)
            ->assertJsonPath('data.notifications.total', 1)
            ->assertJsonPath('data.notifications.unread', 1);
    }
}
