<?php

declare(strict_types=1);

use App\Http\Controllers\Api\TimeEntryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn (Request $request) => $request->user())
    ->middleware('auth:sanctum');

Route::post('/time-entries', [TimeEntryController::class, 'store'])
    ->middleware(['auth:sanctum', 'ability:time-entries:create', 'throttle:60,1']);
