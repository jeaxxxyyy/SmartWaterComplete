<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_review_system_and_relationship_health(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create(['role' => 'user']);
        $deviceUser = User::factory()->create(['role' => 'device', 'assigned_user_id' => $household->id]);
        $device = Device::create([
            'uuid' => (string) Str::uuid(),
            'asset_number' => 'HEALTH-METER-001',
            'serial_number' => 'HEALTH-CORE-001',
            'name' => 'Health Test Meter',
            'household_id' => $household->id,
            'auth_user_id' => $deviceUser->id,
            'status' => 'active',
            'last_seen_at' => now(),
        ]);
        WaterReading::create([
            'user_id' => $household->id,
            'device_id' => $device->id,
            'reading_id' => 'health-reading-001',
            'flow_rate' => 1,
            'liters_used' => 0.1,
            'cubic_meter' => 0.0001,
            'recorded_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/system-health')
            ->assertOk()
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonPath('data.database.status', 'connected')
            ->assertJsonPath('data.database.foreign_keys_enforced', true)
            ->assertJsonPath('data.telemetry.latest_reading_id', 1)
            ->assertJsonPath('data.devices.active', 1)
            ->assertJsonPath('data.devices.online', 1)
            ->assertJsonPath('data.integrity.issue_count', 0)
            ->assertJsonPath('data.queues.failed_jobs', 0);
    }

    public function test_household_cannot_access_system_health(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/system-health')->assertForbidden();
    }

    public function test_admin_dashboard_exposes_system_health_status(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Application and data health')
            ->assertSee('data-admin-system-health-status', false)
            ->assertSee('data-admin-system-database', false)
            ->assertSee('data-admin-system-telemetry', false)
            ->assertSee('data-admin-system-integrity', false)
            ->assertSee('data-admin-system-failed-jobs', false);
    }
}
