<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'account_number' => 'ACC-ADMIN-0001',
        ]);

        User::factory()->create([
            'name' => 'Device User',
            'email' => 'device@example.com',
            'role' => 'device',
            'account_number' => 'ACC-DEVICE-0001',
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'account_number' => 'ACC-USER-0001',
        ]);
    }
}
