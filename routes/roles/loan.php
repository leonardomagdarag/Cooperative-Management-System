<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Accessible only to loan
Route::prefix('loan')->middleware(['auth', 'otp.verified', 'role:loan'])->group(function () {
    // loan Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('loan.dashboard');
});
