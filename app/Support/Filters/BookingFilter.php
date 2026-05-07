<?php

namespace App\Support\Filters;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BookingFilter
{
    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function apply(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('address', 'like', "%{$search}%")
                        ->orWhere('issue_description', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $userQuery): Builder => $userQuery->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('worker', fn (Builder $userQuery): Builder => $userQuery->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('service', fn (Builder $serviceQuery): Builder => $serviceQuery->where('name', 'like', "%{$search}%"));
                });
            });
    }
}
