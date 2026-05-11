<?php

namespace App\Services\Account;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProfileService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $emailChanged = $user->email !== $data['email'];

            $user->fill(Arr::only($data, ['name', 'email', 'phone']));

            // Changing email requires re-verification before sensitive account workflows continue.
            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();

            // Send the new verification challenge immediately after the email update is saved.
            if ($emailChanged) {
                $user->sendEmailVerificationNotification();
            }

            // Customer addresses are stored on the customer profile for booking defaults.
            if ($user->hasRole('customer')) {
                $user->customerProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['address' => $data['address'] ?? null],
                );
            }

            return $user->refresh()->load(['role', 'customerProfile', 'workerProfile', 'workerVerification']);
        });
    }
}
