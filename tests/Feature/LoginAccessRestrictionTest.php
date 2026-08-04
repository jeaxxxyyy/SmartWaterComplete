<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class LoginAccessRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_admin_created_household_can_login_and_receive_a_token(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create([
            'email' => 'approved-household@example.com',
            'password' => Hash::make('household-password'),
            'created_by_admin' => true,
            'created_by' => $admin->id,
            'approved_at' => now(),
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $household->email,
            'password' => 'household-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $household->id)
            ->assertJsonPath('data.user.role', 'user')
            ->assertJsonPath('message', 'Login successful.');

        $this->assertNotNull($response->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $household->id]);
    }

    public function test_incorrect_household_password_is_rejected_without_issuing_a_token(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create([
            'created_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->postJson('/api/login', [
            'email' => $household->email,
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Unable to log in. Please check your credentials or contact the administrator.');

        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $household->id)->count());
    }

    public function test_unapproved_household_accounts_are_rejected_with_a_generic_error(): void
    {
        $household = User::factory()->create([
            'email' => 'unapproved-household@example.com',
            'password' => Hash::make('household-password'),
            'created_by_admin' => false,
            'created_by' => null,
            'approved_at' => null,
        ]);

        $this->postJson('/api/login', [
            'email' => $household->email,
            'password' => 'household-password',
        ])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Unable to log in. Please check your credentials or contact the administrator.')
            ->assertDontSee($household->email);
    }

    public function test_incomplete_admin_flagged_household_accounts_are_rejected(): void
    {
        $household = User::factory()->create([
            'password' => Hash::make('household-password'),
            'created_by_admin' => true,
            'created_by' => null,
            'approved_at' => null,
        ]);

        $this->postJson('/api/login', [
            'email' => $household->email,
            'password' => 'household-password',
        ])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Unable to log in. Please check your credentials or contact the administrator.');
    }

    public function test_disabled_suspended_and_archived_households_cannot_login(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            ['email' => 'disabled@example.com', 'is_active' => false, 'account_status' => User::ACCOUNT_STATUS_ACTIVE],
            ['email' => 'suspended@example.com', 'is_active' => true, 'account_status' => User::ACCOUNT_STATUS_SUSPENDED],
            ['email' => 'archived@example.com', 'is_active' => true, 'account_status' => User::ACCOUNT_STATUS_ARCHIVED],
        ] as $attributes) {
            $household = User::factory()->create([
                ...$attributes,
                'password' => Hash::make('household-password'),
                'created_by' => $admin->id,
                'approved_at' => now(),
            ]);

            $this->postJson('/api/login', [
                'email' => $household->email,
                'password' => 'household-password',
            ])->assertForbidden()
                ->assertJsonPath('message', 'This account is currently inactive. Please contact the administrator.');
        }
    }

    public function test_deleted_households_cannot_login(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create([
            'password' => Hash::make('household-password'),
            'created_by' => $admin->id,
            'approved_at' => now(),
        ]);
        $email = $household->email;
        $household->delete();

        $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'household-password',
        ])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Unable to log in. Please check your credentials or contact the administrator.');
    }

    public function test_household_creation_endpoint_assigns_role_and_protected_fields_server_side(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/household-users', [
            'name' => 'Protected Household',
            'email' => 'protected-household@example.com',
            'password' => 'household-password',
            'address' => 'Poblacion, Calape',
            'role' => 'admin',
            'created_by_admin' => false,
            'created_by' => null,
            'approved_at' => null,
            'account_status' => User::ACCOUNT_STATUS_SUSPENDED,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.role', 'user')
            ->assertJsonPath('data.user.created_by_admin', true)
            ->assertJsonPath('data.user.created_by', $admin->id)
            ->assertJsonPath('data.user.account_status', User::ACCOUNT_STATUS_ACTIVE)
            ->assertJsonMissingPath('data.user.password');

        $this->assertNotNull($response->json('data.user.approved_at'));
        $createdUser = User::query()->where('email', 'protected-household@example.com')->firstOrFail();
        $this->assertNotSame('household-password', $createdUser->password);
        $this->assertTrue(Hash::check('household-password', $createdUser->password));
        $this->assertDatabaseHas('users', [
            'email' => 'protected-household@example.com',
            'role' => 'user',
            'created_by' => $admin->id,
            'created_by_admin' => true,
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        ]);
    }

    public function test_personal_information_keeps_its_original_case_in_storage(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')->postJson('/api/household-users', [
            'name' => 'lowercase household name',
            'email' => 'case-preserved@example.com',
            'password' => 'household-password',
            'address' => 'lowercase street address',
            'phone' => '+63 917 000 0000',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'case-preserved@example.com',
            'name' => 'lowercase household name',
            'address' => 'lowercase street address',
            'phone' => '+63 917 000 0000',
        ]);
    }

    public function test_non_admins_cannot_create_household_accounts_through_either_creation_endpoint(): void
    {
        $user = User::factory()->create();
        $payload = [
            'name' => 'Unauthorized Household',
            'email' => 'unauthorized-household@example.com',
            'password' => 'household-password',
            'role' => 'admin',
        ];

        $this->actingAs($user, 'sanctum')->postJson('/api/household-users', $payload)
            ->assertForbidden();
        $this->actingAs($user, 'sanctum')->postJson('/api/users', $payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized-household@example.com']);
    }

    public function test_public_registration_is_rejected_without_creating_an_account(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Public Household',
            'email' => 'public-household@example.com',
            'password' => 'household-password',
        ])->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('users', ['email' => 'public-household@example.com']);
    }

    public function test_existing_admin_and_authorized_staff_accounts_can_still_login(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create([
            'email' => 'staff@example.com',
            'role' => 'admin',
            'password' => Hash::make('staff-password'),
            'created_by_admin' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => User::ADMIN_PASSWORD,
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => $staff->email,
            'password' => 'staff-password',
        ])->assertOk();
    }

    public function test_an_active_administrator_role_can_use_administrator_endpoints_without_household_fields(): void
    {
        $administrator = User::factory()->create([
            'email' => 'operations-admin@example.com',
            'role' => 'admin',
            'password' => Hash::make('operations-password'),
            'created_by_admin' => false,
            'created_by' => null,
            'approved_at' => null,
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'OPERATIONS-ADMIN@EXAMPLE.COM',
            'password' => 'operations-password',
        ])->assertOk();

        $this->withToken($login->json('data.token'))
            ->getJson('/api/analytics/admin?range=last_7_days')
            ->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $administrator->id]);
    }

    public function test_disabling_or_suspending_a_household_revokes_existing_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create([
            'password' => Hash::make('household-password'),
            'created_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => $household->email,
            'password' => 'household-password',
        ])->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $household->id]);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/users/'.$household->id, [
                'account_status' => User::ACCOUNT_STATUS_SUSPENDED,
            ])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $household->id,
        ]);
        $this->assertNotNull($login->json('data.token'));
    }
}
