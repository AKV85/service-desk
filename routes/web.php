<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return 'Service Desk Dashboard';
})->middleware('auth')->name('dashboard');