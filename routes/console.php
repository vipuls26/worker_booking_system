<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Provide a quick console sanity command for local framework checks.
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process weekly payout records after the work week closes.
Schedule::command('payouts:process-weekly')
    ->weeklyOn(0, '23:00')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping();

// Expire stale service requests so workers are not held by old requests.
Schedule::command('service-requests:cancel-expired')
    ->hourly()
    ->withoutOverlapping();
