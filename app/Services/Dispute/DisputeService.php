<?php

namespace App\Services\Dispute;

use App\Models\Booking;
use App\Models\Dispute;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DisputeService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        // Admins need a filtered dispute queue with all people and booking context needed for triage.
        return Dispute::query()
            ->with($this->relations())
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('openedBy', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('againstUser', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function paginateForUser(User $user, int $perPage = 10): LengthAwarePaginator
    {
        // Customers and workers should only see disputes where they are one of the involved parties.
        return Dispute::query()
            ->with($this->relations())
            ->where(function ($query) use ($user): void {
                $query->where('opened_by', $user->id)
                    ->orWhere('against_user_id', $user->id);
            })
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Dispute
    {
        // Disputes are opened against a specific booking so policy checks can confirm party access.
        $booking = Booking::query()
            ->with(['serviceRequest', 'customer.role', 'worker.role'])
            ->findOrFail($data['booking_id']);

        Gate::forUser($actor)->authorize('create', [Dispute::class, $booking]);

        // A booking can have only one active dispute to keep resolution ownership clear.
        if ($booking->disputes()->whereNotIn('status', [Dispute::STATUS_RESOLVED, Dispute::STATUS_REJECTED])->exists()) {
            throw ValidationException::withMessages([
                'booking_id' => ['This booking already has an active dispute.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $booking, $data): Dispute {
            $againstUserId = $actor->id === $booking->customer_id ? $booking->worker_id : $booking->customer_id;

            $dispute = Dispute::create([
                'booking_id' => $booking->id,
                'service_request_id' => $booking->service_request_id,
                'opened_by' => $actor->id,
                'against_user_id' => $againstUserId,
                'category' => $data['category'],
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => Dispute::STATUS_OPEN,
            ]);

            $this->recordStatus($dispute, $actor, null, Dispute::STATUS_OPEN, 'Dispute opened.');

            $this->audit->record('dispute.created', $actor, $dispute, [
                'booking_id' => $booking->id,
                'against_user_id' => $againstUserId,
                'category' => $dispute->category,
            ]);

            return $dispute->refresh()->load($this->relations());
        });
    }

    public function updateStatus(Dispute $dispute, User $admin, string $status, ?string $resolutionNote = null): Dispute
    {
        Gate::forUser($admin)->authorize('resolve', $dispute);

        return DB::transaction(function () use ($dispute, $admin, $status, $resolutionNote): Dispute {
            $oldStatus = $dispute->status;

            // Resolution statuses close the dispute and capture the admin responsible for the decision.
            $dispute->update([
                'status' => $status,
                'assigned_admin_id' => $dispute->assigned_admin_id ?: $admin->id,
                'resolved_by' => in_array($status, [Dispute::STATUS_RESOLVED, Dispute::STATUS_REJECTED], true) ? $admin->id : null,
                'resolved_at' => in_array($status, [Dispute::STATUS_RESOLVED, Dispute::STATUS_REJECTED], true) ? now() : null,
                'resolution_note' => $resolutionNote,
            ]);

            $this->recordStatus($dispute, $admin, $oldStatus, $status, $resolutionNote);

            $this->audit->record('dispute.status_changed', $admin, $dispute, [
                'from_status' => $oldStatus,
                'to_status' => $status,
            ]);

            return $dispute->refresh()->load($this->relations());
        });
    }

    /**
     * @return array<int, string>
     */
    public function relations(): array
    {
        return [
            'booking.customer.role',
            'booking.worker.role',
            'booking.service',
            'serviceRequest.service',
            'openedBy.role',
            'againstUser.role',
            'assignedAdmin.role',
            'resolvedBy.role',
            'statusHistory.actor.role',
        ];
    }

    private function recordStatus(Dispute $dispute, ?User $actor, ?string $fromStatus, string $toStatus, ?string $note = null): void
    {
        $dispute->statusHistory()->create([
            'actor_id' => $actor?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
        ]);
    }
}
