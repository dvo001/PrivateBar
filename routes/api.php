<?php

use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/sync', [SyncController::class, 'exchange'])->middleware('throttle:120,1');
Route::match(['get', 'post'], '/v1/media', [SyncController::class, 'media'])->middleware('throttle:120,1');

Route::post('/v1/recovery', [SyncController::class, 'recovery'])->middleware('throttle:5,30');

Route::get('/v1/device-check', [SyncController::class, 'check'])->middleware('throttle:10,1');
