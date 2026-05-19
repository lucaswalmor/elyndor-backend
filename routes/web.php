<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/log-temp-debug', function () {
    return response()->file(storage_path('logs/game-balance-2026-05-19.log'));
});