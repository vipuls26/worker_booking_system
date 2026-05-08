<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexAuditLogsRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\User;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    public function __invoke(IndexAuditLogsRequest $request): JsonResponse
    {
        $auditLogs = $this->baseQuery($request)
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->response($auditLogs);
    }

    public function user(IndexAuditLogsRequest $request, User $user): JsonResponse
    {
        $auditLogs = $this->baseQuery($request)
            ->where(function ($query) use ($user): void {
                $query->where('actor_id', $user->id)
                    ->orWhere(function ($query) use ($user): void {
                        $query->where('subject_type', User::class)
                            ->where('subject_id', $user->id);
                    });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->response($auditLogs);
    }

    public function booking(IndexAuditLogsRequest $request, Booking $booking): JsonResponse
    {
        $auditLogs = $this->baseQuery($request)
            ->where(function ($query) use ($booking): void {
                $query->where(function ($query) use ($booking): void {
                    $query->where('subject_type', Booking::class)
                        ->where('subject_id', $booking->id);
                })->orWhere('metadata->booking_id', $booking->id);
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->response($auditLogs);
    }

    private function baseQuery(IndexAuditLogsRequest $request)
    {
        return AuditLog::query()
            ->with('actor.role')
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('action', 'like', "%{$search}%")
                        ->orWhere('subject_type', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('actor', function ($query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->string('action')->toString(), fn ($query, string $action) => $query->where('action', $action))
            ->when($request->string('actor_role')->toString(), fn ($query, string $role) => $query->where('actor_role', $role))
            ->when($request->date('date_from'), fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date('date_to'), fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
    }

    private function response($auditLogs): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Audit logs retrieved',
            'data' => [
                'audit_logs' => AuditLogResource::collection($auditLogs),
                'meta' => PaginationMeta::fromPaginator($auditLogs),
            ],
        ]);
    }
}
