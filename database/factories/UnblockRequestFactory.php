<?php

namespace Database\Factories;

use App\Models\UnblockRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnblockRequest>
 */
class UnblockRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reason' => $this->faker->sentence(),
            'status' => UnblockRequest::STATUS_PENDING,
            'admin_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
