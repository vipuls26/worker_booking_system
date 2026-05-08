<?php

namespace Database\Factories;

use App\Models\Dispute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dispute>
 */
class DisputeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => null,
            'service_request_id' => null,
            'opened_by' => User::factory(),
            'against_user_id' => User::factory(),
            'category' => 'service_issue',
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => Dispute::STATUS_OPEN,
        ];
    }
}
