<?php

namespace Database\Factories;

use App\Models\DisputeStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisputeStatusHistory>
 */
class DisputeStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dispute_id' => null,
            'actor_id' => User::factory(),
            'from_status' => null,
            'to_status' => 'open',
            'note' => fake()->sentence(),
        ];
    }
}
