<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Protected API routes require an authenticated user before email checks apply.
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'errors' => [],
            ], 401);
        }

        // Email verification is required before users can access booking-sensitive features.
        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email is not verified',
                'errors' => [
                    'email' => ['Please verify your email before using this feature.'],
                ],
            ], 403);
        }

        return $next($request);
    }
}
