<?php

namespace App\Services\Payment;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkerPayout;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly WorkerPayoutService $payouts,
    ) {}

    /**
     * @param  array{provider?: string|null, transaction_reference?: string|null}  $data
     */
    public function payForServiceRequest(ServiceRequest $serviceRequest, User $customer, array $data = []): Payment
    {
        abort_if($serviceRequest->customer_id !== $customer->id, 404);

        $booking = $serviceRequest->booking;

        // Customers must select a worker before checkout can charge for the final booking.
        if (! $booking) {
            throw ValidationException::withMessages([
                'booking' => ['Select a worker before paying for this booking.'],
            ]);
        }

        return $this->pay($booking, $customer, $data);
    }

    /**
     * @param  array{provider?: string|null, transaction_reference?: string|null}  $data
     */
    public function pay(Booking $booking, User $customer, array $data = []): Payment
    {
        abort_if($booking->customer_id !== $customer->id, 404);

        return DB::transaction(function () use ($booking, $customer, $data): Payment {
            // Lock the booking so concurrent checkout attempts cannot create duplicate payments.
            $booking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            // A booking can be paid only once.
            if ($booking->payment_status === Booking::PAYMENT_PAID) {
                throw ValidationException::withMessages([
                    'payment' => ['This booking has already been paid.'],
                ]);
            }

            // The platform collects payment only after service completion.
            if ($booking->status !== Booking::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'payment' => ['Payment is available only after the service is completed.'],
                ]);
            }

            $payment = Payment::create([
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'worker_id' => $booking->worker_id,
                'amount' => $booking->quoted_amount,
                'commission_rate' => $booking->quoted_commission_rate ?: Booking::DefaultCommissionRate,
                'platform_commission' => $booking->quoted_platform_commission,
                'worker_earning' => $booking->quoted_worker_earning,
                'provider' => $data['provider'] ?? 'manual',
                'transaction_reference' => $data['transaction_reference'] ?? 'SIM-'.Str::upper(Str::random(12)),
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
                'metadata' => [
                    'source' => 'customer_checkout',
                    'booking_status' => $booking->status,
                ],
            ]);

            $booking->update([
                'payment_status' => Booking::PAYMENT_PAID,
                'paid_at' => $payment->paid_at,
            ]);

            $this->audit->record('payment.paid', $customer, $payment, [
                'booking_id' => $booking->id,
                'amount' => $payment->amount,
                'platform_commission' => $payment->platform_commission,
                'worker_earning' => $payment->worker_earning,
            ]);

            return $payment->refresh()->load(['booking.service', 'customer.role', 'worker.role']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function adminSummary(): array
    {
        // Admin revenue totals are based only on settled payments.
        $paidPayments = Payment::query()->where('status', Payment::STATUS_PAID);

        return [
            'gross_revenue' => round((float) (clone $paidPayments)->sum('amount'), 2),
            'platform_commission' => round((float) (clone $paidPayments)->sum('platform_commission'), 2),
            'worker_earnings' => round((float) (clone $paidPayments)->sum('worker_earning'), 2),
            'paid_bookings' => (clone $paidPayments)->count(),
            'recent_payments' => Payment::query()
                ->with(['booking.service', 'customer.role', 'worker.role'])
                ->where('status', Payment::STATUS_PAID)
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function workerSummary(User $worker): array
    {
        // Worker earning summaries use paid customer payments and completed payout records.
        $paidPayments = Payment::query()
            ->where('worker_id', $worker->id)
            ->where('status', Payment::STATUS_PAID);
        $totalEarned = round((float) (clone $paidPayments)->sum('worker_earning'), 2);
        $platformCommission = round((float) (clone $paidPayments)->sum('platform_commission'), 2);
        $paidOut = round((float) $worker->workerPayouts()->where('status', WorkerPayout::STATUS_PAID)->sum('amount'), 2);

        return [
            'total_earned' => $totalEarned,
            'platform_commission' => $platformCommission,
            'paid_out' => $paidOut,
            'paid_bookings' => (clone $paidPayments)->count(),
            'pending_payout' => max(round($totalEarned - $paidOut, 2), 0),
            'recent_payments' => $this->workerPayments($worker),
            'recent_payouts' => $this->payouts->workerPayouts($worker),
        ];
    }

    /**
     * @return Collection<int, Payment>
     */
    public function workerPayments(User $worker): Collection
    {
        // Workers see their most recent settled customer payments.
        return Payment::query()
            ->with(['booking.service', 'customer.role'])
            ->where('worker_id', $worker->id)
            ->where('status', Payment::STATUS_PAID)
            ->latest()
            ->limit(10)
            ->get();
    }
}
