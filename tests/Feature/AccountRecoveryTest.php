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
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'created_by' => $admin->id,
            'approved_at' => now(),
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        ]);
        $existingToken = $user->createToken('existing-session')->accessToken;

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
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $existingToken->id]);
    }

    public function test_unapproved_household_accounts_do_not_receive_password_reset_access(): void
    {
        $user = User::factory()->create([
            'created_by_admin' => false,
            'created_by' => null,
            'approved_at' => null,
        ]);

        $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ])->assertOk()
            ->assertJsonPath('message', 'If the account is eligible, password reset instructions will be sent.');

        $this->postJson('/api/forgot-password', [
            'email' => 'missing-account@example.com',
        ])->assertOk()
            ->assertJsonPath('message', 'If the account is eligible, password reset instructions will be sent.');

        $this->postJson('/api/reset-password', [
            'token' => 'reset-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Unable to reset the password for this account.');
    }
}
