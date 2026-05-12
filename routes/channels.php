<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes([
    'prefix' => 'api',
    'middleware' => ['auth:sanctum'],
]);

// Private user channels protect personal notifications and workflow updates.
Broadcast::channel('users.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Admin dashboards can subscribe only after admin channel authorization succeeds.
Broadcast::channel('admin.dashboard', function ($user) {
    return $user->hasRole('admin');
});
