<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function view(User $user, Payment $payment): bool
    {
        return $payment->customer_id === $user->id || $payment->worker_id === $user->id;
    }
}
