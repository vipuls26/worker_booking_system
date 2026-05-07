<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformUserIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->loadMissing(['role', 'workerProfile', 'workerVerification']);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'errors' => [],
            ], 401);
        }

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        if (! $user->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Account is waiting for platform verification',
                'errors' => [
                    'verification' => ['An admin must approve your account before you can use platform services.'],
                ],
            ], 403);
        }

        if ($user->hasRole('worker') && ! $user->workerProfile?->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Worker profile is waiting for admin verification',
                'errors' => [
                    'verification' => ['An admin must approve your worker profile before you can use worker services.'],
                ],
            ], 403);
        }

        return $next($request);
    }
}
