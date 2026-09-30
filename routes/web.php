<?php

use App\Http\Controllers\ConfirmablePasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OtpChallengeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Support\MaintenanceState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

    // News Page
    Route::get('/news', [
        PageController::class,
        'news',
    ])->name('news_page');

    // Stories Page
    Route::get('/stories', [
        PageController::class,
        'stories',
    ])->name('stories_page');

    // Login Page
    Route::get('/login', [
        PageController::class,
        'login',
    ])->middleware('public.login.enabled')->name('login_page');

    // Registration Page
    Route::get('/register', [
        PageController::class,
        'register',
    ])->middleware('public.registration.enabled')->name('register_page');
});

// === Authentication Routes ===

// Handle Login Submission
Route::post('/login', [LoginController::class, 'login'])->middleware(['public.login.enabled', 'throttle:10,1'])->name('login');
// Handle Registration Submission
Route::post('/register', [LoginController::class, 'register'])->middleware(['public.registration.enabled', 'throttle:5,10'])->name('register');
Route::post('/contact', [PageController::class, 'submitContactMessage'])->middleware('throttle:5,10')->name('contact.send');
// Handle Logout
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// === Password Reset Routes ===
Route::get('/forgot-password', [PageController::class, 'forgotPassword'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:5,10')->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showBrokerResetForm'])->name('password.reset');
Route::get('/reset-password', [PageController::class, 'resetPassword'])->name('password.reset.form');
Route::post('/verify-token', [PasswordResetController::class, 'verifyToken'])
    ->middleware('throttle:5,1')
    ->name('password.verify');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:5,1')
    ->name('password.update');

// === OTP Verification Routes ===
Route::middleware('auth')->group(function () {
    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::get('/confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('/confirm-password', [ConfirmablePasswordController::class, 'store']);
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('/password', [PasswordController::class, 'update']);
    Route::get('/security/verify-otp', [OtpChallengeController::class, 'show'])->name('otp.challenge.show');
    Route::post('/security/verify-otp', [OtpChallengeController::class, 'verify'])
        ->middleware('throttle:6,1')
        ->name('otp.challenge.verify');
    Route::post('/security/verify-otp/resend', [OtpChallengeController::class, 'resend'])
        ->middleware('throttle:2,1')
        ->name('otp.challenge.resend');
});

Route::middleware(['auth', 'otp.verified', 'verified'])->get('/notifications', function () {
    return redirect()->route('dashboard', ['section' => 'notifications']);
})->name('notifications.index');

Route::middleware(['auth', 'otp.verified', 'verified'])->group(function () {
    Route::get('/dashboard/{section?}', [DashboardController::class, 'index'])
        ->where('section', '.*')
        ->name('dashboard');

    foreach (
        [
            '/admin/dashboard' => 'admin.dashboard',
            '/manager/dashboard' => 'manager.dashboard',
            '/member/dashboard' => 'member.dashboard',
            '/accountant/dashboard' => 'accountant.dashboard',
            '/secretary/dashboard' => 'secretary.dashboard',
            '/loan/dashboard' => 'loan.dashboard',
        ] as $legacyPath => $legacyName
    ) {
        Route::get($legacyPath, function (Request $request) {
            return redirect()->route('dashboard', $request->query());
        })->name($legacyName);
    }
});

// === Maintenance Page ===
// A standalone page the SPA can navigate to when login is switched off. It is
// served as Blade rather than through the catch-all below because the SPA has to
// boot, and booting calls the API, which is exactly what is unavailable.
// During a restore this route is never reached: Laravel's maintenance mode
// short-circuits first and serves errors/503, which renders the same view.
Route::get('/maintenance', function () {
    try {
        // Re-derive the notice from the live settings rather than trusting whatever
        // was recorded earlier, so a switch flipped outside the UI still reads true.
        MaintenanceState::syncLoginAvailability();
    } catch (Throwable) {
        // This page exists for when the system is broken. An unreachable database
        // is one of the ways it can be, and the recorded state -- or the generic
        // fallback -- is still worth showing.
    }

    return response()->view('maintenance', [
        'maintenance' => MaintenanceState::describe(),
    ], MaintenanceState::isActive() ? 503 : 200);
})->name('maintenance');

// === Backup Cron Fallback Ping ===
// Shared hosts without cron support can hit this endpoint (every minute) with
// the BACKUP_PING_TOKEN secret. It runs whichever tier is scheduled & due.
Route::get('/backup/ping', function (Request $request) {
    $token = env('BACKUP_PING_TOKEN');

    if (! is_string($token) || $token === '' || ! hash_equals($token, (string) $request->query('token', ''))) {
        abort(403);
    }

    $exitCode = Artisan::call('backup:ping');

    return response()->json([
        'ok' => $exitCode === 0,
        'output' => trim(Artisan::output()),
    ]);
})->name('backup.ping');

// === SPA Catch-All ===
// Serve the React app for any non-API, non-asset routes.
// This must be AFTER all other routes.
Route::get('/{path?}', function () {
    return view('react');
})->where('path', '.*')->name('react.spa');
