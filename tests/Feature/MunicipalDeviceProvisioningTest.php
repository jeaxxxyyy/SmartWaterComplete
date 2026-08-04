<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MunicipalDeviceProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_household_selector_returns_only_active_household_users(): void
    {
        $admin = User::factory()->admin()->create();
        $eligible = User::factory()->create(['name' => 'Eligible Household']);
        User::factory()->admin()->create(['email' => 'staff@example.com', 'role' => 'admin']);
        User::factory()->create(['role' => 'device', 'email' => 'device@example.com']);
        User::factory()->create(['is_active' => false, 'email' => 'inactive@example.com']);
        User::factory()->create(['account_status' => User::ACCOUNT_STATUS_SUSPENDED, 'email' => 'suspended@example.com']);
        User::factory()->create(['account_status' => User::ACCOUNT_STATUS_ARCHIVED, 'email' => 'archived@example.com']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/household-users?for_device=1&per_page=100')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($eligible->id, $response->json('data.0.id'));
    }

    public function test_registration_rejects_non_eligible_household_targets(): void
    {
        $admin = User::factory()->admin()->create();
        $targets = [
            User::factory()->admin()->create(['email' => 'another-admin@example.com']),
            User::factory()->create(['role' => 'device', 'email' => 'service@example.com']),
            User::factory()->create(['is_active' => false, 'email' => 'inactive-target@example.com']),
            User::factory()->create(['account_status' => User::ACCOUNT_STATUS_SUSPENDED, 'email' => 'suspended-target@example.com']),
            User::factory()->create(['account_status' => User::ACCOUNT_STATUS_ARCHIVED, 'email' => 'archived-target@example.com']),
        ];
        Sanctum::actingAs($admin);

        foreach ($targets as $index => $target) {
            $this->postJson('/api/municipal-devices', [
                'household_id' => $target->id,
                'asset_number' => 'INVALID-TARGET-'.$index,
                'serial_number' => 'INVALID-SERIAL-'.$index,
                'name' => 'Invalid assignment test meter',
            ])->assertUnprocessable();
        }

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_admin_can_register_activate_and_receive_household_telemetry(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create(['role' => 'user', 'account_number' => 'ACC-USER-0042']);
        Sanctum::actingAs($admin);

        $registration = $this->postJson('/api/municipal-devices', [
            'household_id' => $household->id,
            'asset_number' => 'MWM-2026-0042',
            'serial_number' => 'CORES3-0042',
            'name' => 'Household Meter 42',
        ])->assertCreated()->assertJsonPath('data.device.status', 'pending');

        $uuid = $registration->json('data.device.uuid');
        $activationCode = $registration->json('data.activation_code');
        $this->assertSame(8, strlen($activationCode));

        $this->postJson('/api/device-activate', [
            'device_uuid' => $uuid,
            'activation_code' => 'INVALID1',
            'hardware_id' => 'AA:BB:CC:DD:EE:FF',
            'firmware_version' => '1.1.0',
        ])->assertUnprocessable();

        $activation = $this->postJson('/api/device-activate', [
            'device_uuid' => $uuid,
            'activation_code' => $activationCode,
            'hardware_id' => 'AA:BB:CC:DD:EE:FF',
            'firmware_version' => '1.1.0',
        ])->assertOk()->assertJsonMissingPath('data.activation_code');

        $token = $activation->json('data.token');
        $this->app->make('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/water-data', [
            'reading_id' => 'cores3-0042-1',
            'flow_rate' => 2.5,
            'liters_used' => 0.125,
            'cubic_meter' => 0.000125,
            'device_name' => 'M5Stack CoreS3',
        ])->assertCreated()->assertJsonPath('data.reading.user_id', $household->id);

        $device = Device::where('uuid', $uuid)->firstOrFail();
        $this->assertSame('active', $device->status);
        $this->assertSame('AA:BB:CC:DD:EE:FF', $device->hardware_id);
        $this->assertNull($device->activation_code_hash);
        $this->assertNotNull($device->last_telemetry_at);
    }

    public function test_credential_rotation_revokes_old_token_and_decommission_blocks_device(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($admin);
        $registration = $this->postJson('/api/municipal-devices', [
            'household_id' => $household->id,
            'asset_number' => 'MWM-2026-0099',
            'serial_number' => 'CORES3-0099',
            'name' => 'Household Meter 99',
        ]);
        $device = Device::where('uuid', $registration->json('data.device.uuid'))->firstOrFail();
        $oldToken = $device->authUser->createToken('old', ['device-upload'])->plainTextToken;

        $this->postJson("/api/municipal-devices/{$device->id}/activation-code")
            ->assertOk()
            ->assertJsonStructure(['data' => ['device_uuid', 'activation_code', 'expires_at']]);
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => hash('sha256', explode('|', $oldToken, 2)[1])]);

        $this->deleteJson("/api/municipal-devices/{$device->id}")->assertOk();
        $this->assertSame('decommissioned', $device->fresh()->status);
        $this->postJson("/api/municipal-devices/{$device->id}/activation-code")->assertUnprocessable();
    }

    public function test_activation_code_is_single_use_and_the_issued_token_remains_valid(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($admin);

        $registration = $this->postJson('/api/municipal-devices', [
            'household_id' => $household->id,
            'asset_number' => 'MWM-2026-0111',
            'serial_number' => 'CORES3-0111',
            'name' => 'M5Stack CoreS3 111',
        ])->assertCreated();
        $uuid = $registration->json('data.device.uuid');
        $activationCode = $registration->json('data.activation_code');

        $activation = $this->postJson('/api/device-activate', [
            'device_uuid' => $uuid,
            'activation_code' => $activationCode,
            'hardware_id' => 'AA:BB:CC:DD:EE:11',
            'firmware_version' => '1.1.0',
        ])->assertOk();
        $token = $activation->json('data.token');

        $this->postJson('/api/device-activate', [
            'device_uuid' => $uuid,
            'activation_code' => $activationCode,
            'hardware_id' => 'AA:BB:CC:DD:EE:11',
            'firmware_version' => '1.1.0',
        ])->assertUnprocessable();

        $this->app->make('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/water-data', [
            'reading_id' => 'cores3-0111-1',
            'flow_rate' => 1.2,
            'liters_used' => 0.2,
        ])->assertCreated();
    }

    public function test_admin_device_list_reports_online_health_from_recent_telemetry(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create(['role' => 'user']);
        $onlineIdentity = User::factory()->create(['role' => 'device', 'assigned_user_id' => $household->id]);
        $offlineIdentity = User::factory()->create(['role' => 'device', 'assigned_user_id' => $household->id]);
        Device::create([
            'uuid' => (string) Str::uuid(), 'asset_number' => 'HEALTH-ONLINE', 'serial_number' => 'HEALTH-SERIAL-ONLINE',
            'name' => 'Online meter', 'household_id' => $household->id, 'auth_user_id' => $onlineIdentity->id,
            'status' => 'active', 'last_seen_at' => now(),
        ]);
        Device::create([
            'uuid' => (string) Str::uuid(), 'asset_number' => 'HEALTH-OFFLINE', 'serial_number' => 'HEALTH-SERIAL-OFFLINE',
            'name' => 'Offline meter', 'household_id' => $household->id, 'auth_user_id' => $offlineIdentity->id,
            'status' => 'active', 'last_seen_at' => now()->subSeconds(config('devices.offline_seconds') + 1),
        ]);

        Sanctum::actingAs($admin);
        $devices = $this->getJson('/api/municipal-devices')->assertOk()->json('data');
        $byAsset = collect($devices)->keyBy('asset_number');

        $this->assertTrue($byAsset['HEALTH-ONLINE']['is_online']);
        $this->assertFalse($byAsset['HEALTH-OFFLINE']['is_online']);
    }

    public function test_reassigning_a_device_revokes_old_credentials_and_requires_reactivation(): void
    {
        $admin = User::factory()->admin()->create();
        $firstHousehold = User::factory()->create(['role' => 'user']);
        $secondHousehold = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($admin);
        $registration = $this->postJson('/api/municipal-devices', [
            'household_id' => $firstHousehold->id,
            'asset_number' => 'MWM-2026-0100',
            'serial_number' => 'CORES3-0100',
            'name' => 'Household Meter 100',
        ]);
        $device = Device::where('uuid', $registration->json('data.device.uuid'))->firstOrFail();
        $activationCode = $registration->json('data.activation_code');
        $token = $this->postJson('/api/device-activate', [
            'device_uuid' => $device->uuid,
            'activation_code' => $activationCode,
            'hardware_id' => 'AA:BB:CC:DD:EE:99',
            'firmware_version' => '1.1.0',
        ])->json('token');

        $updated = $this->patchJson('/api/municipal-devices/'.$device->id, [
            'household_id' => $secondHousehold->id,
        ])->assertOk();

        $updated->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['activation_code', 'activation_expires_at']]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => hash('sha256', explode('|', $token, 2)[1]),
        ]);
        $this->app->make('auth')->forgetGuards();
        $this->withToken($token)->postJson('/api/water-data', [
            'reading_id' => 'reassigned-1',
            'flow_rate' => 1,
            'liters_used' => 1,
        ])->assertUnauthorized();
    }

    public function test_household_cannot_manage_municipal_devices(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/municipal-devices')->assertForbidden();
    }
}
