<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TicketAttachmentController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/tickets', [TicketController::class, 'index'])
        ->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])
        ->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])
        ->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])
        ->name('tickets.show');
    Route::get('/tickets/{ticket}/edit', [TicketController::class, 'edit'])
        ->name('tickets.edit');
    Route::put('/tickets/{ticket}', [TicketController::class, 'update'])
        ->name('tickets.update');
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])
        ->name('tickets.status.update');
    Route::patch('/tickets/{ticket}/priority', [TicketController::class, 'updatePriority'])
        ->name('tickets.priority.update');
    Route::patch('/tickets/{ticket}/assignee', [TicketController::class, 'assign'])
        ->name('tickets.assignee.update');
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'storeComment'])
        ->name('tickets.comments.store');
    Route::post('/tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store'])
        ->name('tickets.attachments.store');
    Route::get('/tickets/{ticket}/attachments/{attachment}/download', [TicketAttachmentController::class, 'download'])
        ->name('tickets.attachments.download');
});
