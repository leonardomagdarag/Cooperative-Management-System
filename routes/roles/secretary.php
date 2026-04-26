<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

// Accessible only to secretary
Route::prefix('secretary')->middleware(['auth', 'otp.verified', 'role:secretary'])->group(function () {
    // secretary Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('secretary.dashboard');
});
