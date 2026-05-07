<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('spa');
});

Route::get('/{any}', function () {
    return view('spa');
})->where('any', '^(?!api).*$');
