<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\LoanProductController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\OfficerPermissionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PublicContentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\NotificationController;
use App\Jobs\SendEmailJob;
use App\Mail\ContactMessageMail;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public auth routes (rate-limited)
Route::post('/login', [AuthController::class, 'login'])->middleware(['web', 'throttle:10,1']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware(['web', 'auth:web']);

// Public routes (no auth required, rate-limited, CSRF-protected)
Route::post('/contact', function (Request $request) {
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'message' => 'required|string|max:2000',
    ]);

    // Send via queue for async processing
    SendEmailJob::dispatch(
        config('mail.from.address'),
        new ContactMessageMail($validated['name'], $validated['email'], $validated['message'])
    );

    return response()->json(['message' => 'Message sent successfully.']);
})->middleware(['web', 'throttle:5,1']);

// Public content (no auth, but session-aware so can_manage reflects the logged-in user)
Route::get('/public-content', [PublicContentController::class, 'index'])->middleware('web');

// Public announcements (no auth — returns only publicly visible announcements)
Route::get('/announcements/public', function (Request $request) {
    $announcements = Announcement::query()
        ->with('creator.personalData')
        ->where(function ($query) {
            $query->whereIn('visibility', ['cooperative', 'public'])
                ->orWhere(function ($jsonBuilder) {
                    $jsonBuilder->whereRaw('json_valid(visibility)')
                        ->where(function ($visibilityBuilder) {
                            $visibilityBuilder->whereJsonContains('visibility', 'public')
                                ->orWhereJsonContains('visibility', 'everyone');
                        });
                });
        })
        ->latest()
        ->paginate($request->integer('per_page', 9));

    return response()->json([
        'data' => $announcements->getCollection()->map(fn ($a) => [
            'id' => $a->id,
            'title' => $a->title,
            'content' => substr($a->content, 0, 200),
            'type' => $a->type,
            'image_url' => $a->image_path ? asset('storage/'.$a->image_path) : null,
            'created_at' => $a->created_at->toISOString(),
            'creator_name' => trim(($a->creator->personalData->first_name ?? '').' '.($a->creator->personalData->last_name ?? '')) ?: 'Unknown',
        ]),
        'pagination' => [
            'current_page' => $announcements->currentPage(),
            'last_page' => $announcements->lastPage(),
            'per_page' => $announcements->perPage(),
            'total' => $announcements->total(),
        ],
    ]);
})->middleware('throttle:30,1');

// Current user (auth + OTP only — must remain reachable so the SPA can
// surface the "verify your email" gate instead of a blank session failure)
Route::middleware(['web', 'auth:web', 'otp.verified'])->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
});

// Resend the email verification link (no `verified` — that is what we gate on)
Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification'])
    ->middleware(['web', 'auth:web', 'otp.verified', 'throttle:3,10']);

// Protected routes
Route::middleware(['web', 'auth:web', 'otp.verified', 'verified'])->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/email/send-otp', [ProfileController::class, 'sendEmailChangeOtp']);
    Route::get('/profile/completion', [ProfileController::class, 'completion']);

    // Settings
    Route::put('/settings/password', [SettingsController::class, 'changePassword']);
    Route::post('/settings/password/send-otp', [SettingsController::class, 'sendPasswordChangeOtp']);
    Route::post('/settings/revoke-devices', [SettingsController::class, 'revokeDevices']);
    Route::post('/settings/deactivate-account', [SettingsController::class, 'deactivateAccount']);
    Route::delete('/settings/account', [SettingsController::class, 'deleteAccount']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications/read/all', [NotificationController::class, 'deleteAllRead']);

    // History (member transaction history)
    Route::get('/history', [HistoryController::class, 'index']);

    // Logs (admin only)
    Route::get('/logs', [LogController::class, 'index']);

    // Global search (permission-scoped)
    Route::get('/search', [SearchController::class, 'global']);

    // Announcements
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update']);
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/member-loans', [DashboardController::class, 'memberLoans']);

    // Loan Products
    Route::get('/loan-products', [LoanProductController::class, 'index']);
    Route::post('/loan-products', [LoanProductController::class, 'store']);
    Route::put('/loan-products/{loanProduct}', [LoanProductController::class, 'update']);
    Route::post('/loan-products/{loanProduct}/toggle', [LoanProductController::class, 'toggle']);
    Route::delete('/loan-products/{loanProduct}', [LoanProductController::class, 'destroy']);

    // Ledger
    Route::get('/ledger', [LedgerController::class, 'index']);
    Route::get('/ledger/export', [LedgerController::class, 'export']);
    Route::get('/ledger/summary', [LedgerController::class, 'summary']);
    Route::post('/ledger', [LedgerController::class, 'store']);
    Route::put('/ledger/{transaction}', [LedgerController::class, 'update']);
    Route::delete('/ledger/{transaction}', [LedgerController::class, 'destroy']);

    // Expenses
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update']);
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);

    // Reports
    Route::get('/reports/financial', [ReportController::class, 'financial']);
    Route::get('/reports/expenses', [ReportController::class, 'expenses']);
    Route::get('/reports/member-activity', [ReportController::class, 'memberActivity']);
    Route::get('/reports/member-savings', [ReportController::class, 'memberSavings']);
    Route::get('/reports/loans', [ReportController::class, 'loanReport']);
    Route::get('/reports/payments', [ReportController::class, 'payments']);
    Route::get('/reports/options', [ReportController::class, 'options']);
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet']);
    Route::get('/reports/balance-sheet/download', [ReportController::class, 'downloadBalanceSheet']);

    // Loans
    Route::get('/loans', [LoanController::class, 'index']);
    Route::get('/loans/search-members', [LoanController::class, 'searchMembers']);
    Route::get('/loans/loan-products', [LoanController::class, 'loanProducts']);
    Route::post('/loans', [LoanController::class, 'store']);
    Route::get('/loans/{loan}', [LoanController::class, 'show']);
    Route::put('/loans/{loan}', [LoanController::class, 'update']);
    Route::delete('/loans/{loan}', [LoanController::class, 'destroy']);
    Route::post('/loans/{loan}/review', [LoanController::class, 'review']);
    Route::post('/loans/{loan}/disburse', [LoanController::class, 'disburse']);
    Route::post('/loans/{loan}/close', [LoanController::class, 'close']);
    Route::post('/loans/{loan}/documents', [LoanController::class, 'uploadDocument']);

    // Role Permissions (admin only)
    Route::get('/roles', [RolePermissionController::class, 'index']);
    Route::post('/roles', [RolePermissionController::class, 'storeRole']);
    Route::delete('/roles/{role}', [RolePermissionController::class, 'destroyRole']);
    Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'updatePermissions']);
    Route::get('/roles/{role}/restore-preset', [RolePermissionController::class, 'restorePreset']);
    Route::put('/roles/{role}/rename', [RolePermissionController::class, 'renameRole']);

    // Public Content (admin edit only — read is public)
    Route::put('/public-content', [PublicContentController::class, 'save']);

    // Branches / Offices
    Route::get('/branches/options', [BranchController::class, 'options']);
    Route::get('/branches', [BranchController::class, 'index']);
    Route::post('/branches', [BranchController::class, 'store']);
    Route::put('/branches/{branch}', [BranchController::class, 'update']);
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);

    // Members
    Route::get('/members', [MemberController::class, 'index']);
    Route::get('/members/search', [MemberController::class, 'search']);
    Route::post('/members/export', [MemberController::class, 'export']);
    Route::post('/members/{user}/password/send-otp', [MemberController::class, 'sendPasswordChangeOtp']);
    Route::post('/members/{user}/approve', [MemberController::class, 'approve']);
    Route::post('/members/{user}/decline', [MemberController::class, 'decline']);
    Route::post('/members/{user}/toggle-special-loan', [MemberController::class, 'toggleSpecialLoanEligibility']);
    Route::get('/members/{user}', [MemberController::class, 'show']);
    Route::post('/members', [MemberController::class, 'store']);
    Route::put('/members/{user}', [MemberController::class, 'update']);
    Route::delete('/members/{user}', [MemberController::class, 'destroy']);
    Route::post('/members/{user}/restore', [MemberController::class, 'restore']);
    Route::delete('/members/{user}/force', [MemberController::class, 'forceDestroy']);

    // Officer permission overrides (admin)
    Route::get('/officers/{user}/permissions', [OfficerPermissionController::class, 'index']);
    Route::put('/officers/{user}/permissions', [OfficerPermissionController::class, 'update']);

    // Services
    Route::get('/services', [ServiceController::class, 'index']);
    Route::post('/services/deposit', [ServiceController::class, 'deposit'])->middleware('service.availability:deposit');
    Route::post('/services/withdrawal', [ServiceController::class, 'withdrawal'])->middleware('service.availability:withdrawal');
    Route::post('/services/loan-application', [ServiceController::class, 'loanApplication'])->middleware('service.availability:loan_application');
    Route::post('/services/loan-payment', [ServiceController::class, 'loanPayment'])->middleware('service.availability:loan_payment');
    Route::post('/services/approve/{transaction}', [ServiceController::class, 'approveTransaction']);
    Route::post('/services/transactions/{transaction}/send-otp', [ServiceController::class, 'sendWithdrawalApprovalOtp']);
    Route::get('/services/pending', [ServiceController::class, 'pendingTransactions']);

    // Service Availability Settings
    Route::get('/settings/service-availability', [SettingsController::class, 'getServiceAvailability']);
    Route::put('/settings/service-availability', [SettingsController::class, 'updateServiceAvailability']);
});
