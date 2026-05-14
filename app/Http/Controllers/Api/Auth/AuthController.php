<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\AuthService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly AuditLogger $audit,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        // Registration returns a token immediately so new users can complete onboarding steps.
        $result = $this->authService->register($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'token' => $result['token'],
                'user' => new UserResource($result['user']),
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            // Login creates a fresh API token for the authenticated session.
            $result = $this->authService->login($request->validated());
        } catch (ValidationException $exception) {
            // Invalid login attempts return field errors without exposing account existence.
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $result['token'],
                'user' => new UserResource($result['user']),
            ],
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->validated());

        // Password brokers can fail when the email is unknown or delivery cannot be started.
        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to send reset link',
                'errors' => [
                    'email' => [__($status)],
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset link sent',
            'data' => [],
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->validated(),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                // Password changes revoke existing API tokens so old sessions cannot continue.
                event(new PasswordReset($user));
            }
        );

        // Failed resets return broker errors using the API validation format.
        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to reset password',
                'errors' => $this->passwordResetFailureErrors($status),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful',
            'data' => [],
        ]);
    }

    /**
     * Convert password broker failures into clearer API validation messages.
     *
     * @return array<string, array<int, string>>
     */
    private function passwordResetFailureErrors(string $status): array
    {
        // A mismatched email and reset link should explain both possible causes clearly.
        if ($status === Password::INVALID_TOKEN) {
            return [
                'email' => ['This password reset link does not match that email address or has expired.'],
            ];
        }

        // Unknown email addresses should still point users back to the email field.
        if ($status === Password::INVALID_USER) {
            return [
                'email' => ['We could not find an account for that email address.'],
            ];
        }

        return [
            'email' => [__($status)],
        ];
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Logout only removes the current token so other devices can remain signed in.
        $request->user()?->currentAccessToken()?->delete();

        $this->audit->record('auth.logout', $user, $user);

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
            'data' => [],
        ]);
    }

    public function sendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verified users do not need another email challenge.
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Account already verified',
                'data' => [
                    'user' => new UserResource($user->load(['role', 'customerProfile', 'workerProfile', 'workerVerification'])),
                ],
            ]);
        }

        $user->sendEmailVerificationNotification();

        $this->audit->record('auth.email_verification_requested', $user, $user);

        return response()->json([
            'success' => true,
            'message' => 'Verification link sent to your email',
            'data' => [],
        ]);
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse|RedirectResponse
    {
        // Signed email verification links are resolved by user ID and hash.
        $user = User::query()->findOrFail($id);

        abort_unless(hash_equals((string) $hash, sha1($user->getEmailForVerification())), 403);

        // Marking email verified is idempotent so repeated link visits stay safe.
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
            $this->audit->record('auth.email_verified', $user, $user);
        }

        $user->load(['role', 'customerProfile', 'workerProfile', 'workerVerification']);

        // API clients expect JSON, while browser link clicks should land on the frontend confirmation page.
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully',
                'data' => [
                    'user' => new UserResource($user),
                ],
            ]);
        }

        return redirect('/email/verified');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['role', 'customerProfile', 'workerProfile', 'workerVerification']);

        return response()->json([
            'success' => true,
            'message' => 'Authenticated user retrieved',
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }
}
