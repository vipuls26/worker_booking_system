<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBlocked
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Blocked accounts cannot continue booking or marketplace activity.
        if ($request->user()?->is_blocked) {
            return response()->json([
                'success' => false,
                'message' => 'Account blocked',
                'errors' => [
                    'account' => ['Your account has been blocked by admin.'],
                ],
            ], 403);
        }

        return $next($request);
    }
}
