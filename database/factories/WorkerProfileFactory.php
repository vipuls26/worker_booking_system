<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkerProfile>
 */
class WorkerProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->for(Role::factory()->state([
                'name' => 'Worker',
                'slug' => 'worker',
            ])),
            'profile_photo' => null,
            'bio' => fake()->paragraph(),
            'experience_years' => fake()->numberBetween(1, 20),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'skills' => fake()->randomElements(['Electrical', 'Plumbing', 'Cleaning', 'Repair', 'Installation'], 3),
            'is_verified' => false,
        ];
    }
}
