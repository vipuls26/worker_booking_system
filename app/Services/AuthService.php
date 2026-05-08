<?php

namespace App\Services;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
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
            'token' => $user->createToken('api-token')->plainTextToken,
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
        $user = User::query()
            ->with(['role', 'customerProfile', 'workerProfile', 'workerVerification'])
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $this->audit->record('auth.login', $user, $user);

        return [
            'user' => $user,
            'token' => $user->createToken('api-token')->plainTextToken,
        ];
    }
}
