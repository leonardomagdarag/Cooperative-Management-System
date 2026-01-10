<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

// Accessible only to member
Route::prefix('member')->middleware(['auth', 'role:member'])->group(function () {
    // member Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('member.dashboard');
});