<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Models\WorkerVerification;
use App\Notifications\BookingWorkflowNotification;
use App\Notifications\VerificationStatusNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkerVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Proves admins see unresolved pending worker verifications before processed records so reviews are not missed.
     */
    public function test_admin_verification_list_shows_pending_requests_first(): void
    {
        $this->seed(RoleSeeder::class);
        Sanctum::actingAs($this->adminUser());

        $approved = $this->verificationForWorker([
            'status' => WorkerVerification::STATUS_APPROVED,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        $newestProcessed = $this->verificationForWorker([
            'status' => WorkerVerification::STATUS_REJECTED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $olderPending = $this->verificationForWorker([
            'status' => WorkerVerification::STATUS_PENDING,
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);
        $newerPending = $this->verificationForWorker([
            'status' => WorkerVerification::STATUS_PENDING,
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);

        $this->getJson('/api/admin/worker-verifications')
            ->assertOk()
            ->assertJsonPath('data.verifications.0.id', $newerPending->id)
            ->assertJsonPath('data.verifications.1.id', $olderPending->id)
            ->assertJsonPath('data.verifications.2.id', $newestProcessed->id)
            ->assertJsonPath('data.verifications.3.id', $approved->id);
    }

    /**
     * Proves admins are warned when removing verification from a worker who still has active bookings.
     */
    public function test_admin_verification_list_shows_active_booking_count(): void
    {
        [$admin, $worker, $customer, $service] = $this->verifiedBookingActors();
        $verification = $this->approvedVerificationForWorker($worker);

        Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_COMPLETED,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/worker-verifications?status='.WorkerVerification::STATUS_APPROVED)
            ->assertOk()
            ->assertJsonPath('data.verifications.0.id', $verification->id)
            ->assertJsonPath('data.verifications.0.active_worker_bookings_count', 1);
    }

    /**
     * Proves admins can search the verification queue by worker identity without losing the pending-first ordering.
     */
    public function test_admin_verification_list_supports_search(): void
    {
        $this->seed(RoleSeeder::class);
        Sanctum::actingAs($this->adminUser());

        $matchingWorker = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create([
                'name' => 'Searchable Worker',
                'email' => 'searchable.worker@example.com',
            ]);

        $otherWorker = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create([
                'name' => 'Different Worker',
                'email' => 'different.worker@example.com',
            ]);

        $matchingVerification = $this->approvedVerificationForWorker($matchingWorker);
        $this->approvedVerificationForWorker($otherWorker);

        $this->getJson('/api/admin/worker-verifications?search=searchable')
            ->assertOk()
            ->assertJsonCount(1, 'data.verifications')
            ->assertJsonPath('data.verifications.0.id', $matchingVerification->id)
            ->assertJsonPath('data.verifications.0.worker.email', 'searchable.worker@example.com');
    }

    /**
     * Proves removing worker verification blocks new marketplace use but keeps current bookings active.
     */
    public function test_removing_worker_verification_keeps_active_bookings_and_notifies_customers(): void
    {
        Notification::fake();

        [$admin, $worker, $customer, $service] = $this->verifiedBookingActors();
        $verification = $this->approvedVerificationForWorker($worker);

        $activeBooking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        $completedBooking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_COMPLETED,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/worker-verifications/{$verification->id}/reject", [
            'rejection_reason' => 'Updated ID proof failed review.',
        ])
            ->assertOk()
            ->assertJsonPath('data.verification.status', WorkerVerification::STATUS_REJECTED)
            ->assertJsonPath('data.verification.active_worker_bookings_count', 1);

        $this->assertDatabaseHas('users', [
            'id' => $worker->id,
            'is_verified' => false,
        ]);

        $this->assertDatabaseHas('worker_profiles', [
            'user_id' => $worker->id,
            'is_verified' => false,
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $activeBooking->id,
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $completedBooking->id,
            'status' => Booking::STATUS_COMPLETED,
        ]);

        $this->assertDatabaseHas('booking_activities', [
            'booking_id' => $activeBooking->id,
            'actor_id' => $admin->id,
            'from_status' => Booking::STATUS_ACCEPTED,
            'to_status' => Booking::STATUS_ACCEPTED,
            'event' => 'worker_verification_removed',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'admin.worker_verification_rejected',
            'subject_type' => (new WorkerVerification)->getMorphClass(),
            'subject_id' => $verification->id,
        ]);

        Notification::assertSentTo(
            $customer,
            BookingWorkflowNotification::class,
            function (BookingWorkflowNotification $notification) use ($customer): bool {
                return $notification->toArray($customer)['event'] === 'worker_verification_removed';
            }
        );
    }

    public function test_admin_approval_notifies_worker_about_verification_status(): void
    {
        Notification::fake();

        $this->seed(RoleSeeder::class);
        [$admin, $worker] = [$this->adminUser(), $this->workerUser()];

        WorkerProfile::factory()->create([
            'user_id' => $worker->id,
            'is_verified' => false,
        ]);

        $verification = WorkerVerification::create([
            'user_id' => $worker->id,
            'id_proof' => 'worker-verifications/'.$worker->id.'/id-proof.pdf',
            'certificates' => [],
            'experience_years' => 5,
            'mobile_verified' => true,
            'status' => WorkerVerification::STATUS_PENDING,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/worker-verifications/{$verification->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.verification.status', WorkerVerification::STATUS_APPROVED);

        Notification::assertSentTo(
            $worker,
            VerificationStatusNotification::class,
            function (VerificationStatusNotification $notification) use ($worker): bool {
                return $notification->toArray($worker)['event'] === 'verification_status_updated';
            }
        );
    }

    /**
     * Proves new workers must provide identity proof before entering the admin verification queue.
     */
    public function test_new_worker_must_upload_id_proof_for_verification(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->workerUser());

        $this->post('/api/worker/verification', [
            'experience_years' => 4,
            'mobile_verified' => true,
        ], [
            'Accept' => 'application/json',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_proof');
    }

    /**
     * Proves workers can update certificates without replacing the existing identity proof on file.
     */
    public function test_worker_can_update_existing_verification_certificates_without_reuploading_id_proof(): void
    {
        Storage::fake('public');
        $worker = $this->workerUser();
        WorkerProfile::factory()->create([
            'user_id' => $worker->id,
            'is_verified' => true,
        ]);
        Storage::disk('public')->put('worker-verifications/'.$worker->id.'/old-proof.pdf', 'old proof');
        Storage::disk('public')->put('worker-verifications/'.$worker->id.'/certificates/old-certificate.pdf', 'old certificate');

        // Create an approved verification record so the test can confirm resubmission resets approval.
        WorkerVerification::create([
            'user_id' => $worker->id,
            'id_proof' => 'worker-verifications/'.$worker->id.'/old-proof.pdf',
            'certificates' => ['worker-verifications/'.$worker->id.'/certificates/old-certificate.pdf'],
            'experience_years' => 5,
            'mobile_verified' => true,
            'status' => WorkerVerification::STATUS_APPROVED,
            'verified_at' => now(),
        ]);

        Sanctum::actingAs($worker);

        $this->post('/api/worker/verification', [
            'certificates' => [
                UploadedFile::fake()->create('updated-certificate.pdf', 100, 'application/pdf'),
            ],
            'experience_years' => 6,
            'mobile_verified' => true,
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.verification.status', WorkerVerification::STATUS_PENDING)
            ->assertJsonPath('data.verification.experience_years', 6)
            ->assertJsonPath('data.verification.id_proof', 'worker-verifications/'.$worker->id.'/old-proof.pdf');

        Storage::disk('public')->assertExists('worker-verifications/'.$worker->id.'/old-proof.pdf');
        Storage::disk('public')->assertMissing('worker-verifications/'.$worker->id.'/certificates/old-certificate.pdf');

        $this->assertDatabaseHas('worker_profiles', [
            'user_id' => $worker->id,
            'is_verified' => false,
        ]);
    }

    /**
     * Proves workers can replace identity proof while keeping prior certificates for admin review.
     */
    public function test_worker_can_replace_existing_id_proof_without_replacing_certificates(): void
    {
        Storage::fake('public');
        $worker = $this->workerUser();
        Storage::disk('public')->put('worker-verifications/'.$worker->id.'/old-proof.pdf', 'old proof');

        // Create a pending verification record so the worker can replace only the identity proof document.
        WorkerVerification::create([
            'user_id' => $worker->id,
            'id_proof' => 'worker-verifications/'.$worker->id.'/old-proof.pdf',
            'certificates' => ['worker-verifications/'.$worker->id.'/certificates/existing-certificate.pdf'],
            'experience_years' => 5,
            'mobile_verified' => true,
            'status' => WorkerVerification::STATUS_PENDING,
        ]);

        Sanctum::actingAs($worker);

        $this->post('/api/worker/verification', [
            'id_proof' => UploadedFile::fake()->create('updated-proof.pdf', 100, 'application/pdf'),
            'experience_years' => 5,
            'mobile_verified' => true,
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.verification.status', WorkerVerification::STATUS_PENDING)
            ->assertJsonPath('data.verification.certificates.0.path', 'worker-verifications/'.$worker->id.'/certificates/existing-certificate.pdf');

        Storage::disk('public')->assertMissing('worker-verifications/'.$worker->id.'/old-proof.pdf');
    }

    /**
     * Create a worker account for worker-facing verification tests.
     */
    private function workerUser(): User
    {
        $this->seed(RoleSeeder::class);

        // Get a worker user because worker verification endpoints are role protected.
        return User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create();
    }

    /**
     * Create an admin account for admin verification review tests.
     */
    private function adminUser(): User
    {
        // Get an admin user because only admins can review worker verification records.
        return User::factory()
            ->for(Role::where('slug', 'admin')->firstOrFail())
            ->create();
    }

    /**
     * Create verified booking actors for admin verification removal tests.
     *
     * @return array{User, User, User, Service}
     */
    private function verifiedBookingActors(): array
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()
            ->for(Role::where('slug', 'admin')->firstOrFail())
            ->create();

        $worker = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create(['is_verified' => true]);

        WorkerProfile::factory()->create([
            'user_id' => $worker->id,
            'is_verified' => true,
        ]);

        $customer = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create();

        $service = Service::factory()->create(['is_active' => true]);

        return [$admin, $worker, $customer, $service];
    }

    /**
     * Create an approved verification record for a worker who previously passed admin review.
     */
    private function approvedVerificationForWorker(User $worker): WorkerVerification
    {
        return WorkerVerification::create([
            'user_id' => $worker->id,
            'id_proof' => 'worker-verifications/'.$worker->id.'/id-proof.pdf',
            'certificates' => [],
            'experience_years' => 5,
            'mobile_verified' => true,
            'status' => WorkerVerification::STATUS_APPROVED,
            'verified_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function verificationForWorker(array $overrides = []): WorkerVerification
    {
        // Get a worker account so each verification record appears in the admin worker review list.
        $worker = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create();

        // Create a verification record with realistic defaults so ordering can be tested clearly.
        return WorkerVerification::create([
            'user_id' => $worker->id,
            'id_proof' => 'worker-verifications/'.$worker->id.'/id-proof.pdf',
            'certificates' => [],
            'experience_years' => 5,
            'mobile_verified' => true,
            'status' => WorkerVerification::STATUS_PENDING,
            ...$overrides,
        ]);
    }
}
