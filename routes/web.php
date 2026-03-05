<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

// === Public Routes ===

Route::prefix('/')->group(function () {
    // Landing Page
    Route::get('/', [
        PageController::class,
        'home',
    ])->name('home_page');

    // about Page
    Route::get('/about', [
        PageController::class,
        'about',
    ])->name('about_page');

    // Events Page
    Route::get('/events', [
        PageController::class,
        'events',
    ])->name('events_page');

    // Stories Page
    Route::get('/stories', [
        PageController::class,
        'stories',
    ])->name('stories_page');

    // Login Page
    Route::get('/login', [
        PageController::class,
        'login',
    ])->name('login_page');
});

// === Authentication Routes ===

// Handle Login Submission
Route::post('/login', [LoginController::class, 'login'])->name('login');
// Handle Logout
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// === Password Reset Routes ===
Route::get('/forgot-password', [PasswordResetController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password', [PasswordResetController::class, 'showResetForm'])->name('password.reset.form');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

// === API Routes for Notifications (Protected) ===
Route::middleware('auth')->prefix('api')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications/read/all', [NotificationController::class, 'deleteAllRead']);
});

// === Protected and Role-based Routes ===

/*
    routes\roles\admin.php
    routes\roles\manager.php
    routes\roles\member.php
    routes\roles\loan.php
    routes\roles\secretary.php
    routes\roles\.php
*/
