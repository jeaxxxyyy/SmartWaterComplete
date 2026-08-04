<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Device;
use App\Models\LeakAlert;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeakAlertMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_review_update_and_follow_up_on_a_real_leak_alert(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create(['role' => 'user']);
        $deviceIdentity = User::factory()->create([
            'role' => 'device',
            'assigned_user_id' => $household->id,
        ]);
        $device = Device::create([
            'uuid' => (string) Str::uuid(),
            'asset_number' => 'LEAK-METER-001',
            'serial_number' => 'CORES3-LEAK-001',
            'name' => 'CoreS3 Leak Meter',
            'household_id' => $household->id,
            'auth_user_id' => $deviceIdentity->id,
            'status' => 'active',
        ]);
        Billing::create([
            'user_id' => null,
            'water_rate' => 25,
            'subsidy_deduction' => 0,
            'threshold_liters' => 500,
            'leak_flow_rate_threshold' => 8,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
            'is_active' => true,
        ]);

        Sanctum::actingAs($deviceIdentity, ['device-upload']);
        $this->postJson('/api/water-data', [
            'reading_id' => 'leak-monitoring-001',
            'flow_rate' => 9.25,
            'liters_used' => 1.5,
            'cubic_meter' => 0.0015,
            'firmware_version' => '1.1.0',
        ])->assertCreated();

        $alert = LeakAlert::query()->where('user_id', $household->id)->firstOrFail();
        $this->assertSame($device->id, $alert->latestReading->device_id);

        Sanctum::actingAs($admin);
        $this->getJson('/api/leak-alerts?per_page=100')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $alert->id)
            ->assertJsonPath('data.data.0.status', LeakAlert::STATUS_DETECTED)
            ->assertJsonPath('data.data.0.user.id', $household->id)
            ->assertJsonPath('data.data.0.latest_reading.flow_rate', '9.25')
            ->assertJsonPath('data.data.0.device.asset_number', 'LEAK-METER-001')
            ->assertJsonStructure(['data' => ['data' => [[
                'detected_at',
                'last_detected_at',
                'notified_at',
                'notification',
            ]]]]);

        $this->patchJson('/api/leak-alerts/'.$alert->id, [
            'status' => LeakAlert::STATUS_INVESTIGATING,
            'notes' => 'Contacted the household and requested a plumbing inspection.',
        ])->assertOk()
            ->assertJsonPath('data.status', LeakAlert::STATUS_INVESTIGATING)
            ->assertJsonPath('data.notes', 'Contacted the household and requested a plumbing inspection.');

        $this->postJson('/api/leak-alerts/'.$alert->id.'/notify', [
            'title' => 'Leak investigation follow-up',
            'message' => 'Please confirm whether the flow continues after checking the plumbing.',
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $household->id,
            'type' => 'announcement',
            'title' => 'Leak investigation follow-up',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'leak_alert_updated',
            'auditable_id' => $alert->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'leak_alert_notified',
            'auditable_id' => $alert->id,
        ]);
    }

    public function test_admin_can_resolve_or_mark_a_false_alarm_and_reopen_the_workflow(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create(['role' => 'user']);
        $alert = LeakAlert::create([
            'user_id' => $household->id,
            'status' => LeakAlert::STATUS_DETECTED,
            'detected_at' => now(),
            'last_detected_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        $this->patchJson('/api/leak-alerts/'.$alert->id, [
            'status' => LeakAlert::STATUS_RESOLVED,
            'notes' => 'Resolved after household confirmed a faulty outdoor valve.',
        ])->assertOk();
        $this->assertNotNull($alert->fresh()->resolved_at);

        $this->patchJson('/api/leak-alerts/'.$alert->id, [
            'status' => LeakAlert::STATUS_INVESTIGATING,
            'notes' => 'Reopened after a new investigation request.',
        ])->assertOk();
        $this->assertNull($alert->fresh()->resolved_at);
    }

    public function test_household_cannot_access_admin_leak_monitoring_controls(): void
    {
        $household = User::factory()->create(['role' => 'user']);
        $alert = LeakAlert::create([
            'user_id' => $household->id,
            'status' => LeakAlert::STATUS_DETECTED,
            'detected_at' => now(),
            'last_detected_at' => now(),
        ]);
        Sanctum::actingAs($household);

        $this->getJson('/api/leak-alerts')->assertForbidden();
        $this->patchJson('/api/leak-alerts/'.$alert->id, ['status' => LeakAlert::STATUS_RESOLVED])->assertForbidden();
        $this->postJson('/api/leak-alerts/'.$alert->id.'/notify', [
            'title' => 'Unauthorized',
            'message' => 'Unauthorized',
        ])->assertForbidden();
    }

    public function test_legacy_possible_leak_notifications_are_imported_into_monitoring_alerts(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create(['role' => 'user']);
        $notification = Notification::create([
            'user_id' => $household->id,
            'type' => 'possible_leak',
            'title' => 'Possible water leak detected',
            'message' => 'Water flow reached the configured threshold.',
            'sent_at' => now()->subHour(),
        ]);
        $notification->update(['water_reading_id' => null]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/leak-alerts')
            ->assertOk()
            ->assertJsonPath('data.data.0.user.id', $household->id)
            ->assertJsonPath('data.data.0.notification.id', $notification->id);

        $this->assertDatabaseHas('leak_alerts', ['user_id' => $household->id]);
    }
}
