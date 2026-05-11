<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\UnblockRequest;
use App\Models\User;
use App\Notifications\UnblockRequestReviewedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnblockRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Blocked users may have only one pending unblock appeal at a time.
     */
    public function test_blocked_user_cannot_create_duplicate_pending_unblock_request(): void
    {
        $blockedUser = $this->blockedCustomer();

        UnblockRequest::factory()->create([
            'user_id' => $blockedUser->id,
            'status' => UnblockRequest::STATUS_PENDING,
        ]);

        Sanctum::actingAs($blockedUser);

        $this->postJson('/api/account/unblock-request', [
            'reason' => 'Please unblock my account after review.',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.request.0', 'You already have a pending unblock request.');

        $this->assertDatabaseCount('unblock_requests', 1);
    }

    /**
     * Approval must unblock the user in the same transaction that records the admin decision.
     */
    public function test_admin_approval_unblocks_user_and_notifies_them(): void
    {
        Notification::fake();

        [$admin, $blockedUser] = $this->reviewUsers();
        $unblockRequest = UnblockRequest::factory()->create([
            'user_id' => $blockedUser->id,
            'status' => UnblockRequest::STATUS_PENDING,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/unblock-requests/{$unblockRequest->id}/approve", [
            'admin_note' => 'Appeal accepted.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unblock_request.status', UnblockRequest::STATUS_APPROVED);

        $this->assertDatabaseHas('unblock_requests', [
            'id' => $unblockRequest->id,
            'status' => UnblockRequest::STATUS_APPROVED,
            'admin_note' => 'Appeal accepted.',
            'reviewed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $blockedUser->id,
            'is_blocked' => false,
        ]);

        Notification::assertSentTo(
            $blockedUser,
            UnblockRequestReviewedNotification::class,
            function (UnblockRequestReviewedNotification $notification) use ($blockedUser): bool {
                $payload = $notification->toArray($blockedUser);

                return $payload['event'] === 'unblock_request_approved'
                    && $payload['status'] === UnblockRequest::STATUS_APPROVED;
            }
        );
    }

    /**
     * Rejection must keep the account blocked and still notify the user of the decision.
     */
    public function test_admin_rejection_keeps_user_blocked_and_notifies_them(): void
    {
        Notification::fake();

        [$admin, $blockedUser] = $this->reviewUsers();
        $unblockRequest = UnblockRequest::factory()->create([
            'user_id' => $blockedUser->id,
            'status' => UnblockRequest::STATUS_PENDING,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/unblock-requests/{$unblockRequest->id}/reject", [
            'admin_note' => 'Please provide more details.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unblock_request.status', UnblockRequest::STATUS_REJECTED);

        $this->assertDatabaseHas('users', [
            'id' => $blockedUser->id,
            'is_blocked' => true,
        ]);

        Notification::assertSentTo(
            $blockedUser,
            UnblockRequestReviewedNotification::class,
            function (UnblockRequestReviewedNotification $notification) use ($blockedUser): bool {
                $payload = $notification->toArray($blockedUser);

                return $payload['event'] === 'unblock_request_rejected'
                    && $payload['status'] === UnblockRequest::STATUS_REJECTED
                    && $payload['admin_note'] === 'Please provide more details.';
            }
        );
    }

    /**
     * Create the admin and blocked customer used by unblock review tests.
     *
     * @return array{User, User}
     */
    private function reviewUsers(): array
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()
            ->for(Role::where('slug', 'admin')->firstOrFail())
            ->create();

        $blockedUser = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create(['is_blocked' => true]);

        return [$admin, $blockedUser];
    }

    /**
     * Create a blocked customer who can submit an unblock request.
     */
    private function blockedCustomer(): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create(['is_blocked' => true]);
    }
}
