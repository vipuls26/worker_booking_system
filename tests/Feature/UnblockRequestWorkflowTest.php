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
     * Approval must fully restore a partially blocked user in the same transaction.
     */
    public function test_admin_approval_restores_partially_blocked_user_and_notifies_them(): void
    {
        Notification::fake();

        [$admin, $blockedUser] = $this->reviewUsers(User::STATUS_PARTIALLY_BLOCKED);
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
            'account_status' => User::STATUS_ACTIVE,
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

    public function test_admin_approval_fully_restores_fully_blocked_user(): void
    {
        Notification::fake();

        [$admin, $blockedUser] = $this->reviewUsers(User::STATUS_FULLY_BLOCKED);
        $unblockRequest = UnblockRequest::factory()->create([
            'user_id' => $blockedUser->id,
            'status' => UnblockRequest::STATUS_PENDING,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/unblock-requests/{$unblockRequest->id}/approve", [
            'admin_note' => 'Start reverification.',
        ])
            ->assertOk()
            ->assertJsonPath('data.unblock_request.status', UnblockRequest::STATUS_APPROVED)
            ->assertJsonPath('data.unblock_request.needs_reverification', false);

        $this->assertDatabaseHas('users', [
            'id' => $blockedUser->id,
            'account_status' => User::STATUS_ACTIVE,
            'is_blocked' => false,
        ]);

        Notification::assertSentTo(
            $blockedUser,
            UnblockRequestReviewedNotification::class,
            function (UnblockRequestReviewedNotification $notification) use ($blockedUser): bool {
                $payload = $notification->toArray($blockedUser);

                return $payload['event'] === 'unblock_request_approved'
                    && $payload['needs_reverification'] === false
                    && $payload['account_status'] === User::STATUS_ACTIVE;
            }
        );
    }

    /**
     * Rejection must keep the account blocked and still notify the user of the decision.
     */
    public function test_admin_rejection_keeps_user_blocked_and_notifies_them(): void
    {
        Notification::fake();

        [$admin, $blockedUser] = $this->reviewUsers(User::STATUS_FULLY_BLOCKED);
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
            'account_status' => User::STATUS_FULLY_BLOCKED,
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
     * Proves admins can narrow unblock appeals by both search text and workflow status.
     */
    public function test_admin_can_filter_unblock_requests_by_search_and_status(): void
    {
        [$admin, $blockedUser] = $this->reviewUsers(User::STATUS_FULLY_BLOCKED);
        $otherBlockedUser = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create([
                'name' => 'Another Customer',
                'email' => 'another.customer@example.com',
                'account_status' => User::STATUS_FULLY_BLOCKED,
                'is_blocked' => true,
            ]);

        $matchingRequest = UnblockRequest::factory()->create([
            'user_id' => $blockedUser->id,
            'reason' => 'Please restore my account after payment review.',
            'status' => UnblockRequest::STATUS_PENDING,
        ]);

        UnblockRequest::factory()->create([
            'user_id' => $otherBlockedUser->id,
            'reason' => 'I need help with another issue.',
            'status' => UnblockRequest::STATUS_REJECTED,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/unblock-requests?status=pending&search=payment')
            ->assertOk()
            ->assertJsonCount(1, 'data.unblock_requests')
            ->assertJsonPath('data.unblock_requests.0.id', $matchingRequest->id)
            ->assertJsonPath('data.unblock_requests.0.user.email', $blockedUser->email);
    }

    /**
     * Create the admin and blocked customer used by unblock review tests.
     *
     * @return array{User, User}
     */
    private function reviewUsers(string $accountStatus = User::STATUS_FULLY_BLOCKED): array
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()
            ->for(Role::where('slug', 'admin')->firstOrFail())
            ->create();

        $blockedUser = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create([
                'account_status' => $accountStatus,
                'is_blocked' => $accountStatus === User::STATUS_FULLY_BLOCKED,
            ]);

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
            ->create([
                'account_status' => User::STATUS_FULLY_BLOCKED,
                'is_blocked' => true,
            ]);
    }
}
