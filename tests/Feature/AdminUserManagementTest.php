<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkerVerification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Proves admin verification flips the customer account into the approved platform state.
     */
    public function test_admin_can_verify_customer_with_verified_email(): void
    {
        $this->seed(RoleSeeder::class);

        $adminUser = $this->createAdminUser('admin-verify-customer@example.com');
        $customerUser = $this->createCustomerUser('customer-to-verify@example.com');

        Sanctum::actingAs($adminUser);

        $this->patchJson("/api/admin/users/{$customerUser->id}/verify")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User verified')
            ->assertJsonPath('data.user.id', $customerUser->id)
            ->assertJsonPath('data.user.is_admin_verified', true);

        $this->assertDatabaseHas('users', [
            'id' => $customerUser->id,
            'is_verified' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $adminUser->id,
            'action' => 'admin.user_verified',
            'subject_id' => $customerUser->id,
        ]);
    }

    /**
     * Proves worker approval is blocked until the worker verification record is approved.
     */
    public function test_admin_cannot_verify_worker_without_approved_worker_verification(): void
    {
        $this->seed(RoleSeeder::class);

        $adminUser = $this->createAdminUser('admin-verify-worker@example.com');
        $workerUser = $this->createWorkerUser('worker-unapproved@example.com');

        WorkerVerification::query()->create([
            'user_id' => $workerUser->id,
            'id_proof' => 'worker-proofs/unapproved.pdf',
            'certificates' => ['worker-certificates/unapproved.pdf'],
            'experience_years' => 3,
            'mobile_verified' => true,
            'status' => WorkerVerification::STATUS_PENDING,
        ]);

        Sanctum::actingAs($adminUser);

        $this->patchJson("/api/admin/users/{$workerUser->id}/verify")
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unable to verify user')
            ->assertJsonPath('errors.worker_verification.0', 'Approve this worker ID proof before approving the user account.');

        $this->assertDatabaseHas('users', [
            'id' => $workerUser->id,
            'is_verified' => false,
        ]);
    }

    /**
     * Proves admin deletion removes a non-admin user and stores an audit trail.
     */
    public function test_admin_can_delete_non_admin_user(): void
    {
        $this->seed(RoleSeeder::class);

        $adminUser = $this->createAdminUser('admin-delete@example.com');
        $customerUser = $this->createCustomerUser('delete-target@example.com');

        Sanctum::actingAs($adminUser);

        $this->deleteJson("/api/admin/users/{$customerUser->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User deleted');

        $this->assertDatabaseMissing('users', [
            'id' => $customerUser->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $adminUser->id,
            'action' => 'admin.user_deleted',
            'subject_id' => $customerUser->id,
        ]);
    }

    /**
     * Proves admins cannot delete their own account from the moderation screen.
     */
    public function test_admin_cannot_delete_their_own_account(): void
    {
        $this->seed(RoleSeeder::class);

        $adminUser = $this->createAdminUser('self-delete-admin@example.com');

        Sanctum::actingAs($adminUser);

        $this->deleteJson("/api/admin/users/{$adminUser->id}")
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unable to delete user')
            ->assertJsonPath('errors.user.0', 'You cannot delete your own admin account.');

        $this->assertDatabaseHas('users', [
            'id' => $adminUser->id,
        ]);
    }

    /**
     * Create an admin account for user management API checks.
     */
    private function createAdminUser(string $email): User
    {
        return User::factory()
            ->for(Role::query()->where('slug', 'admin')->firstOrFail())
            ->create([
                'email' => $email,
                'is_verified' => true,
            ]);
    }

    /**
     * Create a verified-email customer that still needs admin approval.
     */
    private function createCustomerUser(string $email): User
    {
        return User::factory()
            ->for(Role::query()->where('slug', 'customer')->firstOrFail())
            ->create([
                'email' => $email,
                'is_verified' => false,
            ]);
    }

    /**
     * Create a verified-email worker that still needs admin approval.
     */
    private function createWorkerUser(string $email): User
    {
        return User::factory()
            ->for(Role::query()->where('slug', 'worker')->firstOrFail())
            ->create([
                'email' => $email,
                'is_verified' => false,
            ]);
    }
}
