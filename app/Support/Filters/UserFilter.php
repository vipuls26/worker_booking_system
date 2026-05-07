<?php

namespace App\Support\Filters;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserFilter
{
    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function apply(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('role'), function (Builder $query) use ($request): void {
                $query->whereHas('role', fn (Builder $roleQuery): Builder => $roleQuery->where('slug', $request->string('role')->toString()));
            })
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
    }
}
