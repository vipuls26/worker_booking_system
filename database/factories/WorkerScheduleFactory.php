<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkerSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkerSchedule>
 */
class WorkerScheduleFactory extends Factory
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
            'day_of_week' => fake()->numberBetween(1, 6),
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_off_day' => false,
        ];
    }

    public function offDay(): self
    {
        return $this->state(fn (): array => [
            'start_time' => null,
            'end_time' => null,
            'is_off_day' => true,
        ]);
    }
}
