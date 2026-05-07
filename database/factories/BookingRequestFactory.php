<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingRequest>
 */
class BookingRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->state(['worker_id' => null, 'status' => Booking::STATUS_REQUESTED]),
            'worker_id' => User::factory()->for(Role::factory()->state(['name' => 'Worker', 'slug' => 'worker'])),
            'status' => BookingRequest::STATUS_PENDING,
            'responded_at' => null,
        ];
    }
}
