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
        // Fully blocked accounts cannot access protected platform routes until admin review is complete.
        if ($request->user()?->isFullyBlocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Account fully blocked',
                'errors' => [
                    'account' => ['Your account has been fully blocked by admin.'],
                ],
            ], 403);
        }

        return $next($request);
    }
}
