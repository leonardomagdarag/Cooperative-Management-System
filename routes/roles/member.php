<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Livewire\Content\Dashboards\MemberLoans;

// Accessible only to member
Route::prefix('member')->middleware(['auth', 'role:member'])->group(function () {
    // member Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('member.dashboard');

    // member Loans
    Route::get('/loans', MemberLoans::class)->name('member.loans');
});
