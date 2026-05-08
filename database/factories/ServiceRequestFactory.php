<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'service_id' => Service::factory(),
            'selected_worker_id' => null,
            'booking_id' => null,
            'requested_date' => now()->addDay()->toDateString(),
            'start_time' => '16:00',
            'end_time' => '18:00',
            'address' => fake()->address(),
            'description' => fake()->sentence(),
            'estimated_amount' => fake()->numberBetween(300, 1500),
            'status' => ServiceRequest::STATUS_OPEN,
        ];
    }
}
