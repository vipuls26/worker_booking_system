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

        // Platform verification applies only after authentication succeeds.
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'errors' => [],
            ], 401);
        }

        // Admins bypass user verification gates because they operate the review workflow itself.
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // Customers and workers need admin platform approval before using protected features.
        if (! $user->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Account is waiting for platform verification',
                'errors' => [
                    'verification' => ['An admin must approve your account before you can use platform services.'],
                ],
            ], 403);
        }

        // Worker marketplace actions require the worker profile to be approved too.
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
