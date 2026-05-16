<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeckController;
use App\Http\Controllers\Api\V1\DevController;
use App\Http\Controllers\Api\V1\MatchController;
use App\Http\Controllers\Api\V1\MatchmakingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/decks', [DeckController::class, 'index']);

        Route::post('/matchmaking/join', [MatchmakingController::class, 'join']);
        Route::delete('/matchmaking/leave', [MatchmakingController::class, 'leave']);
        Route::get('/matchmaking/status', [MatchmakingController::class, 'status']);

        Route::get('/matches/{id}', [MatchController::class, 'show']);
        Route::post('/matches/{id}/action', [MatchController::class, 'action']);
        Route::post('/matches/{id}/reconnect', [MatchController::class, 'reconnect']);

        Route::post('/dev/pair-queue', [DevController::class, 'pairQueue']);
    });
});
