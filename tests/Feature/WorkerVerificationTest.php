<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Models\WorkerVerification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
