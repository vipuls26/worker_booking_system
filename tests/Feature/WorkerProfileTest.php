<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkerProfile;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_can_get_default_profile(): void
    {
        Sanctum::actingAs($worker = $this->workerUser());

        $this->getJson('/api/worker/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profile.user_id', $worker->id)
            ->assertJsonPath('data.profile.skills', []);

        $this->assertDatabaseHas('worker_profiles', [
            'user_id' => $worker->id,
            'experience_years' => 0,
        ]);
    }

    public function test_worker_can_update_profile_with_photo(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($worker = $this->workerUser());

        $this->post('/api/worker/profile', [
            'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
            'bio' => 'Experienced home electrician.',
            'experience_years' => 8,
            'address' => '123 Main Street',
            'city' => 'Mumbai',
            'latitude' => '19.0760000',
            'longitude' => '72.8777000',
            'skills' => ['Electrical', 'AC Repair'],
            'phone' => '9000002222',
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profile.city', 'Mumbai')
            ->assertJsonPath('data.profile.skills.0', 'Electrical')
            ->assertJsonPath('data.profile.user.phone', '9000002222');

        $profile = WorkerProfile::whereBelongsTo($worker)->firstOrFail();

        Storage::disk('public')->assertExists($profile->profile_photo);

        $this->assertDatabaseHas('worker_profiles', [
            'user_id' => $worker->id,
            'city' => 'Mumbai',
            'experience_years' => 8,
        ]);
    }

    public function test_customer_cannot_access_worker_profile(): void
    {
        $this->seed(RoleSeeder::class);

        Sanctum::actingAs(
            User::factory()->for(Role::where('slug', 'customer')->firstOrFail())->create(),
        );

        $this->getJson('/api/worker/profile')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    private function workerUser(): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create();
    }
}
