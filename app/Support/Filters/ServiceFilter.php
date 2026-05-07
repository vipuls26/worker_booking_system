<?php

namespace App\Support\Filters;

use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ServiceFilter
{
    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function apply(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), function (Builder $query) use ($request): void {
                $query->where('is_active', $request->boolean('is_active'));
            });
    }
}
