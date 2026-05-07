<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingActivity>
 */
class BookingActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'actor_id' => User::factory(),
            'from_status' => null,
            'to_status' => Booking::STATUS_PENDING,
            'event' => 'booking_created',
            'note' => null,
        ];
    }
}
