<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SmartWaterAdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_and_login_issue_tokens(): void
    {
        $registerResponse = $this->postJson('/api/register', [
            'name' => 'Household User',
            'email' => 'household@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_number' => 'ACC-1001',
            'address' => 'Poblacion, Calape',
            'phone' => '09171234567',
        ]);

        $registerResponse->assertCreated()
            ->assertJsonPath('data.user.role', 'user')
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'household@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertOk()
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_register_page_omits_account_number_and_persists_new_account_without_it(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertDontSee('Account number');

        $registerResponse = $this->postJson('/api/register', [
            'name' => 'New Household User',
            'email' => 'newhousehold@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => 'Poblacion, Calape',
            'phone' => '09171234567',
        ]);

        $registerResponse->assertCreated()
            ->assertJsonPath('data.user.account_number', 'ACC-USER-1');

        $this->assertDatabaseHas('users', [
            'email' => 'newhousehold@example.com',
            'name' => 'New Household User',
            'account_number' => 'ACC-USER-1',
            'role' => 'user',
            'is_active' => true,
        ]);
    }

    public function test_registration_generates_account_numbers_in_sequence(): void
    {
        $firstResponse = $this->postJson('/api/register', [
            'name' => 'Household One',
            'email' => 'household-one@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $secondResponse = $this->postJson('/api/register', [
            'name' => 'Household Two',
            'email' => 'household-two@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $firstResponse->assertCreated();
        $secondResponse->assertCreated();

        $this->assertSame('ACC-USER-1', $firstResponse->json('data.user.account_number'));
        $this->assertSame('ACC-USER-2', $secondResponse->json('data.user.account_number'));
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
            ->assertJsonPath('data.current_month.total_liters', 1200.0);

        Sanctum::actingAs($admin);
        $adminResponse = $this->getJson('/api/summary');
        $adminResponse->assertOk()
            ->assertJsonPath('data.current_month.total_liters', 3600.0);
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

        $token = $device->createToken('water-device', ['device-upload'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/water-data', [
                'user_id' => $device->id,
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

    public function test_admin_can_issue_list_and_revoke_device_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $device = User::factory()->create([
            'role' => 'device',
        ]);

        Sanctum::actingAs($admin);

        $issueResponse = $this->postJson('/api/devices/' . $device->id . '/tokens', [
            'name' => 'ESP32 Core2',
            'abilities' => ['device-upload'],
        ]);

        $issueResponse->assertCreated()
            ->assertJsonPath('data.token', fn ($value) => is_string($value) && $value !== '');

        $tokenId = $device->tokens()->firstOrFail()->id;

        $this->getJson('/api/devices/' . $device->id . '/tokens')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson('/api/devices/' . $device->id . '/tokens/' . $tokenId)
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }
}
