<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkerPayout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkerPayout>
 */
class WorkerPayoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'worker_id' => User::factory()->for(Role::factory()->state(['name' => 'Worker', 'slug' => 'worker'])),
            'processed_by' => null,
            'amount' => fake()->numberBetween(500, 5000),
            'status' => WorkerPayout::STATUS_PENDING,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'reference' => null,
            'notes' => null,
            'paid_at' => null,
        ];
    }
}
