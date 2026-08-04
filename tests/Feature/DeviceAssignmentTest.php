<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_device_upload_is_visible_to_its_household(): void
    {
        $household = User::factory()->create(['role' => 'user']);
        $device = User::factory()->create([
            'role' => 'device',
            'assigned_user_id' => $household->id,
        ]);
        Device::create([
            'uuid' => (string) Str::uuid(),
            'asset_number' => 'TEST-METER-001',
            'serial_number' => 'TEST-CORE-001',
            'name' => 'M5Stack CoreS3',
            'household_id' => $household->id,
            'auth_user_id' => $device->id,
            'status' => 'active',
        ]);
        $deviceToken = $device->createToken('ESP32-S3', ['device-upload'])->plainTextToken;

        $this->withToken($deviceToken)->postJson('/api/water-data', [
            'reading_id' => 'test-device-1',
            'flow_rate' => 2.5,
            'liters_used' => 0.125,
            'cubic_meter' => 0.000125,
            'device_name' => 'M5Stack CoreS3',
        ])->assertCreated()->assertJsonPath('data.reading.user_id', $household->id);

        Sanctum::actingAs($household);
        $this->getJson('/api/readings')
            ->assertOk()
            ->assertJsonPath('data.0.device_name', 'M5Stack CoreS3');
    }
}
