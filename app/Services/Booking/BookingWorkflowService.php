<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingActivity;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BookingWorkflowService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function transition(Booking $booking, string $nextStatus, ?User $actor = null, ?string $note = null, array $context = []): Booking
    {
        $this->assertTransitionAllowed($booking, $nextStatus);

        $oldStatus = $booking->status;
        $updates = ['status' => $nextStatus];

        if ($nextStatus === Booking::STATUS_REJECTED) {
            $updates['rejection_reason'] = $note;
        }

        if ($nextStatus === Booking::STATUS_CANCELLED) {
            $updates['cancelled_by'] = $actor?->id;
            $updates['cancelled_reason'] = $note;
        }

        $booking->update($updates);

        $this->record(
            booking: $booking,
            fromStatus: $oldStatus,
            toStatus: $nextStatus,
            event: $context['event'] ?? $this->eventName($nextStatus),
            actor: $actor,
            note: $note,
        );

        return $booking->refresh();
    }

    public function record(Booking $booking, ?string $fromStatus, string $toStatus, string $event, ?User $actor = null, ?string $note = null): BookingActivity
    {
        return $booking->activities()->create([
            'actor_id' => $actor?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'event' => $event,
            'note' => $note,
        ]);
    }

    public function assertCustomerCanCancel(Booking $booking): void
    {
        if (! in_array($booking->status, [Booking::STATUS_REQUESTED, Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only requested, pending, or confirmed bookings can be cancelled by the customer.'],
            ]);
        }
    }

    private function assertTransitionAllowed(Booking $booking, string $nextStatus): void
    {
        $allowed = [
            Booking::STATUS_REQUESTED => [Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING, Booking::STATUS_CANCELLED],
            Booking::STATUS_PENDING => [Booking::STATUS_ACCEPTED, Booking::STATUS_REJECTED, Booking::STATUS_CANCELLED],
            Booking::STATUS_CONFIRMED => [Booking::STATUS_IN_PROGRESS, Booking::STATUS_CANCELLED],
            Booking::STATUS_ACCEPTED => [Booking::STATUS_IN_PROGRESS, Booking::STATUS_CANCELLED],
            Booking::STATUS_IN_PROGRESS => [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED],
            Booking::STATUS_REJECTED => [],
            Booking::STATUS_COMPLETED => [],
            Booking::STATUS_CANCELLED => [],
        ];

        if (! in_array($nextStatus, $allowed[$booking->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ['This booking cannot move to the selected status.'],
            ]);
        }
    }

    private function eventName(string $status): string
    {
        return match ($status) {
            Booking::STATUS_PENDING => 'worker_selected',
            Booking::STATUS_CONFIRMED => 'booking_confirmed',
            Booking::STATUS_ACCEPTED => 'booking_accepted',
            Booking::STATUS_REJECTED => 'booking_rejected',
            Booking::STATUS_IN_PROGRESS => 'work_started',
            Booking::STATUS_COMPLETED => 'work_completed',
            Booking::STATUS_CANCELLED => 'booking_cancelled',
            default => 'booking_updated',
        };
    }
}
