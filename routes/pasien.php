<?php

use Illuminate\Support\Facades\Route;

Route::get('/dashboard', function () {
    return inertia('Pasien/Dashboard');
})->name('dashboard');
