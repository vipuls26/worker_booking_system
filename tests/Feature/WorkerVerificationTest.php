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

    public function test_worker_can_replace_existing_id_proof_without_replacing_certificates(): void
    {
        Storage::fake('public');
        $worker = $this->workerUser();
        Storage::disk('public')->put('worker-verifications/'.$worker->id.'/old-proof.pdf', 'old proof');

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

    private function workerUser(): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create();
    }
}
