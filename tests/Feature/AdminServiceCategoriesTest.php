<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminServiceCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_service_category_with_generated_slug(): void
    {
        $admin = $this->adminUser();

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/services', [
            'name' => 'AC Repair',
            'description' => 'Air conditioner repair service.',
            'icon' => 'pi-sun',
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service.name', 'AC Repair')
            ->assertJsonPath('data.service.slug', 'ac-repair')
            ->assertJsonPath('data.service.creator.id', $admin->id);

        $this->assertDatabaseHas('services', [
            'name' => 'AC Repair',
            'slug' => 'ac-repair',
            'created_by' => $admin->id,
        ]);
    }

    public function test_admin_can_search_filter_paginate_and_toggle_service_categories(): void
    {
        Sanctum::actingAs($this->adminUser());

        Service::factory()->create([
            'name' => 'Electrician',
            'slug' => 'electrician',
            'description' => 'Wire and switch repairs.',
            'is_active' => true,
        ]);

        $inactive = Service::factory()->inactive()->create([
            'name' => 'TV Repair',
            'slug' => 'tv-repair',
            'description' => 'Screen and sound repairs.',
        ]);

        $this->getJson('/api/admin/services?search=screen&is_active=0&per_page=5')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.services.0.id', $inactive->id)
            ->assertJsonPath('data.meta.per_page', 5);

        $this->patchJson("/api/admin/services/{$inactive->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service.is_active', true);
    }

    public function test_admin_can_update_and_soft_delete_service_category(): void
    {
        Sanctum::actingAs($this->adminUser());

        $service = Service::factory()->create([
            'name' => 'Cleaner',
            'slug' => 'cleaner',
        ]);

        $this->putJson("/api/admin/services/{$service->id}", [
            'name' => 'Home Cleaner',
            'description' => 'Full home cleaning.',
            'icon' => 'pi-sparkles',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.service.name', 'Home Cleaner')
            ->assertJsonPath('data.service.slug', 'home-cleaner')
            ->assertJsonPath('data.service.is_active', false);

        $this->deleteJson("/api/admin/services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Service deleted');

        $this->assertSoftDeleted('services', ['id' => $service->id]);
    }

    public function test_non_admin_cannot_manage_service_categories(): void
    {
        $this->seed(RoleSeeder::class);

        Sanctum::actingAs(
            User::factory()->for(Role::where('slug', 'customer')->firstOrFail())->create(),
        );

        $this->getJson('/api/admin/services')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    private function adminUser(): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()
            ->for(Role::where('slug', 'admin')->firstOrFail())
            ->create();
    }
}
