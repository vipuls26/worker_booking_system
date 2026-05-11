<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\WorkerVerification;
use App\Services\Audit\AuditLogger;
use App\Support\Filters\UserFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function __construct(
        private readonly UserFilter $filter,
        private readonly AuditLogger $audit,
    ) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        // User management excludes admin accounts so staff accounts are handled through safer admin-only flows.
        $query = User::query()
            ->with(['role', 'customerProfile'])
            ->whereDoesntHave('role', fn ($query) => $query->where('slug', 'admin'))
            ->latest();

        return $this->filter
            ->apply($query, $request)
            ->paginate($request->integer('per_page', 15));
    }

    public function block(User $user): User
    {
        $this->ensureNotAdmin($user, 'Admin accounts cannot be blocked.');

        $user->forceFill([
            'is_blocked' => true,
            'is_verified' => false,
            'email_verified_at' => null,
        ])->save();

        // Blocking a worker also removes marketplace verification until an admin re-approves them.
        if ($user->loadMissing('role')->hasRole('worker')) {
            $user->workerProfile()->updateOrCreate(['user_id' => $user->id], [
                'is_verified' => false,
            ]);
        }

        $this->audit->record('admin.user_blocked', request()->user(), $user, [
            'email_verification_reset' => true,
            'admin_approval_reset' => true,
        ]);

        return $user->refresh()->load(['role', 'customerProfile', 'workerProfile']);
    }

    public function unblock(User $user): User
    {
        $this->ensureNotAdmin($user, 'Admin accounts cannot be unblocked from user management.');

        $user->update(['is_blocked' => false]);

        $this->audit->record('admin.user_unblocked', request()->user(), $user);

        return $user->refresh()->load(['role', 'customerProfile']);
    }

    public function verify(User $user): User
    {
        $this->ensureNotAdmin($user, 'Admin accounts cannot be verified from user management.');
        $this->ensureEmailVerified($user);
        $this->ensureWorkerVerificationApproved($user);

        $user->update(['is_verified' => true]);

        // Worker approval must keep the profile flag in sync for booking eligibility checks.
        if ($user->hasRole('worker')) {
            $user->workerProfile()->updateOrCreate(['user_id' => $user->id], [
                'is_verified' => true,
            ]);
        }

        $this->audit->record('admin.user_verified', request()->user(), $user);

        return $user->refresh()->load(['role', 'customerProfile', 'workerProfile', 'workerVerification']);
    }

    /**
     * @throws ValidationException
     */
    public function delete(User $user, User $admin): void
    {
        // Admins cannot delete themselves because the platform needs at least one active operator path.
        if ($user->is($admin)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own admin account.'],
            ]);
        }

        $this->ensureNotAdmin($user, 'Admin accounts cannot be deleted from user management.');

        $this->audit->record('admin.user_deleted', $admin, $user, [
            'deleted_user_email' => $user->email,
        ]);

        $user->delete();
    }

    /**
     * @throws ValidationException
     */
    private function ensureNotAdmin(User $user, string $message): void
    {
        // Admin accounts are protected from customer/worker management actions.
        if ($user->loadMissing('role')->hasRole('admin')) {
            throw ValidationException::withMessages([
                'user' => [$message],
            ]);
        }
    }

    private function ensureEmailVerified(User $user): void
    {
        // Email verification proves the user can receive account and booking notifications.
        if ($user->hasVerifiedEmail()) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => ['User must verify their email before admin approval.'],
        ]);
    }

    private function ensureWorkerVerificationApproved(User $user): void
    {
        // Customers do not need worker document approval before account verification.
        if (! $user->loadMissing('role')->hasRole('worker')) {
            return;
        }

        // Workers must have approved ID proof before admins mark the account platform-verified.
        $isApproved = $user->workerVerification()
            ->where('status', WorkerVerification::STATUS_APPROVED)
            ->exists();

        // Approved verification is enough to continue account approval.
        if ($isApproved) {
            return;
        }

        throw ValidationException::withMessages([
            'worker_verification' => ['Approve this worker ID proof before approving the user account.'],
        ]);
    }
}
