<?php

use Illuminate\Support\Facades\Route;

// Serve the Vue single-page app at the site root.
Route::get('/', function () {
    return view('spa');
});

// Let Vue Router handle every non-API browser route.
Route::get('/{any}', function () {
    return view('spa');
})->where('any', '^(?!api).*$');
