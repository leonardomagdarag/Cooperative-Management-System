<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OfficerController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\DevPanels\DevPanelController;


// === Public Routes ===

// Home Page

Route::prefix('/')->group(function () {
    Route::get('/', [
        PageController::class,
        'home'
    ])->name('home_page');

    //about Page
    Route::get('/about', [
        PageController::class,
        'about'
    ])->name('about_page');

    //Events Page
    Route::get('/events', [
        PageController::class,
        'events'
    ])->name('events_page');

    //Stories Page
    Route::get('/stories', [
        PageController::class,
        'stories'
    ])->name('stories_page');

    // Login Page
    Route::get('/login', [
        PageController::class,
        'login'
    ])->name('login_page');
});

// === Authentication Routes ===

// Handle Login Submission
Route::post('/login', [AuthController::class, 'login'])->name('login');
// Handle Logout
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');


// === Protected and Role-based Routes ===

// DEVELOPER PANEL FOR DEVELOPMENT ONLY
Route::prefix('dev')->middleware(['auth', "role:developer"])->group(function () {
    // Subject to change. Just for testing purposes
    Route::get('/dev_panel', [DevPanelController::class, 'index'])->name('dev.panel');
    Route::post('/dev_panel/users/combined-store', [DevPanelController::class, 'storeCombinedUser'])->name('dev.users.combined.store');
    Route::post('/dev_panel/users/combined-update', [DevPanelController::class, 'updateCombinedUser'])->name('dev.users.combined.update');
    Route::post('/dev_panel/users/combined-delete', [DevPanelController::class, 'deleteCombinedUser'])->name('dev.users.combined.delete');

    // Generic single-table CRUD for other tables
    Route::post('/dev_panel/store', [DevPanelController::class, 'store'])->name('dev.store');
    Route::post('/dev_panel/update', [DevPanelController::class, 'update'])->name('dev.update');
    Route::post('/dev_panel/delete', [DevPanelController::class, 'destroy'])->name('dev.delete');
});


// Accessible only to admin
Route::prefix('admin')->middleware(['auth', "role:admin"])->group(function () {
    // Admin Dashboard
    Route::get('/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
});

// Accessible for officers and admins
Route::prefix('officer')->middleware(['auth', "role:developer,admin,officer,treasurer,secretary"])->group(function () {
    Route::get('/dashboard', [OfficerController::class, 'index'])->name('officer.dashboard');
});

// Accessible for members
Route::get('/dashboard', [MemberController::class, 'index'])
    ->middleware(['auth', "role:member, developer"]);
