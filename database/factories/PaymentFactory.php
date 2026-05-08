<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $booking = Booking::factory()->create();

        return [
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'worker_id' => $booking->worker_id,
            'amount' => $booking->quoted_amount,
            'commission_rate' => $booking->quoted_commission_rate,
            'platform_commission' => $booking->quoted_platform_commission,
            'worker_earning' => $booking->quoted_worker_earning,
            'provider' => 'manual',
            'transaction_reference' => 'SIM-'.Str::upper(Str::random(12)),
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
        ];
    }
}
