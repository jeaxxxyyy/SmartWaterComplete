<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Device;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SmartWaterAdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_created_user_can_login_issue_tokens(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/household-users', [
            'name' => 'Household User',
            'email' => 'household@example.com',
            'password' => 'password123',
            'role' => 'user',
            'address' => 'Poblacion, Calape',
            'phone' => '09171234567',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Household user account created successfully. The user can now log in.')
            ->assertJsonPath('data.user.role', 'user')
            ->assertJsonPath('data.user.created_by_admin', true)
            ->assertJsonPath('data.user.created_by', $admin->id)
            ->assertJsonPath('data.user.account_status', 'active');

        $this->assertDatabaseHas('users', [
            'email' => 'household@example.com',
            'role' => 'user',
            'created_by_admin' => true,
        ]);

        $this->getJson('/api/users?search=household@example.com')
            ->assertOk()
            ->assertJsonFragment(['email' => 'household@example.com']);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'household@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertOk()
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_household_customer_listing_excludes_administrators_and_device_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create([
            'name' => 'Household Customer',
            'email' => 'customer-list@example.com',
            'role' => 'user',
        ]);
        $administrator = User::factory()->create([
            'name' => 'Second Administrator',
            'email' => 'second-admin@example.com',
            'role' => 'admin',
        ]);
        $deviceAccount = User::factory()->create([
            'name' => 'Meter Device Account',
            'email' => 'meter-device@example.com',
            'role' => 'device',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/household-users?per_page=100');

        $response->assertOk()
            ->assertJsonPath('data.data.0.role', 'user')
            ->assertJsonFragment(['email' => $household->email])
            ->assertJsonMissing(['email' => $administrator->email])
            ->assertJsonMissing(['email' => $deviceAccount->email]);

        $this->assertSame(1, collect($response->json('data.data'))->where('role', 'user')->count());
    }

    public function test_admin_can_suspend_a_household_account_and_filter_it_in_customer_management(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($admin);

        $this->putJson('/api/users/'.$household->id, [
            'is_active' => false,
            'account_status' => User::ACCOUNT_STATUS_SUSPENDED,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.account_status', User::ACCOUNT_STATUS_SUSPENDED);

        $this->getJson('/api/household-users?is_active=0')
            ->assertOk()
            ->assertJsonFragment(['id' => $household->id, 'role' => 'user', 'is_active' => false]);

        $this->assertDatabaseHas('users', [
            'id' => $household->id,
            'is_active' => false,
            'account_status' => User::ACCOUNT_STATUS_SUSPENDED,
        ]);
    }

    public function test_customer_management_searches_household_details_and_meter_identifiers(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create([
            'name' => 'North Reservoir Household',
            'address' => '42 Riverside Avenue',
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        ]);
        $deviceAccount = User::factory()->create(['role' => 'device']);
        Device::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'asset_number' => 'METER-RIVER-42',
            'serial_number' => 'CORES3-RIVER-42',
            'name' => 'North Reservoir Meter',
            'household_id' => $household->id,
            'auth_user_id' => $deviceAccount->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/household-users?search=Riverside&per_page=100')
            ->assertOk()
            ->assertJsonFragment(['id' => $household->id]);

        $this->getJson('/api/household-users?search=METER-RIVER-42&per_page=100')
            ->assertOk()
            ->assertJsonFragment(['id' => $household->id])
            ->assertJsonFragment(['asset_number' => 'METER-RIVER-42']);

        $this->getJson('/api/household-users?account_status=active&per_page=100')
            ->assertOk()
            ->assertJsonFragment(['id' => $household->id]);
    }

    public function test_admin_can_unassign_a_meter_and_revoke_its_device_credentials(): void
    {
        $admin = User::factory()->admin()->create();
        $household = User::factory()->create(['role' => 'user']);
        $deviceAccount = User::factory()->create([
            'role' => 'device',
            'assigned_user_id' => $household->id,
        ]);
        $device = Device::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'asset_number' => 'METER-UNASSIGN-01',
            'serial_number' => 'CORES3-UNASSIGN-01',
            'name' => 'Unassignable Meter',
            'household_id' => $household->id,
            'auth_user_id' => $deviceAccount->id,
            'status' => 'active',
        ]);
        $deviceAccount->createToken('meter-token', ['device-upload']);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/municipal-devices/'.$device->id, ['household_id' => null])
            ->assertOk()
            ->assertJsonPath('data.household_id', null)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'household_id' => null,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $deviceAccount->id,
            'assigned_user_id' => null,
        ]);
        $this->assertSame(0, $deviceAccount->tokens()->count());
    }

    public function test_register_page_does_not_offer_public_registration(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertDontSee('Register')
            ->assertDontSee('Account number');

        $this->postJson('/api/register', [
            'name' => 'New Household User',
            'email' => 'newhousehold@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => 'Poblacion, Calape',
            'phone' => '09171234567',
        ])->assertForbidden();
    }

    public function test_admin_created_accounts_generate_account_numbers_in_sequence(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $firstResponse = $this->postJson('/api/users', [
            'name' => 'Household One',
            'email' => 'household-one@example.com',
            'password' => 'password123',
            'role' => 'user',
            'address' => 'Household One Address',
        ]);

        $secondResponse = $this->postJson('/api/users', [
            'name' => 'Household Two',
            'email' => 'household-two@example.com',
            'password' => 'password123',
            'role' => 'user',
            'address' => 'Household Two Address',
        ]);

        $firstResponse->assertCreated();
        $secondResponse->assertCreated();

        $this->assertSame('ACC-USER-1', $firstResponse->json('data.user.account_number'));
        $this->assertSame('ACC-USER-2', $secondResponse->json('data.user.account_number'));
    }

    public function test_household_user_creation_returns_field_validation_errors(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/users', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'role' => 'user',
            'address' => 'Validation Address',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Please correct the highlighted fields.')
            ->assertJsonStructure(['errors' => ['name', 'email', 'password']]);
    }

    public function test_household_address_is_required_trimmed_and_rejected_when_blank(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/household-users', [
            'name' => 'Missing Address',
            'email' => 'missing-address@example.com',
            'password' => 'password123',
        ])->assertUnprocessable()->assertJsonPath('errors.address.0', 'The address field is required.');

        $this->postJson('/api/household-users', [
            'name' => 'Blank Address',
            'email' => 'blank-address@example.com',
            'password' => 'password123',
            'address' => '   ',
        ])->assertUnprocessable()->assertJsonPath('errors.address.0', 'The address field is required.');

        $created = $this->postJson('/api/household-users', [
            'name' => 'Trimmed Address',
            'email' => 'trimmed-address@example.com',
            'password' => 'password123',
            'address' => '  12 Water Street  ',
        ])->assertCreated();

        $user = User::query()->findOrFail($created->json('data.user.id'));
        $this->assertSame('12 Water Street', $user->address);

        $this->putJson('/api/users/'.$user->id, ['address' => '   '])
            ->assertUnprocessable()
            ->assertJsonPath('errors.address.0', 'The address field is required.');
    }

    public function test_device_household_options_are_active_households_sorted_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Zeta Household', 'role' => 'user', 'is_active' => true, 'account_status' => User::ACCOUNT_STATUS_ACTIVE]);
        User::factory()->create(['name' => 'Alpha Household', 'role' => 'user', 'is_active' => true, 'account_status' => User::ACCOUNT_STATUS_ACTIVE]);
        User::factory()->create(['name' => 'Inactive Household', 'role' => 'user', 'is_active' => false]);
        User::factory()->admin()->create(['email' => 'second-admin@example.com']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/household-users?for_device=1&per_page=100')->assertOk();
        $names = collect($response->json('data'))->pluck('name')->values()->all();

        $this->assertSame(['Alpha Household', 'Zeta Household'], $names);
    }

    public function test_household_user_creation_rejects_duplicate_email_without_creating_a_second_record(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'duplicate@example.com']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/users', [
            'name' => 'Duplicate Household',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'role' => 'user',
            'address' => 'Duplicate Address',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.email.0', 'The email has already been taken.');

        $this->assertSame(1, User::query()->where('email', 'duplicate@example.com')->count());
    }

    public function test_repeating_the_same_creation_request_does_not_create_duplicate_household_users(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $payload = [
            'name' => 'Repeated Household',
            'email' => 'repeated@example.com',
            'password' => 'password123',
            'role' => 'user',
            'address' => 'Repeated Address',
        ];

        $this->postJson('/api/users', $payload)->assertCreated();
        $this->postJson('/api/users', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(1, User::query()->where('email', $payload['email'])->count());
    }

    public function test_regular_users_receive_a_consistent_forbidden_response_for_household_creation(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/users', [
            'name' => 'Not Allowed',
            'email' => 'not-allowed@example.com',
            'password' => 'password123',
            'role' => 'user',
        ])->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Admin access is required.');
    }

    public function test_summary_is_scoped_to_current_user_for_households_and_all_users_for_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $householdOne = User::factory()->create();
        $householdTwo = User::factory()->create();

        WaterReading::create([
            'user_id' => $householdOne->id,
            'flow_rate' => 1.2,
            'liters_used' => 1200,
            'cubic_meter' => 1.2,
            'estimated_bill' => 48,
            'rate_per_cubic_meter' => 20,
            'subsidy_deduction' => 5,
            'threshold_liters' => 100,
            'is_threshold_exceeded' => false,
            'is_possible_leak' => false,
            'recorded_at' => now()->subDay(),
        ]);

        WaterReading::create([
            'user_id' => $householdTwo->id,
            'flow_rate' => 2.4,
            'liters_used' => 2400,
            'cubic_meter' => 2.4,
            'estimated_bill' => 96,
            'rate_per_cubic_meter' => 20,
            'subsidy_deduction' => 5,
            'threshold_liters' => 100,
            'is_threshold_exceeded' => false,
            'is_possible_leak' => false,
            'recorded_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($householdOne);
        $householdResponse = $this->getJson('/api/summary');
        $householdResponse->assertOk()
            ->assertJsonPath('data.current_month.total_liters', 1200);

        Sanctum::actingAs($admin);
        $adminResponse = $this->getJson('/api/summary');
        $adminResponse->assertOk()
            ->assertJsonPath('data.current_month.total_liters', 3600);
    }

    public function test_admin_can_manage_users_and_view_reports(): void
    {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create();

        Billing::create([
            'user_id' => null,
            'water_rate' => 20,
            'subsidy_deduction' => 5,
            'threshold_liters' => 100,
            'leak_flow_rate_threshold' => 2,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
            'is_active' => true,
        ]);

        WaterReading::create([
            'user_id' => $targetUser->id,
            'flow_rate' => 3,
            'liters_used' => 120,
            'cubic_meter' => 0.12,
            'estimated_bill' => 0,
            'rate_per_cubic_meter' => 20,
            'subsidy_deduction' => 5,
            'threshold_liters' => 100,
            'is_threshold_exceeded' => true,
            'is_possible_leak' => true,
            'recorded_at' => now(),
        ]);

        Notification::create([
            'user_id' => $targetUser->id,
            'water_reading_id' => null,
            'type' => 'threshold_exceeded',
            'title' => 'Threshold',
            'message' => 'Reached threshold',
            'metadata' => [],
            'is_read' => false,
            'sent_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['email' => $targetUser->email]);

        $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'role' => 'user',
            'account_number' => 'ACC-2002',
            'address' => 'Poblacion',
            'phone' => '09179999999',
            'is_active' => true,
        ])->assertCreated();

        $this->getJson('/api/reports/consumption')
            ->assertOk()
            ->assertJsonPath('data.summary.reading_count', 1);

        $this->getJson('/api/reports/notifications')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_regular_users_cannot_access_admin_endpoints(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_device_token_can_upload_water_data(): void
    {
        $device = User::factory()->create([
            'role' => 'device',
            'email' => 'device-token@example.com',
            'password' => bcrypt('device-password'),
        ]);
        $household = User::factory()->create(['role' => 'user']);
        Device::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'asset_number' => 'TEST-METER-002',
            'serial_number' => 'TEST-CORE-002',
            'name' => 'Test Device',
            'household_id' => $household->id,
            'auth_user_id' => $device->id,
            'status' => 'active',
        ]);

        $token = $device->createToken('water-device', ['device-upload'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/water-data', [
                'reading_id' => 'admin-api-device-1',
                'flow_rate' => 1.8,
                'liters_used' => 25,
                'cubic_meter' => 0.025,
                'recorded_at' => now()->toDateTimeString(),
            ])
            ->assertCreated();
    }

    public function test_regular_authenticated_users_cannot_upload_water_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/water-data', [
            'user_id' => $user->id,
            'flow_rate' => 1.8,
            'liters_used' => 25,
            'cubic_meter' => 0.025,
            'recorded_at' => now()->toDateTimeString(),
        ])->assertForbidden();
    }

    public function test_legacy_manual_token_routes_are_not_exposed(): void
    {
        $admin = User::factory()->admin()->create();
        $device = User::factory()->create([
            'role' => 'device',
        ]);

        Sanctum::actingAs($admin);
        $this->postJson('/api/devices/'.$device->id.'/tokens')->assertNotFound();
        $this->getJson('/api/devices/'.$device->id.'/tokens')->assertNotFound();
    }
}
