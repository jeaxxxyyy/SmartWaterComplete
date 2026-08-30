<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdPasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_household_can_change_its_password_with_the_current_password(): void
    {
        $household = User::factory()->create([
            'role' => 'user',
            'password' => Hash::make('old-password'),
        ]);
        Sanctum::actingAs($household);

        $this->patchJson('/api/account/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $household->fresh()->password));
    }

    public function test_admin_can_reset_a_household_password_and_revoke_existing_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create([
            'role' => 'user',
            'password' => Hash::make('old-password'),
        ]);
        $household->createToken('existing-household-session');
        Sanctum::actingAs($admin);

        $this->putJson('/api/users/'.$household->id, [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $household->fresh()->password));
        $this->assertSame(0, $household->tokens()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user_password_reset',
            'auditable_id' => $household->id,
        ]);
    }

    public function test_admin_password_reset_requires_matching_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create([
            'role' => 'user',
            'password' => Hash::make('old-password'),
        ]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/users/'.$household->id, [
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('old-password', $household->fresh()->password));
    }

    public function test_household_password_form_is_available_without_a_permanently_disabled_button(): void
    {
        $this->get('/household')
            ->assertOk()
            ->assertSee('data-password-form', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('data-password-submit', false)
            ->assertDontSee('data-password-submit disabled', false);
    }
}
