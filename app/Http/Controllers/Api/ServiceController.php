<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\SecurityOtpService;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');
        $isTreasurer = ! $isAdmin && $this->canApprove($user);
        $canManage = $isAdmin || $this->isOfficer($user);
        $isLoanReview = $user->can('loans.update');

        return response()->json([
            'is_admin' => $isAdmin,
            'is_treasurer' => $isTreasurer,
            'can_manage_member_services' => $canManage,
            'is_loan_review_mode' => $isLoanReview,
            'otp_threshold' => (float) SystemSetting::get('security_withdrawal_otp_threshold', 2000),
            'pending_approvals' => Transaction::where('status', 'pending')->count(),
            'pending_loans' => Loan::where('loan_status', 'pending')->count(),
        ]);
    }

    public function deposit(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'member_id' => 'nullable|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'account_type' => 'required|in:savings,share-capital',
            'transaction_mode' => 'required|in:counter,online',
            'note' => 'nullable|string|max:500',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $targetUserId = $validated['member_id'] ?? $user->id;

        // Only officers/admins can deposit to other users' accounts
        if ($targetUserId !== $user->id && ! $this->canManageServicesForMembers($user)) {
            return response()->json(['message' => 'You can only deposit to your own account.'], 403);
        }

        $account = Account::where('user_id', $targetUserId)->where('account_type', $validated['account_type'])->firstOrFail();

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('transaction-proofs', 'public');
        }

        $status = $validated['transaction_mode'] === 'counter' ? 'completed' : 'pending';

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'transaction_type' => 'deposit',
            'amount' => $validated['amount'],
            'status' => $status,
            'transaction_mode' => $validated['transaction_mode'],
            'note' => $validated['note'] ?? null,
            'proof_path' => $proofPath,
            'transaction_date' => now(),
            'recorded_by' => Auth::id(),
        ]);

        if ($status === 'completed') {
            $account->increment('balance', $validated['amount']);
        }

        app(AuditLogService::class)->log('service.deposit', 'Deposit processed.', 'transaction', $transaction, $user);

        return response()->json(['message' => 'Deposit submitted.', 'data' => $transaction], 201);
    }

    public function withdrawal(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'member_id' => 'nullable|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'account_id' => 'required|exists:accounts,id',
            'reason' => 'nullable|string|max:500',
            'transaction_mode' => 'required|in:counter,online',
        ]);

        $account = Account::findOrFail($validated['account_id']);

        // Only officers/admins can withdraw from other users' accounts
        if ($account->user_id !== $user->id && ! $this->canManageServicesForMembers($user)) {
            return response()->json(['message' => 'You can only withdraw from your own account.'], 403);
        }

        $pendingWithdrawals = Transaction::where('account_id', $account->id)->where('transaction_type', 'withdrawal')->where('status', 'pending')->sum('amount');
        $available = $account->balance - $pendingWithdrawals;

        if ($validated['amount'] > $available) {
            return response()->json(['message' => 'Insufficient available balance. Available: ₱' . number_format($available, 2)], 422);
        }

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'transaction_type' => 'withdrawal',
            'amount' => $validated['amount'],
            'status' => 'pending',
            'transaction_mode' => $validated['transaction_mode'],
            'note' => $validated['reason'] ?? null,
            'transaction_date' => now(),
            'recorded_by' => Auth::id(),
        ]);

        app(AuditLogService::class)->log('service.withdrawal', 'Withdrawal requested.', 'transaction', $transaction, $user);

        return response()->json(['message' => 'Withdrawal submitted for approval.', 'data' => $transaction], 201);
    }

    public function loanApplication(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'loan_product_code' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'term_months' => 'required|integer|min:1|max:360',
            'payment_frequency' => 'required|in:weekly,bi-weekly,monthly,quarterly,annually',
            'purpose' => 'nullable|string|max:500',
        ]);

        $product = LoanProduct::query()->active()->where('code', $validated['loan_product_code'])->first();
        if (! $product) {
            return response()->json(['message' => 'Invalid loan product.'], 422);
        }

        $loan = Loan::create([
            'user_id' => $user->id,
            'loan_type' => $product->base_type,
            'loan_amount' => $validated['amount'],
            'interest_rate' => $product->interest_rate,
            'term_months' => $validated['term_months'],
            'payment_frequency' => $validated['payment_frequency'],
            'loan_status' => 'pending',
            'applied_at' => now(),
        ]);

        app(AuditLogService::class)->log('service.loan_application', 'Loan application submitted.', 'loan', $loan, $user);

        return response()->json(['message' => 'Loan application submitted.', 'data' => $loan], 201);
    }

    public function loanPayment(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'amount' => 'required|numeric|min:1',
            'account_id' => 'required|exists:accounts,id',
            'transaction_mode' => 'required|in:counter,online',
        ]);

        $loan = Loan::findOrFail($validated['loan_id']);
        $account = Account::findOrFail($validated['account_id']);

        if ($account->balance < $validated['amount']) {
            return response()->json(['message' => 'Insufficient balance.'], 422);
        }

        $status = $validated['transaction_mode'] === 'counter' ? 'pending' : 'completed';

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'loan_id' => $loan->id,
            'transaction_type' => 'loan_payment',
            'amount' => $validated['amount'],
            'status' => $status,
            'transaction_mode' => $validated['transaction_mode'],
            'transaction_date' => now(),
            'recorded_by' => Auth::id(),
        ]);

        if ($status === 'completed') {
            $account->decrement('balance', $validated['amount']);
        }

        app(AuditLogService::class)->log('service.loan_payment', 'Loan payment submitted.', 'transaction', $transaction, $user);

        return response()->json(['message' => 'Payment submitted.', 'data' => $transaction], 201);
    }

    public function approveTransaction(Request $request, Transaction $transaction): JsonResponse
    {
        $user = Auth::user();
        abort_unless($this->canApprove($user), 403);

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($transaction->status !== 'pending') {
            return response()->json(['message' => 'Transaction is not pending.'], 422);
        }

        DB::transaction(function () use ($transaction, $validated, $user) {
            if ($validated['action'] === 'approve') {
                $transaction->update(['status' => 'completed', 'approved_by' => $user->id, 'approved_at' => now()]);

                if ($transaction->transaction_type === 'deposit') {
                    $transaction->account->increment('balance', $transaction->amount);
                } elseif ($transaction->transaction_type === 'withdrawal') {
                    $transaction->account->decrement('balance', $transaction->amount);
                } elseif ($transaction->transaction_type === 'loan_payment') {
                    $transaction->account->decrement('balance', $transaction->amount);
                }
            } else {
                $transaction->update(['status' => 'rejected', 'rejection_reason' => $validated['reason'] ?? null]);
            }

            app(AuditLogService::class)->log(
                'service.transaction_' . $validated['action'],
                'Transaction ' . $validated['action'] . 'd.',
                'transaction', $transaction, $user
            );
        });

        return response()->json(['message' => 'Transaction ' . $validated['action'] . 'd.']);
    }

    public function pendingTransactions(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($this->canApprove($user), 403);

        $transactions = Transaction::with(['account.user.personalData', 'loan'])
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id, 'transaction_number' => $t->transaction_number,
                'transaction_type' => $t->transaction_type, 'amount' => $t->amount,
                'status' => $t->status, 'account_holder' => $t->account?->user ? trim(($t->account->user->personalData->first_name ?? '') . ' ' . ($t->account->user->personalData->last_name ?? '')) : null,
                'created_at' => $t->created_at->toISOString(),
            ]);

        return response()->json(['data' => $transactions]);
    }

    private function canApprove($user): bool
    {
        return (bool) ($user && ($user->can('transactions.approve') || $user->can('deposits.approve') || $user->can('withdrawals.approve')));
    }

    private function canManageServicesForMembers($user): bool
    {
        return (bool) ($user && ($user->hasRole('admin') || $this->isOfficer($user)));
    }

    private function isOfficer($user): bool
    {
        return (bool) ($user && (($user->is_officer ?? false) || $user->hasAnyRole(PermissionCatalog::officerRoles())));
    }
}
