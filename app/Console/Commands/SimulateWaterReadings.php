<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WaterReading;
use App\Services\WaterConsumptionService;
use Illuminate\Console\Command;

class SimulateWaterReadings extends Command
{
    protected $signature = 'smartwater:simulate {--once : Create one reading and exit} {--user= : Household email} {--interval= : Seconds between readings}';

    protected $description = 'Generate realistic ESP32-S3 demo telemetry while no physical sensor is installed';

    public function handle(WaterConsumptionService $service): int
    {
        $email = (string) ($this->option('user') ?: config('smartwater.demo.user_email'));
        $interval = max(1, (int) ($this->option('interval') ?: config('smartwater.demo.interval_seconds')));
        $user = User::query()->where('email', $email)->where('role', 'user')->first();

        if (! $user) {
            $this->error("Household {$email} was not found. Run php artisan migrate:fresh --seed first.");

            return self::FAILURE;
        }

        $this->info("Sending demo telemetry to {$user->email} every {$interval} seconds. Press Ctrl+C to stop.");

        do {
            $previousTotal = (float) WaterReading::query()->where('user_id', $user->id)->sum('liters_used');
            $phase = now()->timestamp % 60;
            $flowRate = round(2.4 + (sin($phase / 7) * 1.2) + random_int(0, 35) / 100, 2);
            $intervalLiters = round(max($flowRate, 0) * ($interval / 60), 3);

            $service->storeReading([
                'user_id' => $user->id,
                'device_name' => config('smartwater.demo.device_name'),
                'flow_rate' => $flowRate,
                'liters_used' => $intervalLiters,
                'total_water_liters' => round($previousTotal + $intervalLiters, 3),
                'cubic_meter' => $intervalLiters / 1000,
                'leak_detection_status' => 'Normal',
                'monitoring_status' => 'online',
                'wifi_signal_strength' => random_int(72, 96),
                'device_connection_status' => 'connected',
                'server_connection_status' => 'connected',
                'recorded_at' => now(),
            ]);

            $this->line(now()->format('H:i:s').'  '.number_format($flowRate, 2).' L/min');

            if (! $this->option('once')) {
                sleep($interval);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
