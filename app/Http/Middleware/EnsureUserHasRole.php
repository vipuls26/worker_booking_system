<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user()?->loadMissing('role')->hasRole($role)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized role access',
                'errors' => [
                    'role' => ['You are not allowed to access this resource.'],
                ],
            ], 403);
        }

        return $next($request);
    }
}
