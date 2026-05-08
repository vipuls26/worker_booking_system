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
            $result = $this->authService->login($request->validated());
        } catch (ValidationException $exception) {
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

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to reset password',
                'errors' => [
                    'email' => [__($status)],
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful',
            'data' => [],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

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
        $user = User::query()->findOrFail($id);

        abort_unless(hash_equals((string) $hash, sha1($user->getEmailForVerification())), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
            $this->audit->record('auth.email_verified', $user, $user);
        }

        $user->load(['role', 'customerProfile', 'workerProfile', 'workerVerification']);

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
