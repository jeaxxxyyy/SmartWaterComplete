<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Device;
use App\Models\User;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceTelemetryContractTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function registerDevice(?User $admin = null): array
    {
        $admin ??= User::factory()->admin()->create();
        $household = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/municipal-devices', [
            'household_id' => $household->id,
            'asset_number' => 'METER-'.Str::upper(Str::random(8)),
            'serial_number' => 'CORE-'.Str::upper(Str::random(8)),
            'name' => 'M5Stack CoreS3 Meter',
        ])->assertCreated();

        return [$admin, $household, Device::query()->findOrFail($response->json('data.device.id')), $response->json('data.activation_code')];
    }

    private function activate(Device $device, string $activationCode, string $hardwareId = 'AA:BB:CC:DD:EE:01'): string
    {
        $response = $this->postJson('/api/device-activate', [
            'device_uuid' => $device->uuid,
            'activation_code' => $activationCode,
            'hardware_id' => $hardwareId,
            'firmware_version' => '1.1.0',
        ])->assertOk();

        $this->assertSame($response->json('data.token'), $response->json('token'));
        $this->app->make('auth')->forgetGuards();

        return $response->json('token');
    }

    private function readingPayload(string $readingId, array $overrides = []): array
    {
        return array_replace([
            'reading_id' => $readingId,
            'flow_rate' => 1.5,
            'liters_used' => 1.0,
            'total_water_liters' => 9999.0,
            'cubic_meter' => 0.001,
            'water_limit_liters' => 9999.0,
            'monitoring_status' => 'online',
            'wifi_signal_strength' => 85,
            'device_connection_status' => 'connected',
            'server_connection_status' => 'disconnected',
            'firmware_version' => '1.1.0',
        ], $overrides);
    }

    public function test_activation_rejects_unknown_and_disabled_devices_and_conflicting_hardware(): void
    {
        $this->postJson('/api/device-activate', [
            'device_uuid' => (string) Str::uuid(),
            'activation_code' => 'ABCDEFGH',
            'hardware_id' => 'AA:BB:CC:DD:EE:10',
            'firmware_version' => '1.1.0',
        ])->assertUnprocessable();

        [$admin, , $device, $code] = $this->registerDevice();
        $device->update(['status' => 'suspended']);
        $this->postJson('/api/device-activate', [
            'device_uuid' => $device->uuid,
            'activation_code' => $code,
            'hardware_id' => 'AA:BB:CC:DD:EE:11',
            'firmware_version' => '1.1.0',
        ])->assertForbidden();

        $device->update(['status' => 'pending']);
        $token = $this->activate($device, $code, 'AA:BB:CC:DD:EE:12');
        $device->update(['status' => 'suspended']);
        $this->withToken($token)->postJson('/api/water-data', $this->readingPayload('suspended-1'))->assertForbidden();

        Sanctum::actingAs($admin);
        [, , $secondDevice, $secondCode] = $this->registerDevice($admin);
        $this->postJson('/api/device-activate', [
            'device_uuid' => $secondDevice->uuid,
            'activation_code' => $secondCode,
            'hardware_id' => 'AA:BB:CC:DD:EE:12',
            'firmware_version' => '1.1.0',
        ])->assertConflict();
    }

    public function test_missing_and_invalid_device_tokens_are_rejected(): void
    {
        $payload = $this->readingPayload('unauthenticated-1');

        $this->postJson('/api/water-data', $payload)->assertUnauthorized();
        $this->withToken('not-a-valid-token')->postJson('/api/water-data', $payload)->assertUnauthorized();
    }

    public function test_invalid_reading_payload_returns_validation_errors(): void
    {
        [, , $device, $code] = $this->registerDevice();
        $token = $this->activate($device, $code);

        $this->withToken($token)->postJson('/api/water-data', [
            'reading_id' => 'invalid-1',
            'flow_rate' => -1,
            'liters_used' => -2,
            'monitoring_status' => 'unknown',
            'wifi_signal_strength' => 101,
        ])->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_reading_is_owned_by_authenticated_device_and_household(): void
    {
        [, $household, $device, $code] = $this->registerDevice();
        $otherHousehold = User::factory()->create(['role' => 'user']);
        $token = $this->activate($device, $code);

        $this->withToken($token)->postJson('/api/water-data', $this->readingPayload('wrong-owner-1', [
            'user_id' => $otherHousehold->id,
        ]))->assertForbidden();

        $this->withToken($token)->postJson('/api/water-data', $this->readingPayload('owned-1'))
            ->assertCreated()
            ->assertJsonPath('data.reading.user_id', $household->id)
            ->assertJsonPath('data.reading.device_id', $device->id);

        $this->assertDatabaseHas('water_readings', [
            'reading_id' => 'owned-1',
            'device_id' => $device->id,
            'user_id' => $household->id,
        ]);
    }

    public function test_duplicate_reading_id_is_idempotent_and_does_not_double_count(): void
    {
        [, $household, $device, $code] = $this->registerDevice();
        $token = $this->activate($device, $code);
        $payload = $this->readingPayload('retry-1', ['liters_used' => 2.5]);

        $this->withToken($token)->postJson('/api/water-data', $payload)
            ->assertCreated()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('server_total_liters', 2.5);

        $this->withToken($token)->postJson('/api/water-data', $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('reading_id', 'retry-1')
            ->assertJsonPath('server_total_liters', 2.5);

        $this->assertDatabaseCount('water_readings', 1);
        $this->assertSame(2.5, (float) WaterReading::query()->where('user_id', $household->id)->sum('liters_used'));
    }

    public function test_baseline_reporting_disables_the_legacy_server_limit_while_leak_boolean_is_authoritative(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-28 12:00:00'));
        [, $household, $device, $code] = $this->registerDevice();
        Billing::create([
            'user_id' => $household->id,
            'water_rate' => 40,
            'subsidy_deduction' => 0,
            'threshold_liters' => 2,
            'leak_flow_rate_threshold' => 2,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
            'is_active' => true,
        ]);
        $token = $this->activate($device, $code);

        $first = $this->withToken($token)->postJson('/api/water-data', $this->readingPayload('limit-1', [
            'flow_rate' => 2.5,
            'liters_used' => 1,
        ]));
        $first->assertCreated()
            ->assertJsonPath('is_possible_leak', true)
            ->assertJsonPath('water_limit_liters', 0)
            ->assertJsonPath('limit_reached', false);
        $this->assertIsBool($first->json('is_possible_leak'));

        $this->withToken($token)->postJson('/api/water-data', $this->readingPayload('limit-2', [
            'liters_used' => 1,
        ]))->assertCreated()
            ->assertJsonPath('server_total_liters', 2)
            ->assertJsonPath('limit_reached', false);

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'firmware_version' => '1.1.0',
        ]);
        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_server_total_liters_only_includes_the_readings_billing_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 12:00:00'));
        [, $household, $device, $code] = $this->registerDevice();
        $token = $this->activate($device, $code);

        $this->withToken($token)->postJson('/api/water-data', $this->readingPayload('august-reading', [
            'liters_used' => 5,
        ]))->assertCreated()
            ->assertJsonPath('server_total_liters', 5);

        Carbon::setTestNow(Carbon::parse('2026-09-01 12:00:00'));

        $septemberPayload = $this->readingPayload('september-reading', [
            'liters_used' => 2,
        ]);

        $this->withToken($token)->postJson('/api/water-data', $septemberPayload)
            ->assertCreated()
            ->assertJsonPath('server_total_liters', 2);

        $this->withToken($token)->postJson('/api/water-data', $septemberPayload)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('server_total_liters', 2);

        $this->assertSame(7.0, (float) WaterReading::query()
            ->where('user_id', $household->id)
            ->sum('liters_used'));
    }

    public function test_firmware_preserves_pending_telemetry_for_safe_retry_and_restart_recovery(): void
    {
        $source = file_get_contents(base_path('arduino/SmartWater_Modular/ApiClient.cpp'));

        $this->assertStringContainsString('persistPendingReading();', $source);
        $this->assertStringContainsString('preferences.getString(PENDING_ID_KEY, "")', $source);
        $this->assertStringContainsString('body += "\\"reading_id\\":\\"" + pendingReadingId', $source);
        $this->assertStringContainsString('acknowledgedReadingId != pendingReadingId', $source);
        $this->assertStringContainsString('The reading remains queued for retry:', $source);
        $this->assertStringContainsString('A duplicate response is also a successful acknowledgement.', $source);
        $this->assertStringContainsString('completePendingReading();', $source);
        $this->assertLessThan(
            strpos($source, 'const int status = http.POST(body);'),
            strpos($source, 'persistPendingReading();'),
            'Pending telemetry must be persisted before the HTTP upload begins.',
        );
    }
}
