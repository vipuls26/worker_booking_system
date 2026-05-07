<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkerService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkerService>
 */
class WorkerServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'worker_id' => User::factory()->for(Role::factory()->state([
                'name' => 'Worker',
                'slug' => 'worker',
            ])),
            'service_id' => Service::factory(),
            'pricing_type' => WorkerService::PricingFixed,
            'price' => fake()->numberBetween(300, 2000),
            'minimum_hours' => null,
            'description' => fake()->sentence(),
            'is_active' => true,
            'approval_status' => WorkerService::StatusApproved,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function hourly(): self
    {
        return $this->state(fn (): array => [
            'pricing_type' => WorkerService::PricingHourly,
            'price' => fake()->numberBetween(200, 800),
            'minimum_hours' => fake()->numberBetween(1, 4),
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn (): array => [
            'is_active' => false,
            'approval_status' => WorkerService::StatusPending,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn (): array => [
            'is_active' => false,
            'approval_status' => WorkerService::StatusRejected,
            'rejection_reason' => fake()->sentence(),
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
