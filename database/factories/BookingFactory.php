<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d');
        $startTime = fake()->randomElement(['09:00', '10:00', '11:00', '14:00', '15:00']);

        $totalAmount = fake()->numberBetween(300, 2000);
        $platformCommission = round($totalAmount * (Booking::DefaultCommissionRate / 100), 2);

        return [
            'customer_id' => User::factory()->for(Role::factory()->state(['name' => 'Customer', 'slug' => 'customer'])),
            'worker_id' => User::factory()->for(Role::factory()->state(['name' => 'Worker', 'slug' => 'worker'])),
            'selected_worker_id' => null,
            'service_id' => Service::factory(),
            'booking_date' => $date,
            'booking_time' => $startTime,
            'start_time' => $startTime,
            'end_time' => now()->parse($date.' '.$startTime)->addHour()->format('H:i'),
            'address' => fake()->address(),
            'notes' => fake()->sentence(),
            'issue_description' => fake()->sentence(),
            'total_amount' => $totalAmount,
            'commission_rate' => Booking::DefaultCommissionRate,
            'platform_commission' => $platformCommission,
            'worker_earning' => round($totalAmount - $platformCommission, 2),
            'status' => Booking::STATUS_PENDING,
        ];
    }
}
