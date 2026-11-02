<?php

use Illuminate\Support\Facades\Route;

// A short landing instead of Laravel's welcome page. The API docs link only
// shows where Scramble serves them (APP_ENV=local by default).
Route::get('/', fn () => view('home', [
    'docsUrl' => Route::has('scramble.docs.ui') ? route('scramble.docs.ui') : null,
]));
