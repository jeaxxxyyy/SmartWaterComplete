<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPromotionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_console_can_promote_existing_household_user_to_admin(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => false,
        ]);

        $this->artisan('user:make-admin', ['email' => $user->email])
            ->expectsOutput("{$user->email} is now an admin.")
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_console_does_not_promote_device_accounts(): void
    {
        $device = User::factory()->create([
            'role' => 'device',
        ]);

        $this->artisan('user:make-admin', ['email' => $device->email])
            ->expectsOutput('Device accounts cannot be promoted to admin.')
            ->assertFailed();

        $this->assertDatabaseHas('users', [
            'id' => $device->id,
            'role' => 'device',
        ]);
    }

    public function test_public_registration_cannot_create_admin_even_if_role_is_sent(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Public Registrant',
            'email' => 'public@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'public@example.com',
        ]);
    }
}
