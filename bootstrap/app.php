<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsurePlatformUserIsVerified;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsNotBlocked;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

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
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $exception): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (AuthenticationException $exception): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'errors' => [],
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $exception): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'This action is unauthorized',
                'errors' => [],
            ], 403);
        });

        $exceptions->render(function (ValidationException $exception): JsonResponse {
            $firstErrorMessage = collect($exception->errors())->flatten()->first() ?: 'Validation failed';

            return response()->json([
                'success' => false,
                'message' => $firstErrorMessage,
                'errors' => $exception->errors(),
            ], $exception->status);
        });

        $exceptions->render(function (NotFoundHttpException $exception): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
                'errors' => [],
            ], 404);
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please try again shortly.',
                'errors' => [],
            ], 429);
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
