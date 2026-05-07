<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Support\Filters\BookingFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class BookingManagementService
{
    public function __construct(private readonly BookingFilter $filter) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = Booking::query()
            ->with(['customer.role', 'worker.role', 'service', 'cancelledBy.role'])
            ->latest();

        return $this->filter
            ->apply($query, $request)
            ->paginate($request->integer('per_page', 15));
    }

    public function cancel(Booking $booking, User $admin, string $reason): Booking
    {
        $booking->update([
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_by' => $admin->id,
            'cancelled_reason' => $reason,
        ]);

        return $booking->refresh()->load(['customer.role', 'worker.role', 'service', 'cancelledBy.role']);
    }
}
