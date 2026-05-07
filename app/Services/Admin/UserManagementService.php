<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Support\Filters\UserFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function __construct(private readonly UserFilter $filter) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = User::query()
            ->with('role')
            ->whereDoesntHave('role', fn ($query) => $query->where('slug', 'admin'))
            ->latest();

        return $this->filter
            ->apply($query, $request)
            ->paginate($request->integer('per_page', 15));
    }

    public function block(User $user): User
    {
        $this->ensureNotAdmin($user, 'Admin accounts cannot be blocked.');

        $user->update(['is_blocked' => true]);

        return $user->refresh()->load('role');
    }

    public function unblock(User $user): User
    {
        $this->ensureNotAdmin($user, 'Admin accounts cannot be unblocked from user management.');

        $user->update(['is_blocked' => false]);

        return $user->refresh()->load('role');
    }

    /**
     * @throws ValidationException
     */
    public function delete(User $user, User $admin): void
    {
        if ($user->is($admin)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own admin account.'],
            ]);
        }

        $this->ensureNotAdmin($user, 'Admin accounts cannot be deleted from user management.');

        $user->delete();
    }

    /**
     * @throws ValidationException
     */
    private function ensureNotAdmin(User $user, string $message): void
    {
        if ($user->loadMissing('role')->hasRole('admin')) {
            throw ValidationException::withMessages([
                'user' => [$message],
            ]);
        }
    }
}
