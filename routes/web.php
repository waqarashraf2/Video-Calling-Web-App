<?php

use App\Http\Controllers\BroadcastAuthController;
use App\Http\Controllers\CsrfTokenController;
use App\Http\Controllers\GuestSessionController;
use App\Http\Controllers\MatchmakingController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SafetyController;
use App\Http\Controllers\SignalingController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/csrf-token', [CsrfTokenController::class, 'show'])->name('csrf-token');

Route::prefix('api')->group(function () {
    Route::get('online', [GuestSessionController::class, 'online']);
    Route::post('guest-sessions', [GuestSessionController::class, 'store'])->middleware('throttle:videochat-join');
    Route::post('guest-sessions/heartbeat', [GuestSessionController::class, 'heartbeat'])->middleware('throttle:videochat-heartbeat');
    Route::get('state', [GuestSessionController::class, 'state']);
    Route::get('matchmaking/available', [MatchmakingController::class, 'available']);
    Route::post('matchmaking/join', [MatchmakingController::class, 'join'])->middleware('throttle:videochat-join');
    Route::post('matchmaking/call', [MatchmakingController::class, 'call'])->middleware('throttle:videochat-join');
    Route::delete('matchmaking', [MatchmakingController::class, 'leave']);
    Route::post('rooms/leave', [RoomController::class, 'leave']);
    Route::post('rooms/next', [RoomController::class, 'next'])->middleware('throttle:videochat-skip');
    Route::post('rooms/retry', [RoomController::class, 'retry'])->middleware('throttle:videochat-join');
    Route::get('signals', [SignalingController::class, 'index'])->middleware('throttle:videochat-signal');
    Route::post('signals', [SignalingController::class, 'store'])->middleware('throttle:videochat-signal');
    Route::post('reports', [SafetyController::class, 'report'])->middleware('throttle:videochat-report');
    Route::post('blocks', [SafetyController::class, 'block'])->middleware('throttle:videochat-report');
});

Route::post('/broadcasting/auth', BroadcastAuthController::class)->middleware('throttle:videochat-signal');
