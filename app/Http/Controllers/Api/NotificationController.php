<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Notification lists are scoped to the signed-in user.
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved',
            'data' => [
                'notifications' => NotificationResource::collection($notifications),
                'unread_count' => $request->user()->unreadNotifications()->count(),
                'meta' => PaginationMeta::fromPaginator($notifications),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        // The frontend can poll this lightweight count without loading the full notification list.
        return response()->json([
            'success' => true,
            'message' => 'Unread notifications count retrieved',
            'data' => [
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        // A notification can be marked read only by the user who owns it.
        $notification = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => [
                'notification' => new NotificationResource($notification->refresh()),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        // Marking all as read clears the user's notification badge in one request.
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read',
            'data' => [
                'unread_count' => 0,
            ],
        ]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        // Clearing a notification is scoped to the owner to avoid cross-account deletion.
        $notification = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification cleared',
            'data' => [
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        // Clear-all removes only the signed-in user's notification history.
        $request->user()->notifications()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifications cleared',
            'data' => [
                'unread_count' => 0,
            ],
        ]);
    }
}
