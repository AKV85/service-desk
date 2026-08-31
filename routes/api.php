<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

Route::post('/tokens', [ApiTokenController::class, 'store'])
    ->middleware('throttle:api-token');

Route::middleware('auth:sanctum')->group(function () {
    Route::delete('/tokens/current', [ApiTokenController::class, 'destroy']);

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::put('/tickets/{ticket}', [TicketController::class, 'update']);
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus']);
    Route::patch('/tickets/{ticket}/priority', [TicketController::class, 'updatePriority']);
    Route::patch('/tickets/{ticket}/assignee', [TicketController::class, 'assign']);
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'storeComment']);
});
