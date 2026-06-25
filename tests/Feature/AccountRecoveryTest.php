<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_and_reset_password_routes_work(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        Password::shouldReceive('sendResetLink')
            ->once()
            ->andReturn(Password::RESET_LINK_SENT);

        Password::shouldReceive('reset')
            ->once()
            ->andReturnUsing(function (array $credentials, callable $callback) use ($user): string {
                $callback($user, 'new-password');

                return Password::PASSWORD_RESET;
            });

        $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        $this->postJson('/api/reset-password', [
            'token' => 'reset-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }
}
