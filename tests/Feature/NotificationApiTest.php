<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Proves a signed-in user only sees their own notifications and unread badge count.
     */
    public function test_user_can_list_only_their_notifications_with_unread_count(): void
    {
        $this->seed(RoleSeeder::class);

        $customerUser = $this->createCustomerUser('notify-customer@example.com');
        $otherCustomerUser = $this->createCustomerUser('other-notify-customer@example.com');

        $latestNotification = $this->createNotification(
            $customerUser,
            title: 'Latest notification',
            message: 'Please check the latest update.',
        );
        $this->createNotification(
            $customerUser,
            title: 'Read notification',
            message: 'This one is already read.',
            isRead: true,
        );
        $this->createNotification(
            $otherCustomerUser,
            title: 'Other user notification',
            message: 'This should stay hidden.',
        );

        Sanctum::actingAs($customerUser);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.0.id', $latestNotification->id)
            ->assertJsonPath('data.notifications.0.title', 'Latest notification')
            ->assertJsonCount(2, 'data.notifications');
    }

    /**
     * Proves marking a single notification as read updates the unread badge immediately.
     */
    public function test_user_can_mark_one_notification_as_read(): void
    {
        $this->seed(RoleSeeder::class);

        $customerUser = $this->createCustomerUser('mark-one@example.com');
        $unreadNotification = $this->createNotification(
            $customerUser,
            title: 'Unread notification',
            message: 'Needs attention.',
        );

        Sanctum::actingAs($customerUser);

        $this->patchJson("/api/notifications/{$unreadNotification->id}/read")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.notification.id', $unreadNotification->id)
            ->assertJsonPath('data.notification.is_read', true)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertDatabaseMissing('notifications', [
            'id' => $unreadNotification->id,
            'read_at' => null,
        ]);
    }

    /**
     * Proves marking every notification as read affects only the current user's unread rows.
     */
    public function test_user_can_mark_all_notifications_as_read_without_affecting_other_users(): void
    {
        $this->seed(RoleSeeder::class);

        $customerUser = $this->createCustomerUser('read-all@example.com');
        $otherCustomerUser = $this->createCustomerUser('other-read-all@example.com');

        $this->createNotification($customerUser, title: 'Unread one', message: 'First unread.');
        $this->createNotification($customerUser, title: 'Unread two', message: 'Second unread.');
        $otherUsersNotification = $this->createNotification(
            $otherCustomerUser,
            title: 'Other unread',
            message: 'Should stay unread.',
        );

        Sanctum::actingAs($customerUser);

        $this->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(0, $customerUser->fresh()->unreadNotifications()->count());
        $this->assertDatabaseHas('notifications', [
            'id' => $otherUsersNotification->id,
            'read_at' => null,
        ]);
    }

    /**
     * Proves one notification can be cleared and the owner's unread badge stays accurate.
     */
    public function test_user_can_delete_one_notification(): void
    {
        $this->seed(RoleSeeder::class);

        $customerUser = $this->createCustomerUser('delete-one@example.com');
        $notificationToDelete = $this->createNotification(
            $customerUser,
            title: 'Delete me',
            message: 'Remove this notification.',
        );
        $notificationToKeep = $this->createNotification(
            $customerUser,
            title: 'Keep me',
            message: 'Leave this notification.',
        );

        Sanctum::actingAs($customerUser);

        $this->deleteJson("/api/notifications/{$notificationToDelete->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 1);

        $this->assertDatabaseMissing('notifications', [
            'id' => $notificationToDelete->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'id' => $notificationToKeep->id,
        ]);
    }

    /**
     * Proves users cannot read or delete another user's notification rows.
     */
    public function test_user_cannot_manage_another_users_notification(): void
    {
        $this->seed(RoleSeeder::class);

        $customerUser = $this->createCustomerUser('owner@example.com');
        $otherCustomerUser = $this->createCustomerUser('intruder@example.com');
        $otherUsersNotification = $this->createNotification(
            $otherCustomerUser,
            title: 'Private notification',
            message: 'This belongs to another user.',
        );

        Sanctum::actingAs($customerUser);

        $this->patchJson("/api/notifications/{$otherUsersNotification->id}/read")
            ->assertNotFound();

        $this->deleteJson("/api/notifications/{$otherUsersNotification->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', [
            'id' => $otherUsersNotification->id,
        ]);
    }

    /**
     * Create a predictable customer account for notification API checks.
     */
    private function createCustomerUser(string $email): User
    {
        return User::factory()
            ->for(Role::query()->where('slug', 'customer')->firstOrFail())
            ->create([
                'email' => $email,
                'is_verified' => true,
            ]);
    }

    /**
     * Insert one database notification row for the provided user.
     */
    private function createNotification(User $user, string $title, string $message, bool $isRead = false): object
    {
        $notificationId = (string) Str::uuid();

        $user->notifications()->create([
            'id' => $notificationId,
            'type' => 'tests.notification',
            'data' => [
                'event' => 'test_event',
                'title' => $title,
                'message' => $message,
                'url' => '/customer/bookings/1',
            ],
            'read_at' => $isRead ? now() : null,
        ]);

        return $user->notifications()->whereKey($notificationId)->firstOrFail();
    }
}
