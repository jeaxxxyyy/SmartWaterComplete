<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ProvisionSmartWaterDevice extends Command
{
    protected $signature = 'smartwater:provision-device
        {--device=device@example.com : Device account email}
        {--server= : Laravel URL reachable by the ESP32}
        {--wifi-sketch= : Existing sketch containing WIFI_SSID and WIFI_PASSWORD}';

    protected $description = 'Create an ESP32 upload token and write the ignored Arduino secrets.h file';

    public function handle(): int
    {
        $device = User::query()->where('email', $this->option('device'))->where('role', 'device')->first();
        if (! $device || ! $device->assigned_user_id) {
            $this->error('The device account does not exist or is not assigned to a household.');

            return self::FAILURE;
        }
        $registeredDevice = Device::query()->where('auth_user_id', $device->id)->first();
        if (! $registeredDevice || $registeredDevice->status !== 'active' || ! $device->isAccountActive()) {
            $this->error('The device must be registered and active before an upload token can be provisioned.');

            return self::FAILURE;
        }

        $sourcePath = (string) $this->option('wifi-sketch');
        $source = $sourcePath !== '' && File::exists($sourcePath) ? File::get($sourcePath) : '';
        preg_match('/WIFI_SSID\s*=\s*"([^"]*)"/', $source, $ssidMatch);
        preg_match('/WIFI_PASSWORD\s*=\s*"([^"]*)"/', $source, $passwordMatch);
        $ssid = $ssidMatch[1] ?? 'YOUR_WIFI_NAME';
        $password = $passwordMatch[1] ?? 'YOUR_WIFI_PASSWORD';
        $server = rtrim((string) ($this->option('server') ?: config('app.url')), '/');
        if (preg_match('/(^|\/\/)(localhost|127\.0\.0\.1|::1)(:|\/|$)/i', $server)) {
            $this->error('The firmware server URL must be reachable from the M5Stack LAN. Pass --server with the computer LAN IPv4 address.');

            return self::FAILURE;
        }
        $token = $device->createToken('M5Stack CoreS3', ['device-upload'])->plainTextToken;
        $escape = static fn (string $value): string => addcslashes($value, '\\"');
        $contents = "#pragma once\n\n"
            .'#define WIFI_SSID "'.$escape($ssid)."\"\n"
            .'#define WIFI_PASSWORD "'.$escape($password)."\"\n"
            .'#define SMARTWATER_SERVER_URL "'.$escape($server)."\"\n"
            .'#define SMARTWATER_DEVICE_UUID "'.$escape($registeredDevice->uuid)."\"\n"
            .'#define SMARTWATER_ACTIVATION_CODE ""\n'
            .'#define SMARTWATER_DEVICE_TOKEN "'.$escape($token)."\"\n";
        $target = base_path('arduino/smartwater/secrets.h');
        File::ensureDirectoryExists(dirname($target));
        File::put($target, $contents);

        $this->info('Device provisioned for '.$device->assignedHousehold->email.'.');
        $this->info('Private firmware configuration written to arduino/smartwater/secrets.h.');
        if ($ssid === 'YOUR_WIFI_NAME') {
            $this->warn('Wi-Fi values were not found; edit secrets.h before uploading.');
        }

        return self::SUCCESS;
    }
}
