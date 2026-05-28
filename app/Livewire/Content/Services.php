<?php

namespace App\Livewire\Content;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\SecurityOtpService;
use App\Support\PermissionCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class Services extends Component
{
    use WithFileUploads;

    protected $notificationService;
    protected $securityOtpService;
    protected $auditLogService;

    // View state
    public $activeService = null; // 'deposit', 'withdraw', 'loan', 'payment', 'approve'

    public $isTreasurerMode = false;

    public $isAdminMode = false;

    public $canManageMemberServices = false;

    public $selectedMemberId = null;

    public $memberSearch = '';

    public $selectedTransactionId = null;

    public $approvalAction = ''; // 'approve' or 'reject'

    public $rejectionReason = '';

    public $approvalOtpCode = '';

    public $approvalOtpSentForTransactionId = null;

    public $withdrawalApprovalOtpThreshold = 2000;

    // Wizard steps
    public $depositStep = 1;

    public $withdrawalStep = 1;

    public $paymentStep = 1;

    // Deposit form
    public $depositAccountType = 'savings';

    public $depositAmount = '';

    public $depositTransactionMode = ''; // 'counter' or 'online'

    public $depositProof = null;

    public $depositNote = '';

    public $completedDepositTransaction = null;

    // Withdrawal form
    public $withdrawalAccountId = '';

    public $withdrawalAmount = '';

    public $withdrawalReason = '';

    public $withdrawalTransactionMode = '';

    public $completedWithdrawalTransaction = null;

    // Loan application - Step tracking
    public $loanStep = 1;

    public $loanType = '';

    public $loanAmount = '';

    public $loanTerm = 6;

    public $loanPurpose = '';

    public $loanPaymentFrequency = 'monthly';

    // Loan payment
    public $paymentLoanId = '';

    public $paymentScheduleKey = '';

    public $paymentAmount = '';

    public $paymentNote = '';

    public $paymentTransactionMode = '';

    public $completedPaymentTransaction = null;

    // Group loan members
    public $groupMembers = [];

    // Business loan fields
    public $interviewSchedule = '';
    public $loanTypes = [];

    public $loanTypeWarnings = [];

    // Loan review (officer-facing) ─────────────────────────────────────────
    public $isLoanReviewMode = false;
    public $selectedLoanId = null;
    public $loanReviewNotes = '';
    public $loanReviewAction = ''; // '' | 'reject' | 'interview_complete'

    public function boot()
    {
        $this->notificationService = app(NotificationService::class);
        $this->securityOtpService = app(SecurityOtpService::class);
        $this->auditLogService = app(AuditLogService::class);
    }

    public function mount()
    {
        $this->withdrawalApprovalOtpThreshold = (float) SystemSetting::get('security_withdrawal_otp_threshold', 2000);
        $this->loadLoanTypes();

        $user = Auth::user();
        $this->isAdminMode = $user && $user->hasRole('admin');
        $this->isTreasurerMode = $user && ! $this->isAdminMode && $this->canApprovePendingTransactions($user);
        $this->canManageMemberServices = $this->canManageServicesForMembers($user);

        // Loan review mode: any officer with loans.update can process review queue
        $this->isLoanReviewMode = $user && $user->can('loans.update');

        // Member-facing services default to the authenticated user's savings account.
        if (! $this->canManageMemberServices) {
            $savingsAccount = Auth::user()->accounts()->where('account_type', 'savings')->first();
            if ($savingsAccount) {
                $this->withdrawalAccountId = $savingsAccount->id;
            }
        }

        $this->refreshLoanTypeWarnings();
    }

    private function canApprovePendingTransactions($user): bool
    {
        return (bool) ($user && (
            $user->can('transactions.approve') ||
            $user->can('deposits.approve') ||
            $user->can('withdrawals.approve')
        ));
    }

    private function canRejectPendingTransactions($user): bool
    {
        return (bool) ($user && (
            $user->can('transactions.approve') ||
            $user->can('deposits.reject') ||
            $user->can('withdrawals.reject')
        ));
    }

    private function canManageServicesForMembers($user): bool
    {
        $isOfficer = $user && (
            ($user->is_officer ?? false) ||
            $user->hasAnyRole(PermissionCatalog::officerRoles())
        );

        return (bool) ($user && (
            $this->isAdminMode ||
            ($isOfficer && (
                $user->can('services.view') ||
                $this->isTreasurerMode ||
                $user->can('services.create') ||
                $user->can('services.update') ||
                $user->can('loans.update')
            ))
        ));
    }

    private function isLoanOfficerFocusedMode($user = null): bool
    {
        $user ??= Auth::user();

        return (bool) ($user && ! $this->isAdminMode && $user->hasRole('loan'));
    }

    private function allowedServicesForCurrentUser($user = null): array
    {
        $user ??= Auth::user();

        if (! $user) {
            return [];
        }

        if ($this->canManageMemberServices) {
            if ($this->isLoanOfficerFocusedMode($user)) {
                return ['loan-review', 'loan', 'payment'];
            }

            $services = ['deposit', 'withdraw', 'loan', 'payment'];

            if ($this->isTreasurerMode || $this->isAdminMode) {
                $services[] = 'approve';
            }

            if ($this->isLoanReviewMode) {
                $services[] = 'loan-review';
            }

            return array_values(array_unique($services));
        }

        $services = ['deposit', 'withdraw', 'loan', 'payment'];

        if ($this->isLoanReviewMode) {
            $services[] = 'loan-review';
        }

        return $services;
    }

    private function canAccessService(string $service, $user = null): bool
    {
        return in_array($service, $this->allowedServicesForCurrentUser($user), true);
    }

    public function selectMember($memberId)
    {
        $this->selectedMemberId = $memberId;
        $this->memberSearch = '';
        $this->dispatch('member-selected');

        // Pre-select member's savings account for withdrawal
        $member = \App\Models\User::find($memberId);
        if ($member) {
            $savingsAccount = $member->accounts()->where('account_type', 'savings')->first();
            if ($savingsAccount) {
                $this->withdrawalAccountId = $savingsAccount->id;
            }
        }

        $this->refreshLoanTypeWarnings();
    }

    public function clearMemberSelection()
    {
        $this->selectedMemberId = null;
        $this->withdrawalAccountId = '';
        $this->refreshLoanTypeWarnings();
        $this->dispatch('member-cleared');
    }

    public function viewTransactionForApproval($transactionId)
    {
        $this->selectedTransactionId = $transactionId;
        $this->approvalOtpCode = '';
        $this->approvalOtpSentForTransactionId = null;
    }

    public function approveTransaction()
    {
        $user = Auth::user();
        $canApprove = $this->canApprovePendingTransactions($user);

        if (!$canApprove || !$this->selectedTransactionId) {
            return;
        }

        try {
            DB::beginTransaction();

            $transaction = Transaction::with(['loan', 'user'])
                ->whereKey($this->selectedTransactionId)
                ->lockForUpdate()
                ->firstOrFail();

            $account = null;
            if ($transaction->account_id) {
                $account = Account::query()
                    ->whereKey($transaction->account_id)
                    ->lockForUpdate()
                    ->first();
            }

            // Check if already processed
            if ($transaction->status !== 'pending') {
                $this->dispatch('warning', message: 'Transaction has already been processed.');
                $this->selectedTransactionId = null;
                DB::rollBack();
                return;
            }

            // For withdrawals, verify the account still has sufficient balance
            if ($transaction->transaction_type === 'withdrawal') {
                if (! $account || $account->balance < $transaction->amount) {
                    $currentBalance = (float) ($account?->balance ?? 0);
                    $this->dispatch('error', message: 'Insufficient balance. Current balance: ₱' . number_format($currentBalance, 2));
                    DB::rollBack();
                    return;
                }
            }

            if (in_array($transaction->transaction_type, ['deposit', 'withdrawal'], true) && ! $account) {
                $this->dispatch('error', message: 'The linked account is missing for this transaction.');
                DB::rollBack();
                return;
            }

            if ($this->requiresWithdrawalApprovalOtp($transaction) && ! $this->passesWithdrawalApprovalOtp($transaction)) {
                DB::rollBack();
                return;
            }

            // Update status to completed
            $transaction->status = 'completed';
            $transaction->note = ($transaction->note ? $transaction->note . ' | ' : '') . 'Approved by ' . Auth::user()->personalData->first_name . ' ' . Auth::user()->personalData->last_name;
            $transaction->save();

            $this->auditLogService->log(
                'transaction.approved',
                'Pending transaction approved.',
                'transaction',
                $transaction,
                $user,
                [
                    'transaction_type' => $transaction->transaction_type,
                    'amount' => (float) $transaction->amount,
                    'target_user_id' => $transaction->user?->id,
                ]
            );

            // Update account balance
            if ($transaction->transaction_type === 'deposit') {
                $account->balance += (float) $transaction->amount;
                $account->save();

                if ($transaction->user) {
                    $this->notificationService->notifyAccountActivity(
                        $transaction->user,
                        'Deposit Approved',
                        'Your deposit request of ₱' . number_format($transaction->amount, 2) . ' has been approved and credited.',
                        'normal'
                    );
                }
            } elseif ($transaction->transaction_type === 'withdrawal') {
                $account->balance -= (float) $transaction->amount;
                $account->save();

                // Send withdrawal approval notification
                if ($transaction->user) {
                    $this->notificationService->notifyWithdrawalApproved($transaction);
                }
            } elseif ($transaction->transaction_type === 'loan_payment' && $transaction->loan) {
                if ($transaction->user) {
                    $this->notificationService->notifyLoanPayment($transaction->loan, (float) $transaction->amount);
                }

                // Check if loan is now fully paid
                $loan = $transaction->loan;
                $loan->refresh(); // Refresh to get updated balance after this payment

                $remainingBalance = $loan->remainingBalance();

                if ($remainingBalance <= 0) {
                    $loan->update(['loan_status' => 'paid_off']);
                }
            }

            DB::commit();

            $this->dispatch('success', message: 'Transaction approved successfully!');
            $this->selectedTransactionId = null;
            $this->approvalAction = '';
            $this->approvalOtpCode = '';
            $this->approvalOtpSentForTransactionId = null;
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            $this->dispatch('error', message: 'Failed to approve transaction. Please try again or contact support.');
        }
    }

    public function resendApprovalOtp()
    {
        if (! $this->selectedTransactionId) {
            return;
        }

        $transaction = Transaction::find($this->selectedTransactionId);
        if (! $transaction || ! $this->requiresWithdrawalApprovalOtp($transaction)) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $purpose = $this->securityOtpService->withdrawalApprovalPurpose();
        $context = ['transaction_id' => $transaction->id];

        $issued = $this->securityOtpService->issueChallenge($user, $purpose, $context);
        $this->securityOtpService->sendOtpMail($user, $purpose, $issued['otp_code']);

        $this->approvalOtpSentForTransactionId = $transaction->id;
        $this->dispatch('success', message: 'A new OTP was sent to your email.');
    }

    public function rejectTransaction()
    {
        $this->validate([
            'rejectionReason' => 'required|string|min:5',
        ]);

        $user = Auth::user();
        $canReject = $this->canRejectPendingTransactions($user);

        if (!$canReject || !$this->selectedTransactionId) {
            return;
        }

        try {
            DB::beginTransaction();

            $transaction = Transaction::with(['account', 'user'])->findOrFail($this->selectedTransactionId);

            // Check if already processed
            if ($transaction->status !== 'pending' && $transaction->status !== 'completed') {
                $this->dispatch('warning', message: 'Transaction has already been rejected.');
                $this->selectedTransactionId = null;
                $this->rejectionReason = '';
                DB::rollBack();
                return;
            }

            // Reverse the transaction if money was already deducted/added (completed status)
            if ($transaction->status === 'completed') {
                $account = $transaction->account;

                if ($transaction->transaction_type === 'deposit') {
                    // Reverse deposit - remove the money
                    $account->balance -= $transaction->amount;
                    $account->save();
                } elseif ($transaction->transaction_type === 'withdrawal') {
                    // Reverse withdrawal - return the money
                    $account->balance += $transaction->amount;
                    $account->save();
                }
            }

            // Update status to rejected
            $transaction->status = 'rejected';
            $transaction->note = ($transaction->note ? $transaction->note . ' | ' : '') . 'REJECTED: ' . $this->rejectionReason . ' (by ' . Auth::user()->personalData->first_name . ' ' . Auth::user()->personalData->last_name . ')';
            $transaction->save();

            $this->auditLogService->log(
                'transaction.rejected',
                'Transaction rejected by approver.',
                'transaction',
                $transaction,
                $user,
                [
                    'transaction_type' => $transaction->transaction_type,
                    'amount' => (float) $transaction->amount,
                    'reason' => $this->rejectionReason,
                    'target_user_id' => $transaction->user?->id,
                ]
            );

            // Send withdrawal rejection notification
            if ($transaction->transaction_type === 'withdrawal' && $transaction->user) {
                $this->notificationService->notifyWithdrawalRejected($transaction, $this->rejectionReason);
            } elseif ($transaction->transaction_type === 'deposit' && $transaction->user) {
                $this->notificationService->notifyAccountActivity(
                    $transaction->user,
                    'Deposit Request Rejected',
                    'Your deposit request of ₱' . number_format($transaction->amount, 2) . ' was rejected. Reason: ' . $this->rejectionReason,
                    'high'
                );
            } elseif ($transaction->transaction_type === 'loan_payment' && $transaction->user) {
                $this->notificationService->notifyAccountActivity(
                    $transaction->user,
                    'Loan Payment Rejected',
                    'Your loan payment of ₱' . number_format($transaction->amount, 2) . ' was rejected. Reason: ' . $this->rejectionReason,
                    'high'
                );
            }

            DB::commit();

            $this->dispatch('success', message: 'Transaction rejected and reversed successfully.');
            $this->selectedTransactionId = null;
            $this->rejectionReason = '';
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Failed to reject transaction: ' . $e->getMessage());
        }
    }

    public function cancelApproval()
    {
        $this->selectedTransactionId = null;
        $this->rejectionReason = '';
        $this->approvalAction = '';
        $this->approvalOtpCode = '';
        $this->approvalOtpSentForTransactionId = null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Officer Loan Review actions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Select a loan to act on inside the review modal.
     */
    public function selectLoanForReview(int $loanId): void
    {
        $user = Auth::user();
        if (! $user || ! $user->can('loans.update')) {
            $this->dispatch('error', message: 'You do not have permission to review loan applications.');
            return;
        }

        $this->selectedLoanId = $loanId;
        $this->loanReviewNotes = '';
        $this->loanReviewAction = '';
    }

    public function cancelLoanReview(): void
    {
        $this->selectedLoanId = null;
        $this->loanReviewNotes = '';
        $this->loanReviewAction = '';
    }

    /**
     * Officer picks up a pending loan for review (null → under_review).
     */
    public function startLoanReview(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->can('loans.update')) {
            $this->dispatch('error', message: 'Unauthorized.');
            return;
        }

        $loan = Loan::where('id', $this->selectedLoanId)
            ->where('loan_status', 'pending')
            ->whereNull('review_status')
            ->first();

        if (! $loan) {
            $this->dispatch('error', message: 'Loan not found or already in review.');
            return;
        }

        $loan->update([
            'review_status' => 'under_review',
            'reviewed_by'   => $user->id,
            'reviewed_at'   => now(),
        ]);

        $this->auditLogService->log(
            'loan.review_started',
            'Officer started reviewing loan application.',
            'loan',
            $loan,
            $user,
            ['loan_number' => $loan->loan_number]
        );

        $this->notificationService->notifyLoanUnderReview($loan);

        $this->dispatch('success', message: "Loan {$loan->loan_number} is now under review.");
        $this->selectedLoanId = null;
    }

    /**
     * Officer marks interview as completed (updates interview_status on the loan).
     */
    public function markLoanInterviewComplete(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->can('loans.update')) {
            $this->dispatch('error', message: 'Unauthorized.');
            return;
        }

        $this->validate(['loanReviewNotes' => 'nullable|string|max:1000']);

        $loan = Loan::where('id', $this->selectedLoanId)
            ->where('loan_status', 'pending')
            ->where('review_status', 'under_review')
            ->whereNotNull('interview_schedule')
            ->first();

        if (! $loan) {
            $this->dispatch('error', message: 'Loan is not currently under review or has no interview scheduled.');
            return;
        }

        $loan->update([
            'interview_status'    => 'completed',
            'interview_officer_id' => $user->id,
            'review_notes'        => $this->loanReviewNotes ?: $loan->review_notes,
        ]);

        $this->auditLogService->log(
            'loan.interview_completed',
            'Officer marked loan interview as completed.',
            'loan',
            $loan,
            $user,
            ['loan_number' => $loan->loan_number]
        );

        $this->dispatch('success', message: 'Interview marked as completed.');
        $this->loanReviewNotes = '';
        $this->loanReviewAction = '';
    }

    /**
     * Officer endorses a reviewed loan for final admin/manager approval.
     */
    public function endorseLoanForApproval(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->can('loans.update')) {
            $this->dispatch('error', message: 'Unauthorized.');
            return;
        }

        $this->validate(['loanReviewNotes' => 'nullable|string|max:1000']);

        $loan = Loan::where('id', $this->selectedLoanId)
            ->where('loan_status', 'pending')
            ->where('review_status', 'under_review')
            ->first();

        if (! $loan) {
            $this->dispatch('error', message: 'Loan must be under review before it can be endorsed.');
            return;
        }

        // For interview-required loans, the interview must be completed first.
        if (! empty($loan->interview_schedule) && $loan->interview_status !== 'completed') {
            $this->dispatch('error', message: 'The scheduled interview must be marked complete before endorsing.');
            return;
        }

        $loan->update([
            'review_status' => 'endorsed_for_approval',
            'reviewed_by'   => $user->id,
            'reviewed_at'   => now(),
            'review_notes'  => $this->loanReviewNotes ?: $loan->review_notes,
        ]);

        $this->auditLogService->log(
            'loan.endorsed_for_approval',
            'Officer endorsed loan for final approval.',
            'loan',
            $loan,
            $user,
            ['loan_number' => $loan->loan_number, 'notes' => $this->loanReviewNotes]
        );

        $this->notificationService->notifyLoanEndorsedForApproval($loan);

        $this->dispatch('success', message: "Loan {$loan->loan_number} has been endorsed for final approval.");
        $this->selectedLoanId = null;
        $this->loanReviewNotes = '';
        $this->loanReviewAction = '';
    }

    /**
     * Officer rejects a loan application during review.
     * Requires a written reason. Sets review_status = review_rejected.
     */
    public function rejectLoanInReview(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->can('loans.reject')) {
            $this->dispatch('error', message: 'You do not have permission to reject loan applications.');
            return;
        }

        $this->validate(['loanReviewNotes' => 'required|string|min:10|max:1000']);

        $loan = Loan::where('id', $this->selectedLoanId)
            ->where('loan_status', 'pending')
            ->whereIn(DB::raw('COALESCE(review_status, \'none\')'), ['none', 'under_review'])
            ->first();

        // Fallback query for null review_status (COALESCE trick may not work on all drivers)
        if (! $loan) {
            $loan = Loan::where('id', $this->selectedLoanId)
                ->where('loan_status', 'pending')
                ->where(function ($q) {
                    $q->whereNull('review_status')
                        ->orWhere('review_status', 'under_review');
                })
                ->first();
        }

        if (! $loan) {
            $this->dispatch('error', message: 'Loan not found or is not in a reviewable state.');
            return;
        }

        DB::beginTransaction();
        try {
            $loan->update([
                'review_status' => 'review_rejected',
                'reviewed_by'   => $user->id,
                'reviewed_at'   => now(),
                'review_notes'  => $this->loanReviewNotes,
            ]);

            $this->auditLogService->log(
                'loan.review_rejected',
                'Officer rejected loan application during review.',
                'loan',
                $loan,
                $user,
                ['loan_number' => $loan->loan_number, 'reason' => $this->loanReviewNotes]
            );

            $this->notificationService->notifyLoanReviewRejected($loan, $this->loanReviewNotes);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Failed to reject loan: ' . $e->getMessage());
            return;
        }

        $this->dispatch('success', message: "Loan {$loan->loan_number} has been rejected.");
        $this->selectedLoanId = null;
        $this->loanReviewNotes = '';
        $this->loanReviewAction = '';
    }

    /**
     * Admin / manager returns an endorsed loan back to officer review.
     */
    public function returnLoanForReview(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasAnyRole(['admin', 'manager'])) {
            $this->dispatch('error', message: 'Only admin or manager can return a loan for re-review.');
            return;
        }

        $loan = Loan::where('id', $this->selectedLoanId)
            ->where('loan_status', 'pending')
            ->where('review_status', 'endorsed_for_approval')
            ->first();

        if (! $loan) {
            $this->dispatch('error', message: 'Loan not found or not in endorsed state.');
            return;
        }

        $loan->update([
            'review_status' => 'under_review',
            'review_notes'  => $this->loanReviewNotes ?: $loan->review_notes,
        ]);

        $this->auditLogService->log(
            'loan.returned_for_review',
            'Admin returned endorsed loan to officer review.',
            'loan',
            $loan,
            $user,
            ['loan_number' => $loan->loan_number, 'notes' => $this->loanReviewNotes]
        );

        $this->dispatch('success', message: "Loan {$loan->loan_number} returned to the review queue.");
        $this->selectedLoanId = null;
        $this->loanReviewNotes = '';
        $this->loanReviewAction = '';
    }

    /**
     * Final loan approval — admin and manager (chairman) only.
     * Hard role gate enforced regardless of permission assignment.
     */
    public function approveLoanFinal(): void
    {
        $user = Auth::user();
        // Hard role gate: permission-based checks alone are insufficient for this action.
        if (! $user || ! $user->hasAnyRole(['admin', 'manager'])) {
            $this->dispatch('error', message: 'Only admin or manager can issue final loan approval.');
            return;
        }

        $loan = Loan::where('id', $this->selectedLoanId)
            ->where('loan_status', 'pending')
            ->where('review_status', 'endorsed_for_approval')
            ->first();

        if (! $loan) {
            $this->dispatch('error', message: 'Loan must be endorsed before it can be approved. Please ensure an officer has endorsed it first.');
            return;
        }

        DB::beginTransaction();
        try {
            $loan->update([
                'loan_status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $this->auditLogService->log(
                'loan.approved',
                'Loan application approved.',
                'loan',
                $loan,
                $user,
                [
                    'loan_number' => $loan->loan_number,
                    'loan_amount' => (float) $loan->loan_amount,
                ]
            );

            $this->notificationService->notifyLoanApproved($loan);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Failed to approve loan: ' . $e->getMessage());
            return;
        }

        $this->dispatch('success', message: "Loan {$loan->loan_number} has been approved!");
        $this->selectedLoanId = null;
        $this->loanReviewNotes = '';
        $this->loanReviewAction = '';
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function isActingForMember(): bool
    {
        return (bool) ($this->selectedMemberId && $this->canManageMemberServices);
    }

    public function openService($service)
    {
        if (! $this->canAccessService($service, Auth::user())) {
            $this->dispatch('warning', message: 'This service is not available for your role.');
            return;
        }

        // 'approve' and 'loan-review' are officer-only panels that don't need a member pre-selected
        $memberFreeServices = ['approve', 'loan-review'];

        // Officer-facing member services require a member to be selected first.
        if ($this->canManageMemberServices && ! in_array($service, $memberFreeServices, true) && ! $this->selectedMemberId) {
            $this->dispatch('warning', message: 'Please select a member first before accessing this service.');
            return;
        }

        $this->activeService = $service;

        if ($service === 'loan') {
            $this->loanStep = 1;
        }
    }

    public function closeService()
    {
        $this->activeService = null;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset([
            'depositStep',
            'depositAmount',
            'depositTransactionMode',
            'depositProof',
            'depositNote',
            'completedDepositTransaction',
            'withdrawalStep',
            'withdrawalAmount',
            'withdrawalReason',
            'withdrawalTransactionMode',
            'completedWithdrawalTransaction',
            'paymentStep',
            'loanStep',
            'loanType',
            'loanAmount',
            'loanTerm',
            'loanPurpose',
            'loanPaymentFrequency',
            'paymentLoanId',
            'paymentScheduleKey',
            'paymentAmount',
            'paymentNote',
            'paymentTransactionMode',
            'completedPaymentTransaction',
            'groupMembers',
            'interviewSchedule',
            'approvalOtpCode',
            // Loan review
            'selectedLoanId',
            'loanReviewNotes',
            'loanReviewAction',
        ]);

        $this->approvalOtpSentForTransactionId = null;
    }

    public function getSelectedTransactionRequiresOtpProperty(): bool
    {
        if (! $this->selectedTransactionId) {
            return false;
        }

        $transaction = Transaction::find($this->selectedTransactionId);

        return $transaction ? $this->requiresWithdrawalApprovalOtp($transaction) : false;
    }

    private function requiresWithdrawalApprovalOtp(Transaction $transaction): bool
    {
        if ($transaction->transaction_type !== 'withdrawal') {
            return false;
        }

        $user = Auth::user();
        if (! $user || ! $this->securityOtpService->shouldEnforceOtpForUser($user)) {
            return false;
        }

        return (float) $transaction->amount >= (float) $this->withdrawalApprovalOtpThreshold;
    }

    private function passesWithdrawalApprovalOtp(Transaction $transaction): bool
    {
        $user = Auth::user();
        if (! $user) {
            $this->dispatch('error', message: 'Unable to verify user for OTP validation.');
            return false;
        }

        $purpose = $this->securityOtpService->withdrawalApprovalPurpose();
        $context = ['transaction_id' => $transaction->id];

        if ($this->securityOtpService->hasVerifiedChallenge($user, $purpose, $context)) {
            return true;
        }

        $otpCode = trim((string) $this->approvalOtpCode);
        if ($otpCode === '') {
            if ((int) $this->approvalOtpSentForTransactionId !== (int) $transaction->id) {
                $issued = $this->securityOtpService->issueChallenge($user, $purpose, $context);
                $this->securityOtpService->sendOtpMail($user, $purpose, $issued['otp_code']);
                $this->approvalOtpSentForTransactionId = $transaction->id;
            }

            $this->dispatch('warning', message: 'OTP required for high-value withdrawal approval. Enter the code sent to your email.');
            return false;
        }

        $verified = $this->securityOtpService->verifyChallenge($user, $purpose, $otpCode, $context);
        if (! $verified) {
            $this->dispatch('error', message: 'Invalid or expired OTP. Please try again or resend a new code.');
            return false;
        }

        $this->approvalOtpCode = '';
        $this->approvalOtpSentForTransactionId = null;

        return true;
    }

    // Deposit wizard methods
    public function nextDepositStep()
    {
        if ($this->depositStep === 1) {
            $this->validate(Arr::only($this->depositRules(), ['depositAmount', 'depositNote']));
        } elseif ($this->depositStep === 2) {
            $this->validate(Arr::only($this->depositRules(), ['depositTransactionMode']));
        }
        $this->depositStep++;
    }

    public function previousDepositStep()
    {
        if ($this->depositStep > 1) {
            $this->depositStep--;
        }
    }

    // Deposit methods
    public function submitDeposit()
    {
        $this->validate($this->depositRules());

        try {
            DB::beginTransaction();

            // Get target user (member if acting for member, or auth user)
            $targetUser = $this->isActingForMember()
                ? \App\Models\User::find($this->selectedMemberId)
                : Auth::user();

            $account = $targetUser->accounts()
                ->where('account_type', $this->depositAccountType)
                ->first();

            if (! $account) {
                $this->dispatch('error', message: 'Account not found');

                return;
            }

            $proofPath = $this->depositProof
                ? $this->depositProof->store('transaction-proofs', 'public')
                : null;

            $requiresProofVerification = $this->depositTransactionMode === 'online';
            $isAutoApprove = false;

            // Create transaction
            $note = ($this->isActingForMember() ? 'Processed by officer - ' : '')
                . ($this->depositTransactionMode === 'counter' ? 'Over the counter' : 'Online payment')
                . ($this->depositNote ? ' | ' . $this->depositNote : '');

            $transaction = Transaction::create([
                'account_id' => $account->id,
                'transaction_type' => 'deposit',
                'amount' => $this->depositAmount,
                'transaction_date' => now(),
                'status' => $isAutoApprove ? 'completed' : 'pending',
                'proof_of_payment_path' => $proofPath,
                'proof_of_payment_uploaded_at' => $proofPath ? now() : null,
                'note' => $note,
                'recorded_by' => Auth::id(),
            ]);

            $this->auditLogService->log(
                'transaction.deposit_submitted',
                $requiresProofVerification
                    ? 'Online deposit submitted with proof and awaiting verification.'
                    : 'Deposit submitted and awaiting approval.',
                'transaction',
                $transaction,
                Auth::user(),
                [
                    'amount' => (float) $this->depositAmount,
                    'mode' => $this->depositTransactionMode,
                    'status' => $transaction->status,
                    'has_proof' => $proofPath !== null,
                    'target_user_id' => $targetUser?->id,
                ]
            );

            // Update account balance only if a deposit is auto-approved.
            if ($isAutoApprove) {
                $account->increment('balance', $this->depositAmount);

                $this->notificationService->notifyAccountActivity(
                    $targetUser,
                    'Deposit Completed',
                    'Your deposit of ₱' . number_format((float) $this->depositAmount, 2) . ' has been credited to your account.',
                    'normal'
                );
            } else {
                $this->notificationService->notifyAccountActivity(
                    $targetUser,
                    'Deposit Request Submitted',
                    'Your deposit request of ₱' . number_format((float) $this->depositAmount, 2) . ' is pending approval.',
                    'normal'
                );
            }

            DB::commit();

            // Store transaction for receipt/confirmation
            $this->completedDepositTransaction = $transaction->fresh();

            $this->depositStep = 99;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Failed to submit deposit request: ' . $e->getMessage());
        }
    }

    public function completeOnlineDepositPayment()
    {
        // This simulates completing the online payment
        // In real implementation, this would be called after payment gateway confirms
        $this->depositStep++; // Move to receipt step
    }

    // Withdrawal wizard methods
    public function nextWithdrawalStep()
    {
        $account = Account::find($this->withdrawalAccountId);

        // Calculate available balance (current balance minus pending withdrawals)
        $pendingWithdrawals = Transaction::where('account_id', $this->withdrawalAccountId)
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'pending')
            ->sum('amount');

        $availableBalance = $account ? ($account->balance - $pendingWithdrawals) : 0;

        if ($this->withdrawalStep === 1) {
            $this->validate(Arr::only($this->withdrawalRules((float) $availableBalance), ['withdrawalAmount', 'withdrawalReason']));
        } elseif ($this->withdrawalStep === 2) {
            $this->validate(Arr::only($this->withdrawalRules((float) $availableBalance), ['withdrawalTransactionMode']));
        }
        $this->withdrawalStep++;
    }

    public function previousWithdrawalStep()
    {
        if ($this->withdrawalStep > 1) {
            $this->withdrawalStep--;
        }
    }

    // Withdrawal methods
    public function submitWithdrawal()
    {
        $account = Account::find($this->withdrawalAccountId);

        // Calculate available balance (current balance minus ALL pending withdrawals)
        $pendingWithdrawals = Transaction::where('account_id', $this->withdrawalAccountId)
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'pending')
            ->sum('amount');

        $availableBalance = $account->balance - $pendingWithdrawals;

        $this->validate($this->withdrawalRules((float) $availableBalance));

        try {
            DB::beginTransaction();

            // ALL withdrawals now require approval (no auto-approve)
            $note = ($this->isActingForMember() ? 'Processed by officer - ' : '')
                . $this->withdrawalReason . ' | '
                . ($this->withdrawalTransactionMode === 'counter' ? 'Over the counter' : 'Online payment');

            $transaction = Transaction::create([
                'account_id' => $this->withdrawalAccountId,
                'transaction_type' => 'withdrawal',
                'amount' => $this->withdrawalAmount,
                'transaction_date' => now(),
                'status' => 'pending', // ALL withdrawals start as pending
                'note' => $note,
                'recorded_by' => Auth::id(),
            ]);

            $targetUser = $this->isActingForMember()
                ? \App\Models\User::find($this->selectedMemberId)
                : Auth::user();

            $this->auditLogService->log(
                'transaction.withdrawal_submitted',
                'Withdrawal submitted and queued for approval.',
                'transaction',
                $transaction,
                Auth::user(),
                [
                    'amount' => (float) $this->withdrawalAmount,
                    'mode' => $this->withdrawalTransactionMode,
                    'reason' => $this->withdrawalReason,
                    'target_user_id' => $targetUser?->id,
                ]
            );

            // Do NOT update account balance - wait for approval

            if ($targetUser) {
                $this->notificationService->notifyAccountActivity(
                    $targetUser,
                    'Withdrawal Request Submitted',
                    'Your withdrawal request of ₱' . number_format((float) $this->withdrawalAmount, 2) . ' has been submitted for approval.',
                    'high'
                );
            }

            DB::commit();

            $this->completedWithdrawalTransaction = $transaction->fresh();

            // Always go to pending confirmation step
            $this->withdrawalStep = 99;

            $this->dispatch('success', message: 'Withdrawal request submitted for approval.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Failed to submit withdrawal: ' . $e->getMessage());
        }
    }

    public function completeOnlineWithdrawalPayment()
    {
        $this->withdrawalStep++;
    }

    // Loan payment wizard methods
    public function nextPaymentStep()
    {
        if ($this->paymentStep === 1) {
            $this->validate($this->paymentStep1Rules());

            $loan = $this->resolveSelectedPaymentLoan();
            $selectedSchedule = $loan ? $this->findSelectedPaymentSchedule($loan) : null;

            if (! $loan || ! $selectedSchedule) {
                $this->addError('paymentScheduleKey', 'Please select a valid payment schedule.');
                return;
            }

            $this->paymentAmount = $selectedSchedule['amount_due'];

            if ($this->paymentAmount > $loan->remainingBalance()) {
                $this->addError('paymentScheduleKey', 'Selected payment exceeds remaining loan balance (₱' . number_format($loan->remainingBalance(), 2) . ')');
                return;
            }
        } elseif ($this->paymentStep === 2) {
            $this->validate([
                'paymentTransactionMode' => 'required|in:counter,online',
            ]);
        }
        $this->paymentStep++;
    }

    public function previousPaymentStep()
    {
        if ($this->paymentStep > 1) {
            $this->paymentStep--;
        }
    }

    public function updatedPaymentLoanId($loanId): void
    {
        $this->paymentScheduleKey = '';
        $this->paymentAmount = '';

        if (blank($loanId)) {
            return;
        }

        $loan = $this->resolveSelectedPaymentLoan();
        $availableSchedules = $loan ? $this->getAvailablePaymentSchedules($loan) : [];

        if (count($availableSchedules) === 1) {
            $this->paymentScheduleKey = (string) $availableSchedules[0]['key'];
            $this->paymentAmount = $availableSchedules[0]['amount_due'];
        }
    }

    public function updatedPaymentScheduleKey($scheduleKey): void
    {
        $this->paymentAmount = '';

        if (blank($scheduleKey)) {
            return;
        }

        $loan = $this->resolveSelectedPaymentLoan();
        $selectedSchedule = $loan ? $this->findSelectedPaymentSchedule($loan) : null;

        if ($selectedSchedule) {
            $this->paymentAmount = $selectedSchedule['amount_due'];
        }
    }

    // Loan application methods
    public function selectLoanType($type)
    {
        if (! isset($this->loanTypes[$type])) {
            $this->dispatch('error', message: 'Selected loan product is unavailable.');
            return;
        }

        $this->loanType = $type;

        $targetUser = $this->resolveLoanTargetUser();
        if (! $targetUser) {
            $this->dispatch('error', message: 'Unable to resolve target member for loan application.');
            return;
        }

        $eligibilityError = $this->getLoanEligibilityError($targetUser, $this->loanTypes[$type]);
        if ($eligibilityError !== null) {
            $this->dispatch('warning', message: $eligibilityError);
            return;
        }

        // Reset co-makers when switching between products.
        if ((int) ($this->loanTypes[$type]['required_co_makers'] ?? 0) > 0) {
            $this->groupMembers = [];
        }

        $this->loanStep = 2;
    }

    public function addGroupMember($userId)
    {
        $requiredCoMakers = (int) (($this->loanTypes[$this->loanType]['required_co_makers'] ?? 0));
        if ($requiredCoMakers <= 0) {
            $this->dispatch('warning', message: 'This loan product does not require co-makers.');
            return;
        }

        if (count($this->groupMembers) >= $requiredCoMakers) {
            $this->dispatch('error', message: 'Maximum ' . $requiredCoMakers . ' co-makers allowed for this product.');
            return;
        }

        if (in_array($userId, $this->groupMembers)) {
            $this->dispatch('warning', message: 'Member already added to group');
            return;
        }

        // Don't allow adding yourself
        $currentUserId = $this->isActingForMember()
            ? $this->selectedMemberId
            : Auth::id();

        if ($userId == $currentUserId) {
            $this->dispatch('warning', message: 'Cannot add yourself as a co-maker');
            return;
        }

        $this->groupMembers[] = $userId;
        $this->memberSearch = '';
        $this->dispatch('success', message: 'Member added to group');
    }

    public function removeGroupMember($userId)
    {
        $this->groupMembers = array_values(array_filter($this->groupMembers, function ($id) use ($userId) {
            return $id != $userId;
        }));
    }

    public function nextLoanStep()
    {
        $loanConfig = $this->loanTypes[$this->loanType] ?? null;
        if (! $loanConfig) {
            $this->dispatch('error', message: 'Loan product configuration is missing.');
            return;
        }

        if ($this->loanStep === 2) {
            $requiredCoMakers = (int) ($loanConfig['required_co_makers'] ?? 0);
            if ($requiredCoMakers > 0 && count($this->groupMembers) !== $requiredCoMakers) {
                $this->dispatch('error', message: 'This product requires exactly ' . $requiredCoMakers . ' co-makers.');
                return;
            }

            $this->validate($this->loanDetailsRules($loanConfig));
        } elseif ($this->loanStep === 3) {
            $this->validate($this->loanPurposeRules());
        }

        $this->loanStep++;
    }

    public function previousLoanStep()
    {
        if ($this->loanStep > 1) {
            $this->loanStep--;
        }
    }

    public function submitLoanApplication()
    {
        $loanConfig = $this->loanTypes[$this->loanType] ?? null;
        if (! $loanConfig) {
            $this->dispatch('error', message: 'Loan product configuration is missing.');
            return;
        }

        // Validate loan data before submission
        $this->validate($this->loanSubmitRules($loanConfig));

        $targetUser = $this->resolveLoanTargetUser();
        if (! $targetUser) {
            $this->dispatch('error', message: 'Unable to resolve target member for loan application.');
            return;
        }

        $eligibilityError = $this->getLoanEligibilityError($targetUser, $loanConfig);
        if ($eligibilityError !== null) {
            $this->dispatch('error', message: $eligibilityError);
            return;
        }

        $requiredCoMakers = (int) ($loanConfig['required_co_makers'] ?? 0);
        if ($requiredCoMakers > 0 && count($this->groupMembers) !== $requiredCoMakers) {
            $this->dispatch('error', message: 'This product requires exactly ' . $requiredCoMakers . ' co-makers.');
            return;
        }

        if (! empty($loanConfig['requires_interview']) && empty($this->interviewSchedule)) {
            $this->dispatch('error', message: 'Interview schedule is required for this loan product.');
            return;
        }

        try {
            DB::beginTransaction();

            $targetUserId = $targetUser->id;

            // Get employment info for products that require interviews.
            $employmentId = null;
            if (! empty($loanConfig['requires_interview']) && $targetUser->personalData && $targetUser->personalData->employment) {
                $employmentId = $targetUser->personalData->employment->id;
            }

            $loanData = [
                'user_id' => $targetUserId,
                'loan_type' => $loanConfig['base_type'],
                'loan_amount' => $this->loanAmount,
                'interest_rate' => $loanConfig['interest_rate'],
                'term_months' => $this->loanTerm,
                'payment_frequency' => $this->loanPaymentFrequency,
                'loan_status' => 'pending',
                'applied_at' => now(),
            ];

            if (! empty($loanConfig['requires_interview'])) {
                $loanData['employment_id'] = $employmentId;
                $loanData['interview_schedule'] = $this->interviewSchedule;
                $loanData['interview_status'] = 'scheduled';
            }

            $loan = Loan::create($loanData);

            $this->auditLogService->log(
                'loan.application_submitted',
                'Loan application submitted.',
                'loan',
                $loan,
                Auth::user(),
                [
                    'loan_type' => $loan->loan_type,
                    'loan_amount' => (float) $loan->loan_amount,
                    'payment_frequency' => $loan->payment_frequency,
                    'target_user_id' => $targetUserId,
                ]
            );

            if ($requiredCoMakers > 0 && count($this->groupMembers) > 0) {
                foreach ($this->groupMembers as $memberId) {
                    $loan->groupMembers()->attach($memberId, [
                        'has_approved' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            $message = 'Loan application submitted successfully! Reference: ' . $loan->loan_number;
            if (! empty($loanConfig['requires_interview'])) {
                $message .= ' | Interview scheduled for: ' . date('M d, Y h:i A', strtotime($this->interviewSchedule));
            }

            $this->dispatch('success', message: $message);
            $this->closeService();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Failed to submit loan application: ' . $e->getMessage());
        }
    }

    // Loan payment methods
    public function submitLoanPayment()
    {
        $this->validate($this->paymentStep1Rules());
        $this->validate($this->paymentStep2Rules());

        try {
            DB::beginTransaction();

            // Get target user first so loan ownership can be enforced.
            $targetUser = $this->isActingForMember()
                ? \App\Models\User::find($this->selectedMemberId)
                : Auth::user();

            if (! $targetUser) {
                $this->dispatch('error', message: 'Unable to resolve the member for this payment.');
                DB::rollBack();
                return;
            }

            $loan = Loan::query()
                ->whereKey($this->paymentLoanId)
                ->where('user_id', $targetUser->id)
                ->first();

            if (! $loan) {
                $this->dispatch('error', message: 'Selected loan is invalid for this member.');
                DB::rollBack();
                return;
            }

            $selectedSchedule = $this->findSelectedPaymentSchedule($loan);
            if (! $selectedSchedule) {
                $this->addError('paymentScheduleKey', 'Please select a valid payment schedule.');
                DB::rollBack();
                return;
            }

            $this->paymentAmount = $selectedSchedule['amount_due'];

            // Validate payment doesn't exceed remaining balance
            $remainingBalance = $loan->remainingBalance();
            if ($this->paymentAmount > $remainingBalance) {
                $this->dispatch('error', message: 'Payment amount (₱' . number_format($this->paymentAmount, 2) . ') exceeds remaining loan balance (₱' . number_format($remainingBalance, 2) . ')');
                DB::rollBack();
                return;
            }

            $account = $targetUser->accounts()->where('account_type', 'savings')->first();

            if (! $account) {
                $this->dispatch('error', message: 'No savings account found');
                DB::rollBack();
                return;
            }

            // Only online/digital wallet transactions are auto-approved
            // Counter transactions need treasurer approval
            $isAutoApprove = $this->paymentTransactionMode === 'online';

            $note = ($this->isActingForMember() ? 'Processed by officer - ' : '')
                . 'Loan payment for ' . $loan->loan_number
                . ' | ' . ($this->paymentTransactionMode === 'counter' ? 'Over the counter' : 'Online payment')
                . ($this->paymentNote ? ' | ' . $this->paymentNote : '');

            $transaction = Transaction::create([
                'account_id' => $account->id,
                'loan_id' => $loan->id,
                'transaction_type' => 'loan_payment',
                'amount' => $this->paymentAmount,
                'transaction_date' => now(),
                'status' => $isAutoApprove ? 'completed' : 'pending',
                'note' => $note,
                'recorded_by' => Auth::id(),
            ]);

            $this->auditLogService->log(
                'transaction.loan_payment_submitted',
                $isAutoApprove ? 'Loan payment submitted and auto-completed.' : 'Loan payment submitted and awaiting approval.',
                'transaction',
                $transaction,
                Auth::user(),
                [
                    'amount' => (float) $this->paymentAmount,
                    'mode' => $this->paymentTransactionMode,
                    'status' => $transaction->status,
                    'loan_id' => $loan->id,
                    'target_user_id' => $targetUser?->id,
                ]
            );

            // If auto-approved (online), check if loan is now fully paid
            if ($isAutoApprove) {
                $this->notificationService->notifyLoanPayment($loan, (float) $this->paymentAmount);

                $loan->refresh();
                $newRemainingBalance = $loan->remainingBalance();
                if ($newRemainingBalance <= 0) {
                    $loan->update(['loan_status' => 'paid_off']);
                }
            } else {
                $this->notificationService->notifyAccountActivity(
                    $targetUser,
                    'Loan Payment Submitted',
                    'Your loan payment of ₱' . number_format((float) $this->paymentAmount, 2) . ' is pending approval.',
                    'normal'
                );
            }

            DB::commit();

            $this->completedPaymentTransaction = $transaction->fresh();

            if ($this->paymentTransactionMode === 'online') {
                $this->paymentStep++;
            } else {
                $this->paymentStep = 99;
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            $this->dispatch('error', message: 'Failed to submit payment. Please try again or contact support.');
        }
    }

    public function completeOnlinePaymentPayment()
    {
        $this->paymentStep++;
    }

    private function loadLoanTypes(): void
    {
        $products = LoanProduct::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($products->isEmpty()) {
            $this->loanTypes = [
                'group' => [
                    'id' => null,
                    'code' => 'group',
                    'name' => 'Group Loan',
                    'description' => 'Requires 4 co-makers and no existing active loan.',
                    'base_type' => 'group',
                    'icon' => 'users',
                    'interest_rate' => 1.5,
                    'max_amount' => 5000,
                    'max_term' => 6,
                    'requires_no_existing_loan' => true,
                    'minimum_share_capital' => 0,
                    'minimum_membership_years' => 1,
                    'required_co_makers' => 4,
                    'requires_interview' => false,
                    'requires_special_eligibility' => false,
                ],
                'business' => [
                    'id' => null,
                    'code' => 'business',
                    'name' => 'Business Loan',
                    'description' => 'Requires share capital and interview.',
                    'base_type' => 'business',
                    'icon' => 'briefcase',
                    'interest_rate' => 3.0,
                    'max_amount' => 50000,
                    'max_term' => 12,
                    'requires_no_existing_loan' => false,
                    'minimum_share_capital' => 4000,
                    'minimum_membership_years' => 1,
                    'required_co_makers' => 0,
                    'requires_interview' => true,
                    'requires_special_eligibility' => false,
                ],
            ];

            return;
        }

        $this->loanTypes = $products
            ->mapWithKeys(fn(LoanProduct $product) => [
                $product->code => [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'description' => $product->description,
                    'base_type' => $product->base_type,
                    'icon' => $product->icon ?: 'wallet',
                    'interest_rate' => (float) $product->interest_rate,
                    'max_amount' => (float) $product->max_amount,
                    'max_term' => (int) $product->max_term,
                    'requires_no_existing_loan' => (bool) $product->requires_no_existing_loan,
                    'minimum_share_capital' => (float) $product->minimum_share_capital,
                    'minimum_membership_years' => (int) $product->minimum_membership_years,
                    'required_co_makers' => (int) $product->required_co_makers,
                    'requires_interview' => (bool) $product->requires_interview,
                    'requires_special_eligibility' => (bool) $product->requires_special_eligibility,
                ],
            ])
            ->toArray();
    }

    private function refreshLoanTypeWarnings(): void
    {
        $this->loanTypeWarnings = [];

        $targetUser = $this->resolveLoanTargetUser();
        if (! $targetUser) {
            return;
        }

        foreach ($this->loanTypes as $key => $config) {
            $this->loanTypeWarnings[$key] = $this->getLoanEligibilityError($targetUser, $config);
        }
    }

    private function resolveLoanTargetUser()
    {
        if ($this->isActingForMember()) {
            return \App\Models\User::find($this->selectedMemberId);
        }

        return Auth::user();
    }

    private function getLoanEligibilityError($user, array $loanConfig): ?string
    {
        if (! $user) {
            return 'Unable to verify applicant details.';
        }

        if (! empty($loanConfig['requires_no_existing_loan'])) {
            $activeLoanExists = Loan::query()
                ->where('user_id', $user->id)
                ->whereIn('loan_status', ['pending', 'approved', 'active'])
                ->exists();

            if ($activeLoanExists) {
                return 'This product requires no existing active or pending loan.';
            }
        }

        $requiredShareCapital = (float) ($loanConfig['minimum_share_capital'] ?? 0);
        if ($requiredShareCapital > 0) {
            $shareCapitalAccount = $user->accounts()->where('account_type', 'share-capital')->first();
            $shareCapitalBalance = (float) ($shareCapitalAccount?->balance ?? 0);

            if ($shareCapitalBalance < $requiredShareCapital) {
                return 'Requires at least P' . number_format($requiredShareCapital, 2) . ' share capital.';
            }
        }

        $minimumMembershipYears = (int) ($loanConfig['minimum_membership_years'] ?? 0);
        if ($minimumMembershipYears > 0) {
            $memberYears = (int) $user->created_at?->diffInYears(now());
            if ($memberYears < $minimumMembershipYears) {
                return 'Requires at least ' . $minimumMembershipYears . ' year(s) of membership.';
            }
        }

        if (! empty($loanConfig['requires_special_eligibility']) && ! ($user->special_loan_eligible ?? false)) {
            return 'This product requires special-loan eligibility approval.';
        }

        return null;
    }

    public function getMonthlyPaymentProperty()
    {
        if (! $this->loanAmount || ! $this->loanTerm || ! $this->loanType) {
            return 0;
        }

        $loanConfig = $this->loanTypes[$this->loanType] ?? null;
        if (! $loanConfig) {
            return 0;
        }

        $principal = $this->loanAmount;
        $rate = $loanConfig['interest_rate'] / 100 / 12;
        $months = $this->loanTerm;

        if ($rate == 0) {
            return $principal / $months;
        }

        return $principal * ($rate * pow(1 + $rate, $months)) / (pow(1 + $rate, $months) - 1);
    }

    public function getAvailableBalanceProperty()
    {
        $targetUser = $this->isActingForMember()
            ? \App\Models\User::find($this->selectedMemberId)
            : Auth::user();

        if (! $targetUser) {
            return 0;
        }

        $savingsAccount = $targetUser->accounts()->where('account_type', 'savings')->first();
        if (! $savingsAccount) {
            return 0;
        }

        // Calculate pending withdrawals
        $pendingWithdrawals = Transaction::where('account_id', $savingsAccount->id)
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'pending')
            ->sum('amount');

        return max(0, $savingsAccount->balance - $pendingWithdrawals);
    }

    /**
     * Get pending withdrawal requests for the current user
     */
    public function getPendingWithdrawalsProperty()
    {
        if ($this->isActingForMember()) {
            $user = \App\Models\User::find($this->selectedMemberId);
        } else {
            $user = Auth::user();
        }

        if (!$user) {
            return collect();
        }

        $accountIds = $user->accounts()->pluck('id');

        return Transaction::whereIn('account_id', $accountIds)
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ─────────────────────────────────────────────────────────────
    // Validation rule builders — single source of truth per workflow
    // ─────────────────────────────────────────────────────────────

    private function depositRules(): array
    {
        return [
            'depositAmount'          => 'required|numeric|min:100',
            'depositTransactionMode' => 'required|in:counter,online',
            'depositProof'           => 'required_if:depositTransactionMode,online|nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'depositNote'            => 'nullable|string|max:255',
        ];
    }

    private function withdrawalRules(float $availableBalance): array
    {
        return [
            'withdrawalAmount'          => "required|numeric|min:100|max:{$availableBalance}",
            'withdrawalReason'          => 'required|string|max:255',
            'withdrawalTransactionMode' => 'required|in:counter,online',
        ];
    }

    private function loanDetailsRules(array $loanConfig): array
    {
        $rules = [
            'loanAmount'           => "required|numeric|min:100|max:{$loanConfig['max_amount']}",
            'loanTerm'             => "required|integer|min:1|max:{$loanConfig['max_term']}",
            'loanPaymentFrequency' => 'required|in:weekly,monthly',
        ];

        if (! empty($loanConfig['requires_interview'])) {
            $rules['interviewSchedule'] = 'required|date|after:now';
        }

        return $rules;
    }

    private function loanPurposeRules(): array
    {
        return [
            'loanPurpose' => 'required|string|min:10|max:500',
        ];
    }

    private function loanSubmitRules(array $loanConfig): array
    {
        return array_merge(
            ['loanType' => 'required|in:' . implode(',', array_keys($this->loanTypes))],
            $this->loanDetailsRules($loanConfig),
            $this->loanPurposeRules(),
        );
    }

    private function paymentStep1Rules(): array
    {
        return [
            'paymentLoanId' => 'required|exists:loans,id',
            'paymentScheduleKey' => 'required',
        ];
    }

    private function paymentStep2Rules(): array
    {
        return [
            'paymentTransactionMode' => 'required|in:counter,online',
        ];
    }

    private function resolveSelectedPaymentLoan(): ?Loan
    {
        if (blank($this->paymentLoanId)) {
            return null;
        }

        $targetUser = $this->isActingForMember()
            ? \App\Models\User::find($this->selectedMemberId)
            : Auth::user();

        if (! $targetUser) {
            return null;
        }

        return Loan::query()
            ->whereKey($this->paymentLoanId)
            ->where('user_id', $targetUser->id)
            ->first();
    }

    public function getAvailablePaymentSchedules(Loan $loan): array
    {
        $paidAmount = (float) $loan->completedPayments()->sum('amount');
        $schedule = $this->generateAmortizationSchedule($loan);
        $availableSchedules = [];
        $cumulativeAmount = 0.0;

        foreach ($schedule as $scheduleEntry) {
            $entryAmount = (float) $scheduleEntry['payment_amount'];
            $coveredAmount = min(max($paidAmount - $cumulativeAmount, 0), $entryAmount);
            $amountDue = round($entryAmount - $coveredAmount, 2);
            $cumulativeAmount += $entryAmount;

            if ($amountDue <= 0) {
                continue;
            }

            $availableSchedules[] = [
                'key' => (string) $scheduleEntry['payment_number'],
                'payment_number' => $scheduleEntry['payment_number'],
                'due_date' => $scheduleEntry['due_date'],
                'payment_amount' => round($entryAmount, 2),
                'amount_due' => $amountDue,
            ];
        }

        return $availableSchedules;
    }

    public function findSelectedPaymentSchedule(Loan $loan): ?array
    {
        foreach ($this->getAvailablePaymentSchedules($loan) as $scheduleEntry) {
            if ((string) $scheduleEntry['key'] === (string) $this->paymentScheduleKey) {
                return $scheduleEntry;
            }
        }

        return null;
    }

    private function generateAmortizationSchedule(Loan $loan): array
    {
        $principal = (float) $loan->loan_amount;
        $termMonths = (int) ($loan->term_months ?: 0);

        $totalPeriods = match ($loan->payment_frequency) {
            'weekly' => $termMonths * 4.33,
            'bi-weekly' => $termMonths * 2.17,
            'monthly' => $termMonths,
            'quarterly' => $termMonths / 3,
            'annually' => $termMonths / 12,
            default => $termMonths,
        };

        if ($totalPeriods <= 0) {
            return [];
        }

        $roundedPeriods = max(1, (int) ceil($totalPeriods));
        $totalInterest = method_exists($loan, 'totalInterest')
            ? $loan->totalInterest()
            : round($principal * ((float) $loan->interest_rate / 100) * max(1, $termMonths), 2);
        $totalPayable = round($principal + $totalInterest, 2);
        $principalPerPeriod = $principal / $roundedPeriods;
        $interestPerPeriod = $totalInterest / $roundedPeriods;

        $schedule = [];
        $balance = $principal;
        $startDate = $loan->approved_at ?? $loan->applied_at ?? now();

        for ($i = 1; $i <= $roundedPeriods; $i++) {
            $interestPayment = $interestPerPeriod;
            $principalPayment = $principalPerPeriod;
            $paymentAmount = $principalPayment + $interestPayment;

            if ($i === $roundedPeriods) {
                $principalPayment = $balance;
                $paymentAmount = round($totalPayable - collect($schedule)->sum('payment_amount'), 2);
                $interestPayment = round($paymentAmount - $principalPayment, 2);
            }

            $balance -= $principalPayment;

            $dueDate = match ($loan->payment_frequency) {
                'weekly' => $startDate->copy()->addWeeks($i),
                'bi-weekly' => $startDate->copy()->addWeeks($i * 2),
                'monthly' => $startDate->copy()->addMonths($i),
                'quarterly' => $startDate->copy()->addMonths($i * 3),
                'annually' => $startDate->copy()->addYears($i),
                default => $startDate->copy()->addMonths($i),
            };

            $schedule[] = [
                'payment_number' => $i,
                'due_date' => $dueDate,
                'payment_amount' => round($paymentAmount, 2),
                'principal' => round($principalPayment, 2),
                'interest' => round($interestPayment, 2),
                'balance' => round(max($balance, 0), 2),
            ];
        }

        return $schedule;
    }

    public function render()
    {
        $currentUser = Auth::user();

        // Get target user based on mode
        $targetUser = $this->isActingForMember()
            ? \App\Models\User::find($this->selectedMemberId)
            : $currentUser;

        $hideSelectedMemberFinancials = $this->isLoanOfficerFocusedMode($currentUser);

        $savingsAccount = $targetUser?->accounts()->where('account_type', 'savings')->first();
        $shareCapitalAccount = $targetUser?->accounts()->where('account_type', 'share-capital')->first();
        $activeLoans = $targetUser?->loans()->whereIn('loan_status', ['approved', 'active'])->get() ?? collect();

        if ($hideSelectedMemberFinancials) {
            $savingsAccount = null;
            $shareCapitalAccount = null;
        }

        // Admins and designated approvers see all pending requests and can approve
        $canApproveTransactions = $this->isTreasurerMode || $this->isAdminMode;

        // For members: only count their own pending requests
        // For approvers/admins: count all pending requests if viewing approval section
        if ($canApproveTransactions) {
            $pendingRequests = Transaction::whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_payment'])
                ->where('status', 'pending')
                ->count();

            // Get all pending transactions for approval view
            $pendingTransactions = Transaction::with(['account.user.personalData', 'loan'])
                ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_payment'])
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $pendingRequests = $targetUser ? Transaction::whereIn('account_id', $targetUser->accounts->pluck('id'))
                ->whereIn('transaction_type', ['deposit', 'withdrawal'])
                ->where('status', 'pending')
                ->count() : 0;

            $pendingTransactions = collect();
        }

        // Get members list for roles that can process services on behalf of members.
        $members = collect();
        if ($this->canManageMemberServices) {
            $members = \App\Models\User::query()->memberUsers()
                ->with('personalData')
                ->when($this->memberSearch, function ($query) {
                    $query->whereHas('personalData', function ($q) {
                        $q->where('first_name', 'like', '%' . $this->memberSearch . '%')
                            ->orWhere('last_name', 'like', '%' . $this->memberSearch . '%');
                    });
                })
                ->limit(10)
                ->get();
        }

        // Build review queue for loan review officers
        $reviewableLoans = collect();
        $pendingLoanReviews = 0;
        if ($this->isLoanReviewMode) {
            $reviewableLoans = Loan::with(['user.personalData', 'reviewer', 'groupMembers.personalData'])
                ->where('loan_status', 'pending')
                ->where(function ($q) {
                    $q->whereNull('review_status')
                        ->orWhereIn('review_status', ['under_review', 'endorsed_for_approval']);
                })
                ->orderBy('applied_at', 'asc')
                ->get();

            $pendingLoanReviews = $reviewableLoans->count();
        }

        return view('livewire.content.services', [
            'savingsAccount' => $savingsAccount,
            'shareCapitalAccount' => $shareCapitalAccount,
            'activeLoans' => $activeLoans,
            'pendingRequests' => $pendingRequests,
            'pendingTransactions'  => $pendingTransactions,
            'members'              => $members,
            'reviewableLoans'      => $reviewableLoans,
            'pendingLoanReviews'   => $pendingLoanReviews,
            'availableServices'    => $this->allowedServicesForCurrentUser($currentUser),
            'hideSelectedMemberFinancials' => $hideSelectedMemberFinancials,
            'selectedMember'       => $this->selectedMemberId ? \App\Models\User::with('personalData')->find($this->selectedMemberId) : null,
        ]);
    }
}
