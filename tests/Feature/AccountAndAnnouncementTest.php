<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

    public function test_broadcast_notification_is_retrievable_and_unread_for_each_household(): void
    {
        $admin = User::factory()->admin()->create();
        $firstHousehold = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $secondHousehold = User::factory()->create(['role' => 'user', 'is_active' => true]);
        User::factory()->create(['role' => 'user', 'is_active' => false]);
        User::factory()->create(['role' => 'user', 'is_active' => true, 'account_status' => User::ACCOUNT_STATUS_SUSPENDED]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/notifications', [
            'type' => 'announcement',
            'title' => 'Water service update',
            'message' => 'Please review the latest water service information.',
        ])->assertCreated()
            ->assertJsonPath('recipient_count', 2)
            ->assertJsonPath('delivered_count', 2);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $firstHousehold->id,
            'title' => 'Water service update',
            'is_read' => false,
        ]);

        foreach ([$firstHousehold, $secondHousehold] as $household) {
            Sanctum::actingAs($household);

            $this->getJson('/api/notifications?per_page=100')
                ->assertOk()
                ->assertJsonPath('data.data.0.user_id', $household->id)
                ->assertJsonPath('data.data.0.is_read', false)
                ->assertJsonPath('data.data.0.title', 'Water service update');

            $this->getJson('/api/summary')
                ->assertOk()
                ->assertJsonPath('data.notifications.unread', 1)
                ->assertJsonPath('data.notifications.latest.0.title', 'Water service update');
        }
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

    public function test_admin_can_target_new_notification_categories_and_review_batch_delivery_status(): void
    {
        $admin = User::factory()->admin()->create();
        $recipient = User::factory()->create(['role' => 'user']);
        User::factory()->create(['role' => 'user', 'is_active' => false]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/notifications', [
            'type' => 'billing',
            'audience' => 'selected_household',
            'user_id' => $recipient->id,
            'title' => 'Billing notice',
            'message' => 'Your latest statement is available.',
        ])->assertCreated()
            ->assertJsonPath('recipient_count', 1)
            ->assertJsonPath('delivered_count', 1);

        $this->getJson('/api/notification-management')
            ->assertOk()
            ->assertJsonPath('data.data.0.type', 'billing')
            ->assertJsonPath('data.data.0.audience', 'selected_household')
            ->assertJsonPath('data.data.0.recipient_count', 1)
            ->assertJsonPath('data.data.0.delivered_count', 1)
            ->assertJsonPath('data.data.0.unread_count', 1);

        Sanctum::actingAs($recipient);
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('data.data.0.title', 'Billing notice');
    }

    public function test_scheduled_notifications_are_hidden_until_the_delivery_command_runs(): void
    {
        $admin = User::factory()->admin()->create();
        $recipient = User::factory()->create(['role' => 'user']);
        $dueAt = now()->addMinutes(5);
        Sanctum::actingAs($admin);

        $this->postJson('/api/notifications', [
            'type' => 'device_system',
            'audience' => 'selected_household',
            'user_id' => $recipient->id,
            'scheduled_at' => $dueAt->format('Y-m-d H:i:s'),
            'title' => 'Meter update',
            'message' => 'A system update is scheduled.',
        ])->assertCreated()->assertJsonPath('scheduled_count', 1);

        Sanctum::actingAs($recipient);
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(0, 'data.data');

        Notification::where('title', 'Meter update')->update(['scheduled_at' => now()->subMinute()]);
        Artisan::call('notifications:deliver-scheduled');

        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('data.data.0.title', 'Meter update');
    }

    public function test_admin_can_retry_failed_delivery_and_archive_history_without_removing_household_inbox(): void
    {
        $admin = User::factory()->admin()->create();
        $recipient = User::factory()->create(['role' => 'user']);
        $failed = Notification::create([
            'user_id' => $recipient->id,
            'type' => 'leak_alert',
            'batch_id' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Emergency alert',
            'message' => 'Please inspect the meter.',
            'delivery_status' => 'failed',
            'delivery_error' => 'Temporary delivery failure',
        ]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/notifications/'.$failed->id.'/retry')
            ->assertOk()
            ->assertJsonPath('data.delivery_status', 'delivered');
        $this->patchJson('/api/notifications/'.$failed->id.'/archive')->assertOk();
        $this->getJson('/api/notification-management')->assertOk()->assertJsonCount(0, 'data.data');

        Sanctum::actingAs($recipient);
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('data.data.0.title', 'Emergency alert');
    }

    public function test_household_cannot_use_admin_notification_management_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/notification-management')->assertForbidden();
        $this->postJson('/api/notifications', [
            'type' => 'announcement',
            'title' => 'Not allowed',
            'message' => 'A household cannot send this.',
        ])->assertForbidden();
    }
}
