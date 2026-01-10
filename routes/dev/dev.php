<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DevPanels\DevPanelController;

// DEVELOPER PANEL FOR DEVELOPMENT ONLY 
Route::prefix('dev')->middleware(['auth', 'role:developer'])->group(function () {
    // Developer Home - Choose between dev panel or test dashboard
    Route::view('/home', 'dev.home')->name('dev.home');
    
    // Test Dashboard - View all sections for testing
    Route::get('/test-dashboard', function() {
        return view('dev.test-dashboard-layout');
    })->name('dev.test-dashboard');
    
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
