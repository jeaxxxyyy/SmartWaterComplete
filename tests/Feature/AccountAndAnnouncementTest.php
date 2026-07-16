<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountAndAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_household_can_update_contact_details_and_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/account', ['email' => 'updated@example.com', 'phone' => '09171234567'])
            ->assertOk()->assertJsonPath('data.email', 'updated@example.com');
        $this->patchJson('/api/account/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_admin_can_send_an_announcement_to_all_households(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create(['role' => 'user']);
        User::factory()->create(['role' => 'device']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/notifications', [
            'type' => 'maintenance',
            'title' => 'Scheduled maintenance',
            'message' => 'Service will pause at 10 PM.',
        ])->assertCreated();
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(1, Notification::query()->distinct()->count('batch_id'));
    }

    public function test_admin_can_delete_one_or_all_sent_notifications_without_deleting_device_alerts(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($admin);
        $sentOne = Notification::create([
            'user_id' => $user->id, 'type' => 'announcement', 'title' => 'One', 'message' => 'First', 'sent_at' => now(),
        ]);
        Notification::create([
            'user_id' => $user->id, 'type' => 'maintenance', 'title' => 'Two', 'message' => 'Second', 'sent_at' => now(),
        ]);
        $automatic = Notification::create([
            'user_id' => $user->id, 'type' => 'possible_leak', 'title' => 'Leak', 'message' => 'Alert', 'sent_at' => now(),
        ]);

        $this->deleteJson('/api/notifications/sent/'.$sentOne->id)->assertOk();
        $this->deleteJson('/api/notifications/sent/all')->assertOk()->assertJsonPath('data.deleted_count', 1);
        $this->assertDatabaseHas('notifications', ['id' => $automatic->id]);
        $this->deleteJson('/api/notifications/sent/'.$automatic->id)->assertUnprocessable();
    }
}
