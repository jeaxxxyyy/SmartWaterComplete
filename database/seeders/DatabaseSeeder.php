<?php

namespace Database\Seeders;

use App\Models\Billing;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(['email' => User::ADMIN_EMAIL], [
            'name' => 'Admin User',
            'password' => Hash::make(User::ADMIN_PASSWORD),
            'role' => 'admin',
            'account_number' => 'ACC-ADMIN-0001',
            'email_verified_at' => now(),
            'is_active' => true,
            'created_by_admin' => true,
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        ]);

        $household = User::updateOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
            'role' => 'user',
            'account_number' => 'ACC-USER-0001',
            'email_verified_at' => now(),
            'is_active' => true,
            'created_by_admin' => true,
            'created_by' => $admin->id,
            'approved_at' => now(),
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        ]);

        $deviceUser = User::updateOrCreate(['email' => 'device@example.com'], [
            'name' => 'ESP32-S3 Panel',
            'password' => Hash::make('device123'),
            'role' => 'device',
            'assigned_user_id' => $household->id,
            'account_number' => 'ACC-DEVICE-0001',
            'email_verified_at' => now(),
            'is_active' => true,
            'created_by_admin' => true,
            'created_by' => $admin->id,
            'approved_at' => now(),
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        ]);

        Device::updateOrCreate(['serial_number' => 'CORES3-DEMO-0001'], [
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'asset_number' => 'MWM-2026-0001',
            'name' => 'M5Stack CoreS3 Household Meter',
            'household_id' => $household->id,
            'auth_user_id' => $deviceUser->id,
            'hardware_id' => '68:EE:8F:D8:3F:EC',
            'firmware_version' => '1.0.0',
            'status' => 'active',
            'maintenance_status' => 'operational',
            'installation_address' => $household->address,
            'installed_at' => now(),
            'activated_at' => now(),
            'calibrated_at' => now(),
            'calibration_due_at' => now()->addYear(),
        ]);

        Billing::updateOrCreate(['user_id' => null, 'is_active' => true], [
            'water_rate' => 40,
            'subsidy_deduction' => 0,
            'threshold_liters' => 500,
            'leak_flow_rate_threshold' => 8,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
        ]);
    }
}
