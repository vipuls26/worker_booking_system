<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Filters\ServiceFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceManagementService
{
    public function __construct(
        private readonly ServiceFilter $filter,
        private readonly AuditLogger $audit,
    ) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        // Service administration lists categories with creator context for auditability.
        $query = Service::query()
            ->with('creator:id,name,email,role_id')
            ->latest();

        return $this->filter
            ->apply($query, $request)
            ->paginate($request->integer('per_page', 15));
    }

    /**
     * @param  array{name: string, description?: string|null, icon?: string|null, is_active?: bool}  $data
     */
    public function create(array $data, User $admin): Service
    {
        return Service::create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $admin->id,
        ])->load('creator');
    }

    /**
     * @param  array{name: string, description?: string|null, icon?: string|null, is_active: bool}  $data
     */
    public function update(Service $service, array $data): Service
    {
        $service->update([
            ...$data,
            'slug' => $service->name === $data['name'] ? $service->slug : $this->uniqueSlug($data['name'], $service->id),
        ]);

        return $service->refresh()->load('creator');
    }

    /**
     * Soft delete a service only when bookings are safe or the admin has confirmed force deletion.
     */
    public function delete(Service $service, User $admin, bool $force = false): void
    {
        DB::transaction(function () use ($service, $admin, $force): void {
            // Active bookings must keep running, so admins must explicitly force service deletion.
            $activeBookings = $service->bookings()
                ->whereIn('status', Booking::ActiveStatuses)
                ->get();

            if ($activeBookings->isNotEmpty() && ! $force) {
                throw ValidationException::withMessages([
                    'service' => ['This service has active bookings. Use force=true to soft delete it while keeping existing bookings active.'],
                ]);
            }

            $service->delete();

            // Existing bookings continue, but their timeline should show that the service category was removed.
            foreach ($activeBookings as $booking) {
                $booking->activities()->create([
                    'actor_id' => $admin->id,
                    'from_status' => $booking->status,
                    'to_status' => $booking->status,
                    'event' => 'service_soft_deleted',
                    'note' => 'Service soft deleted by admin; existing booking continues.',
                ]);
            }

            $this->audit->record('admin.service_deleted', $admin, $service, [
                'force' => $force,
                'active_bookings_count' => $activeBookings->count(),
            ]);
        });
    }

    public function toggleStatus(Service $service): Service
    {
        $service->update([
            'is_active' => ! $service->is_active,
        ]);

        return $service->refresh()->load('creator');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        // Service slugs must remain unique even when a deleted category used the name before.
        while (Service::withTrashed()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
