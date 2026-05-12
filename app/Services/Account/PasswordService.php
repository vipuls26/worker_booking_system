<?php

namespace App\Services\Account;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PasswordService
{
    /**
     * Update the authenticated user's password and return the refreshed user model.
     *
     * @param  array{current_password: string, password: string, password_confirmation?: string}  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            // Save the new password so future authenticated requests use the latest credential.
            $user->forceFill([
                'password' => $data['password'],
            ])->save();

            return $user->refresh()->load(['role', 'customerProfile', 'workerProfile', 'workerVerification']);
        });
    }
}
