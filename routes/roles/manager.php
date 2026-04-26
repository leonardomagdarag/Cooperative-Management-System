<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Accessible only to manager
Route::prefix('manager')->middleware(['auth', 'otp.verified', 'role:manager'])->group(function () {
    // manager Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('manager.dashboard');
});
