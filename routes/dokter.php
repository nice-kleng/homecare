<?php

use Illuminate\Support\Facades\Route;

Route::get('/dashboard', function () {
    return inertia('Dokter/Dashboard');
})->name('dashboard');
