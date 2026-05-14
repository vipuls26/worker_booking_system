<?php

namespace App\Services;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const FRONTEND_TOKEN_ABILITY = 'spa';

    private const FRONTEND_TOKEN_EXPIRATION_HOURS = 12;

    private const FRONTEND_TOKEN_NAME = 'frontend-spa';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{role_id: int, name: string, email: string, phone: string, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'role_id' => $data['role_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
        ])->load(['role', 'customerProfile', 'workerProfile', 'workerVerification']);

        event(new Registered($user));

        $this->audit->record('auth.registered', $user, $user, [
            'role' => $user->role?->slug,
        ]);

        return [
            'user' => $user,
            'token' => $this->issueFrontendToken($user),
        ];
    }

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        // Login needs the user and profile context returned with the token response.
        $user = User::query()
            ->with(['role', 'customerProfile', 'workerProfile', 'workerVerification'])
            ->where('email', $credentials['email'])
            ->first();

        // Failed credentials should not reveal whether the email exists.
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $this->audit->record('auth.login', $user, $user);

        return [
            'user' => $user,
            'token' => $this->issueFrontendToken($user),
        ];
    }

    /**
     * Issue a browser session token that is scoped to the SPA and expires automatically.
     */
    private function issueFrontendToken(User $user): string
    {
        return $user->createToken(
            self::FRONTEND_TOKEN_NAME,
            [self::FRONTEND_TOKEN_ABILITY],
            now()->addHours(self::FRONTEND_TOKEN_EXPIRATION_HOURS),
        )->plainTextToken;
    }
}
