<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->state(['status' => Booking::STATUS_COMPLETED]),
            'customer_id' => User::factory()->for(Role::factory()->state(['name' => 'Customer', 'slug' => 'customer'])),
            'worker_id' => User::factory()->for(Role::factory()->state(['name' => 'Worker', 'slug' => 'worker'])),
            'type' => Review::TypeCustomerToWorker,
            'rating' => fake()->numberBetween(1, 5),
            'review' => fake()->paragraph(),
        ];
    }

    public function workerToCustomer(): self
    {
        return $this->state(fn (): array => [
            'type' => Review::TypeWorkerToCustomer,
        ]);
    }
}
