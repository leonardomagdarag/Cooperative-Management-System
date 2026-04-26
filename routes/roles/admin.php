<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

// Accessible only to admin
Route::prefix('admin')->middleware(['auth', 'otp.verified', 'role:admin'])->group(function () {
    // Admin Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
});
