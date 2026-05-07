<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsurePlatformUserIsVerified;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsNotBlocked;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'not.blocked' => EnsureUserIsNotBlocked::class,
            'role' => EnsureUserHasRole::class,
            'verified' => EnsureEmailIsVerified::class,
            'platform.verified' => EnsurePlatformUserIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'errors' => [],
            ], 401);
        });

        $exceptions->render(function (InvalidSignatureException $exception) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification link is invalid or expired',
                    'errors' => [
                        'signature' => ['Request a new verification link and try again.'],
                    ],
                ], 403);
            }

            return redirect('/email/verified?status=expired');
        });
    })->create();
