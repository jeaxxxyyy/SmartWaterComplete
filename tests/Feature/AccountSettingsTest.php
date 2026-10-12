<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_household_can_load_only_its_own_account_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create([
            'name' => 'King Dela Cruz',
            'email' => 'king@example.com',
            'account_number' => 'ACC-KING-001',
            'address' => 'Barangay Central',
            'phone' => '09171234567',
        ]);
        $household->forceFill(['assigned_user_id' => $admin->id, 'created_by' => $admin->id])->save();
        Sanctum::actingAs($household);

        $this->getJson('/api/account')
            ->assertOk()
            ->assertJsonPath('data.name', 'King Dela Cruz')
            ->assertJsonPath('data.email', 'king@example.com')
            ->assertJsonPath('data.account_number', 'ACC-KING-001')
            ->assertJsonPath('data.address', 'Barangay Central')
            ->assertJsonPath('data.phone', '09171234567')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonMissing(['created_by' => $admin->id])
            ->assertJsonMissing(['assigned_user_id' => $admin->id])
            ->assertJsonMissing(['password' => $household->password]);
    }

    public function test_household_contact_updates_cannot_modify_protected_account_fields(): void
    {
        $household = User::factory()->create([
            'name' => 'Original Name',
            'account_number' => 'ACC-ORIGINAL-001',
        ]);
        Sanctum::actingAs($household);

        $this->patchJson('/api/account', [
            'name' => 'Attempted Name Change',
            'role' => 'admin',
            'account_number' => 'ACC-OVERRIDE',
            'email' => 'new@example.com',
            'phone' => '09998887777',
        ])->assertOk()
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.phone', '09998887777')
            ->assertJsonPath('data.name', 'Original Name')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.account_number', 'ACC-ORIGINAL-001');
    }

    public function test_admin_contact_updates_are_visible_from_the_household_profile_endpoint(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create([
            'address' => 'Old Address',
            'phone' => '09170000000',
        ]);

        Sanctum::actingAs($admin);
        $this->putJson("/api/users/{$household->id}", [
            'address' => 'New Household Address',
            'phone' => '09998887777',
        ])->assertOk()
            ->assertJsonPath('data.address', 'New Household Address')
            ->assertJsonPath('data.phone', '09998887777');

        Sanctum::actingAs($household->fresh());
        $this->getJson('/api/account')
            ->assertOk()
            ->assertJsonPath('data.address', 'New Household Address')
            ->assertJsonPath('data.phone', '09998887777');
    }

    public function test_admin_cannot_use_household_account_settings_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/account')->assertForbidden();
        $this->patchJson('/api/account', ['email' => 'admin-new@example.com'])->assertForbidden();
        $this->patchJson('/api/account/password', [
            'current_password' => 'admin123',
            'password' => 'new-admin-password',
            'password_confirmation' => 'new-admin-password',
        ])->assertForbidden();
    }
}
