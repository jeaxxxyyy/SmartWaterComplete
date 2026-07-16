<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MunicipalDeviceProvisioningTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_household_cannot_manage_municipal_devices(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/municipal-devices')->assertForbidden();
    }
}
