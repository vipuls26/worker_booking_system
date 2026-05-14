<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(300)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->string('email')->lower()->toString().'|'.$request->ip());
        });

        RateLimiter::for('booking-actions', function (Request $request) {
            return Limit::perMinute(45)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('worker-search', function (Request $request) {
            return Limit::perMinute(90)->by($request->user()?->id ?: $request->ip());
        });

        Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $accessToken, bool $isValid): bool {
            // API bearer tokens must come from the browser SPA flow and remain inside their expiry window.
            if (! $isValid) {
                return false;
            }

            return $accessToken->name === 'frontend-spa' && $accessToken->can('spa');
        });

        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return url('/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->email,
            ]));
        });

        Vite::prefetch(concurrency: 3);
    }
}
