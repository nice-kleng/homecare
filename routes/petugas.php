<?php

use Illuminate\Support\Facades\Route;

Route::get('/dashboard', function () {
    return inertia('Petugas/Dashboard');
})->name('dashboard');
