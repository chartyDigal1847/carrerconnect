<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CareerConnect Web Routes
|--------------------------------------------------------------------------
| Main UI route — serves the single-page application
|--------------------------------------------------------------------------
*/

// Root route - serves the main CareerConnect SPA
Route::get('/', function () {
    return view('careerconnect');
});

// SPA fallback — must not capture API, broadcasting, or health routes
Route::get('/{any}', function () {
    return view('careerconnect');
})->where('any', '^(?!api(?:/|$)|broadcasting|up).*$');

