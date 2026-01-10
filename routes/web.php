<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\PageController;
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

// === Protected and Role-based Routes ===

/*
    routes\roles\admin.php
    routes\roles\manager.php
    routes\roles\member.php
    routes\roles\loan.php
    routes\roles\secretary.php
    routes\roles\.php
*/
