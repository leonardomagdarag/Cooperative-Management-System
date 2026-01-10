<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

// Accessible only to treasurer
Route::prefix('treasurer')->middleware(['auth', 'role:treasurer'])->group(function () {
    // treasurer Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('treasurer.dashboard');
});