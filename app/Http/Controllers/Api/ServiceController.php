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
            'user_id' => 'nullable|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'account_type' => 'required|in:savings,share-capital',
            'transaction_mode' => 'required|in:counter,online',
            'note' => 'nullable|string|max:500',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $targetUserId = $validated['member_id'] ?? $validated['user_id'] ?? $user->id;

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
            'proof_of_payment_path' => $proofPath,
            'proof_of_payment_uploaded_at' => $proofPath ? now() : null,
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
            'user_id' => 'nullable|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:500',
            'note' => 'nullable|string|max:500',
            'transaction_mode' => 'required|in:counter,online,bank_transfer',
        ]);

        $targetUserId = $validated['member_id'] ?? $validated['user_id'] ?? $user->id;

        // Only officers/admins can withdraw from other users' accounts
        if ($targetUserId !== $user->id && ! $this->canManageServicesForMembers($user)) {
            return response()->json(['message' => 'You can only withdraw from your own account.'], 403);
        }

        // Withdrawals are always debited from the target member's savings account.
        $account = Account::where('user_id', $targetUserId)->where('account_type', 'savings')->first();

        if (! $account) {
            return response()->json(['message' => 'Member has no savings account.'], 422);
        }

        $pendingWithdrawals = Transaction::where('account_id', $account->id)->where('transaction_type', 'withdrawal')->where('status', 'pending')->sum('amount');
        $available = $account->balance - $pendingWithdrawals;

        if ($validated['amount'] > $available) {
            return response()->json(['message' => 'Insufficient available balance. Available: ₱'.number_format($available, 2)], 422);
        }

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'transaction_type' => 'withdrawal',
            'amount' => $validated['amount'],
            'status' => 'pending',
            'transaction_mode' => $validated['transaction_mode'],
            'note' => $validated['note'] ?? $validated['reason'] ?? null,
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
            'co_makers' => 'nullable|array',
            'co_makers.*' => 'integer|exists:users,id',
            'interview_schedule' => 'nullable|date',
            'member_id' => 'nullable|exists:users,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $product = LoanProduct::query()->active()->where('code', $validated['loan_product_code'])->first();
        if (! $product) {
            return response()->json(['message' => 'Invalid loan product.'], 422);
        }

        $targetUserId = $validated['member_id'] ?? $validated['user_id'] ?? $user->id;

        if ($targetUserId !== $user->id && ! $this->canManageServicesForMembers($user)) {
            return response()->json(['message' => 'You can only apply for a loan on your own behalf.'], 403);
        }

        $targetUser = User::with('personalData.employment')->find($targetUserId);
        if (! $targetUser) {
            return response()->json(['message' => 'Unable to resolve applicant.'], 422);
        }

        $eligibilityError = $this->getLoanEligibilityError($targetUser, $product);
        if ($eligibilityError !== null) {
            return response()->json(['message' => $eligibilityError], 422);
        }

        $coMakers = array_unique($validated['co_makers'] ?? []);
        $requiredCoMakers = (int) $product->required_co_makers;
        if ($requiredCoMakers > 0 && count($coMakers) !== $requiredCoMakers) {
            return response()->json(['message' => "This product requires exactly {$requiredCoMakers} co-maker(s)."], 422);
        }

        if ($product->requires_interview && empty($validated['interview_schedule'])) {
            return response()->json(['message' => 'Interview schedule is required for this loan product.'], 422);
        }

        $loan = DB::transaction(function () use ($validated, $product, $targetUser, $coMakers) {
            $loanData = [
                'user_id' => $targetUser->id,
                'loan_type' => $product->base_type,
                'loan_amount' => $validated['amount'],
                'interest_rate' => $product->interest_rate,
                'term_months' => $validated['term_months'],
                'payment_frequency' => $validated['payment_frequency'],
                'loan_status' => 'pending',
                'applied_at' => now(),
            ];

            if ($product->requires_interview) {
                $loanData['employment_id'] = $targetUser->personalData?->employment?->id;
                $loanData['interview_schedule'] = $validated['interview_schedule'];
                $loanData['interview_status'] = 'scheduled';
            }

            $loan = Loan::create($loanData);

            foreach ($coMakers as $coMakerId) {
                if ((int) $coMakerId === $targetUser->id) {
                    continue;
                }
                $loan->groupMembers()->attach($coMakerId, [
                    'has_approved' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $loan;
        });

        app(AuditLogService::class)->log('service.loan_application', 'Loan application submitted.', 'loan', $loan, $user);

        return response()->json(['message' => 'Loan application submitted.', 'data' => $loan], 201);
    }

    public function loanPayment(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'loan_id' => 'required|exists:loans,id',
            'amount' => 'required|numeric|min:1',
            'account_id' => 'nullable|exists:accounts,id',
            'transaction_mode' => 'required|in:counter,online',
            'note' => 'nullable|string|max:500',
            'member_id' => 'nullable|exists:users,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $loan = Loan::findOrFail($validated['loan_id']);
        $targetUserId = $validated['member_id'] ?? $validated['user_id'] ?? $loan->user_id ?? $user->id;

        if ($loan->user_id && (int) $targetUserId !== (int) $loan->user_id && ! $this->canManageServicesForMembers($user)) {
            return response()->json(['message' => 'You can only pay your own loan.'], 403);
        }

        $account = ! empty($validated['account_id']) ? Account::find($validated['account_id']) : null;

        if (! $account || ((int) $account->user_id !== (int) $targetUserId && ! $this->canManageServicesForMembers($user))) {
            $account = Account::where('user_id', $targetUserId)->where('account_type', 'savings')->first();
        }

        if (! $account) {
            return response()->json(['message' => 'Member has no savings account.'], 422);
        }

        if ($account->balance < $validated['amount']) {
            return response()->json(['message' => 'Insufficient balance.'], 422);
        }

        $status = $validated['transaction_mode'] === 'counter' ? 'completed' : 'pending';

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'loan_id' => $loan->id,
            'transaction_type' => 'loan_payment',
            'amount' => $validated['amount'],
            'status' => $status,
            'transaction_mode' => $validated['transaction_mode'],
            'note' => $validated['note'] ?? null,
            'transaction_date' => now(),
            'recorded_by' => Auth::id(),
        ]);

        if ($status === 'completed') {
            $account->decrement('balance', $validated['amount']);

            if ($loan->remainingBalance() <= 0 && $loan->loan_status !== 'paid_off') {
                $loan->update(['loan_status' => 'paid_off']);
            }
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
            'otp_code' => 'nullable|string|digits:6',
        ]);

        if ($transaction->status !== 'pending') {
            return response()->json(['message' => 'Transaction is not pending.'], 422);
        }

        $otpThreshold = (float) SystemSetting::get('security_withdrawal_otp_threshold', 2000);
        $isHighValueWithdrawal = $transaction->transaction_type === 'withdrawal' && $transaction->amount >= $otpThreshold;

        if ($validated['action'] === 'approve' && $isHighValueWithdrawal) {
            if (empty($validated['otp_code'])) {
                return response()->json(['message' => 'A verification code is required to approve this withdrawal.'], 422);
            }

            $verified = app(SecurityOtpService::class)->verifyChallenge(
                $user,
                'withdrawal_approval',
                $validated['otp_code'],
                ['transaction_id' => $transaction->id]
            );

            if (! $verified) {
                return response()->json(['message' => 'Invalid or expired verification code.'], 422);
            }
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

                    if ($transaction->loan && $transaction->loan->remainingBalance() <= 0 && $transaction->loan->loan_status !== 'paid_off') {
                        $transaction->loan->update(['loan_status' => 'paid_off']);
                    }
                }
            } else {
                $transaction->update(['status' => 'rejected', 'rejection_reason' => $validated['reason'] ?? null]);
            }

            app(AuditLogService::class)->log(
                'service.transaction_'.$validated['action'],
                'Transaction '.$validated['action'].'d.',
                'transaction', $transaction, $user
            );
        });

        $this->notifyTransactionDecision($transaction->fresh(), $validated['action'], $validated['reason'] ?? null);

        return response()->json(['message' => 'Transaction '.$validated['action'].'d.']);
    }

    private function notifyTransactionDecision(Transaction $transaction, string $action, ?string $reason): void
    {
        $notificationService = app(NotificationService::class);

        if ($action === 'approve') {
            if ($transaction->transaction_type === 'withdrawal' && $transaction->user) {
                $notificationService->notifyWithdrawalApproved($transaction);
            } elseif ($transaction->transaction_type === 'loan_payment' && $transaction->loan) {
                $notificationService->notifyLoanPayment($transaction->loan, (float) $transaction->amount);
            } elseif ($transaction->transaction_type === 'deposit' && $transaction->user) {
                $notificationService->notifyAccountActivity(
                    $transaction->user,
                    'Deposit Approved',
                    'Your deposit of ₱'.number_format($transaction->amount, 2).' has been approved and credited to your account.'
                );
            }

            return;
        }

        if ($transaction->transaction_type === 'withdrawal' && $transaction->user) {
            $notificationService->notifyWithdrawalRejected($transaction, $reason ?? '');
        } elseif ($transaction->user) {
            $notificationService->notifyAccountActivity(
                $transaction->user,
                'Transaction Rejected',
                'Your transaction of ₱'.number_format($transaction->amount, 2).' was rejected.'.($reason ? ' Reason: '.$reason : '')
            );
        }
    }

    public function sendWithdrawalApprovalOtp(Request $request, Transaction $transaction): JsonResponse
    {
        $user = Auth::user();
        abort_unless($this->canApprove($user), 403);

        abort_unless($transaction->transaction_type === 'withdrawal' && $transaction->status === 'pending', 422);

        $otpThreshold = (float) SystemSetting::get('security_withdrawal_otp_threshold', 2000);
        if ($transaction->amount < $otpThreshold) {
            return response()->json(['message' => 'This transaction does not require a verification code.'], 422);
        }

        $securityOtp = app(SecurityOtpService::class);
        $challenge = $securityOtp->issueChallenge(
            $user,
            $securityOtp->withdrawalApprovalPurpose(),
            ['transaction_id' => $transaction->id]
        );

        $securityOtp->sendOtpMail($user, $securityOtp->withdrawalApprovalPurpose(), $challenge['otp_code']);

        return response()->json([
            'message' => 'A verification code was sent to your email.',
            'expires_at' => $challenge['challenge']->expires_at->toISOString(),
        ]);
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
                'status' => $t->status, 'account_holder' => $t->account?->user ? trim(($t->account->user->personalData->first_name ?? '').' '.($t->account->user->personalData->last_name ?? '')) : null,
                'created_at' => $t->created_at->toISOString(),
            ]);

        return response()->json(['data' => $transactions]);
    }

    private function canApprove($user): bool
    {
        return (bool) ($user && ($user->can('transactions.approve') || $user->can('deposits.approve') || $user->can('withdrawals.approve')));
    }

    private function getLoanEligibilityError(User $targetUser, LoanProduct $product): ?string
    {
        if ($product->requires_no_existing_loan) {
            $activeLoanExists = Loan::query()
                ->where('user_id', $targetUser->id)
                ->whereIn('loan_status', ['pending', 'approved', 'active'])
                ->exists();

            if ($activeLoanExists) {
                return 'This product requires no existing active or pending loan.';
            }
        }

        if ((float) $product->minimum_share_capital > 0) {
            $shareCapitalAccount = $targetUser->accounts()->where('account_type', 'share-capital')->first();
            $shareCapitalBalance = (float) ($shareCapitalAccount?->balance ?? 0);

            if ($shareCapitalBalance < (float) $product->minimum_share_capital) {
                return 'Requires at least ₱'.number_format((float) $product->minimum_share_capital, 2).' share capital.';
            }
        }

        if ((int) $product->minimum_membership_years > 0) {
            $memberYears = (int) $targetUser->created_at?->diffInYears(now());

            if ($memberYears < (int) $product->minimum_membership_years) {
                return 'Requires at least '.(int) $product->minimum_membership_years.' year(s) of membership.';
            }
        }

        if ($product->requires_special_eligibility && ! ($targetUser->special_loan_eligible ?? false)) {
            return 'This product requires special-loan eligibility approval.';
        }

        return null;
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
