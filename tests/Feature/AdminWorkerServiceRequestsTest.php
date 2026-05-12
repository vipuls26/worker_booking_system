<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkerService;
use App\Models\WorkerVerification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminWorkerServiceRequestsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Proves admins only review worker service requests after identity documents are approved.
     */
    public function test_admin_worker_service_requests_only_show_admin_verified_workers(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->adminUser();

        Sanctum::actingAs($admin);

        $approvedWorkerService = WorkerService::factory()->pending()->create([
            'worker_id' => $this->workerUser()->id,
        ]);

        WorkerVerification::query()->create([
            'user_id' => $approvedWorkerService->worker_id,
            'id_proof' => 'worker-verifications/approved-proof.pdf',
            'certificates' => ['worker-verifications/approved-certificate.pdf'],
            'experience_years' => 5,
            'mobile_verified' => true,
            'status' => WorkerVerification::STATUS_APPROVED,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        $pendingWorkerService = WorkerService::factory()->pending()->create([
            'worker_id' => $this->workerUser()->id,
        ]);

        WorkerVerification::query()->create([
            'user_id' => $pendingWorkerService->worker_id,
            'id_proof' => 'worker-verifications/pending-proof.pdf',
            'certificates' => ['worker-verifications/pending-certificate.pdf'],
            'experience_years' => 3,
            'mobile_verified' => true,
            'status' => WorkerVerification::STATUS_PENDING,
        ]);

        $this->getJson('/api/admin/worker-service-requests')
            ->assertOk()
            ->assertJsonPath('data.worker_services.0.id', $approvedWorkerService->id)
            ->assertJsonCount(1, 'data.worker_services');
    }

    /**
     * Create an admin user for protected admin API tests.
     */
    private function adminUser(): User
    {
        return User::factory()
            ->for(Role::where('slug', 'admin')->firstOrFail())
            ->create([
                'is_verified' => true,
            ]);
    }

    /**
     * Create a worker user for worker service approval scenarios.
     */
    private function workerUser(): User
    {
        return User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create([
                'is_verified' => true,
            ]);
    }
}
