<?php

namespace App\Livewire\Content;

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class Services extends Component
{
    use WithFileUploads;

    // View state
    public $activeService = null; // 'deposit', 'withdraw', 'loan', 'payment', 'approve'

    public $isTreasurerMode = false;

    public $selectedMemberId = null;

    public $memberSearch = '';

    public $selectedTransactionId = null;

    public $approvalAction = ''; // 'approve' or 'reject'

    public $rejectionReason = '';

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

    public $paymentAmount = '';

    public $paymentNote = '';

    public $paymentTransactionMode = '';

    public $completedPaymentTransaction = null;

    // Group loan members
    public $groupMembers = [];
    
    // Business loan fields
    public $interviewSchedule = '';
    public $hasRequiredShareCapital = false;

    public $loanTypes = [
        'group' => [
            'name' => 'Group Loan',
            'description' => 'Requires 5 members, each can borrow up to ₱5,000',
            'interest_rate' => 1.5,
            'max_amount' => 5000,
            'max_term' => 6,
            'icon' => 'users',
            'required_members' => 5,
        ],
        'business' => [
            'name' => 'Business Loan',
            'description' => 'Requires ₱4,000 share capital and business verification',
            'interest_rate' => 3.0,
            'max_amount' => 50000,
            'max_term' => 12,
            'icon' => 'briefcase',
            'required_share_capital' => 4000,
        ],
    ];

    public function mount()
    {
        // Check if user is treasurer, admin, or manager
        $userRole = Auth::user()->role;
        $this->isTreasurerMode = in_array($userRole, ['treasurer', 'admin', 'manager']);

        // If not treasurer mode, pre-select user's savings account for withdrawal
        if (! $this->isTreasurerMode) {
            $savingsAccount = Auth::user()->accounts()->where('account_type', 'savings')->first();
            if ($savingsAccount) {
                $this->withdrawalAccountId = $savingsAccount->id;
            }
            
            // Check if user has required share capital for business loan
            $shareCapitalAccount = Auth::user()->accounts()->where('account_type', 'share_capital')->first();
            $this->hasRequiredShareCapital = $shareCapitalAccount && $shareCapitalAccount->balance >= 4000;
        }
    }

    public function selectMember($memberId)
    {
        $this->selectedMemberId = $memberId;
        $this->memberSearch = '';

        // Pre-select member's savings account for withdrawal
        $member = \App\Models\User::find($memberId);
        if ($member) {
            $savingsAccount = $member->accounts()->where('account_type', 'savings')->first();
            if ($savingsAccount) {
                $this->withdrawalAccountId = $savingsAccount->id;
            }
            
            // Update share capital check for selected member (treasurer mode)
            $shareCapitalAccount = $member->accounts()->where('account_type', 'share_capital')->first();
            $this->hasRequiredShareCapital = $shareCapitalAccount && $shareCapitalAccount->balance >= 4000;
        }
    }

    public function clearMemberSelection()
    {
        $this->selectedMemberId = null;
        $this->withdrawalAccountId = '';
    }

    public function viewTransactionForApproval($transactionId)
    {
        $this->selectedTransactionId = $transactionId;
    }

    public function approveTransaction()
    {
        if (! $this->isTreasurerMode || ! $this->selectedTransactionId) {
            return;
        }

        try {
            DB::beginTransaction();

            $transaction = Transaction::with(['account', 'loan'])->findOrFail($this->selectedTransactionId);

            // Check if already processed
            if ($transaction->status !== 'pending') {
                $this->dispatch('warning', message: 'Transaction has already been processed.');
                $this->selectedTransactionId = null;
                return;
            }

            // For withdrawals, verify the account still has sufficient balance
            if ($transaction->transaction_type === 'withdrawal') {
                if ($transaction->account->balance < $transaction->amount) {
                    $this->dispatch('error', message: 'Insufficient balance. Current balance: ₱' . number_format($transaction->account->balance, 2));
                    DB::rollBack();
                    return;
                }
            }

            // Update status to completed
            $transaction->status = 'completed';
            $transaction->note = ($transaction->note ? $transaction->note.' | ' : '').'Approved by '.Auth::user()->personalData->first_name.' '.Auth::user()->personalData->last_name;
            $transaction->save();

            // Update account balance
            if ($transaction->transaction_type === 'deposit') {
                $transaction->account->increment('balance', $transaction->amount);
            } elseif ($transaction->transaction_type === 'withdrawal') {
                $transaction->account->decrement('balance', $transaction->amount);
            } elseif ($transaction->transaction_type === 'loan_payment' && $transaction->loan) {
                // Check if loan is now fully paid
                $loan = $transaction->loan;
                $loan->refresh(); // Refresh to get updated balance after this payment
                
                // remainingBalance() now calculates based on completed payments
                // But since we just marked this as completed, we need to check
                $remainingBalance = $loan->loan_amount - $loan->completedPayments()->sum('amount');
                
                if ($remainingBalance <= 0) {
                    $loan->update(['loan_status' => 'paid']);
                }
            }

            DB::commit();;

            $this->dispatch('success', message: 'Transaction approved successfully!');
            $this->selectedTransactionId = null;
            $this->approvalAction = '';
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Failed to approve transaction: '.$e->getMessage());
        }
    }

    public function rejectTransaction()
    {
        $this->validate([
            'rejectionReason' => 'required|string|min:5',
        ]);

        if (! $this->isTreasurerMode || ! $this->selectedTransactionId) {
            return;
        }

        try {
            DB::beginTransaction();

            $transaction = Transaction::with('account')->findOrFail($this->selectedTransactionId);

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
            $transaction->note = ($transaction->note ? $transaction->note.' | ' : '').'REJECTED: '.$this->rejectionReason.' (by '.Auth::user()->personalData->first_name.' '.Auth::user()->personalData->last_name.')';
            $transaction->save();

            DB::commit();

            $this->dispatch('success', message: 'Transaction rejected and reversed successfully.');
            $this->selectedTransactionId = null;
            $this->rejectionReason = '';
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Failed to reject transaction: '.$e->getMessage());
        }
    }

    public function cancelApproval()
    {
        $this->selectedTransactionId = null;
        $this->rejectionReason = '';
        $this->approvalAction = '';
    }

    public function openService($service)
    {
        // Validate that a member is selected in treasurer mode (except for 'approve' service)
        if ($this->isTreasurerMode && $service !== 'approve' && !$this->selectedMemberId) {
            $this->dispatch('warning', message: 'Please select a member first before accessing services.');
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
            'depositStep', 'depositAmount', 'depositTransactionMode', 'depositProof', 'depositNote', 'completedDepositTransaction',
            'withdrawalStep', 'withdrawalAmount', 'withdrawalReason', 'withdrawalTransactionMode', 'completedWithdrawalTransaction',
            'paymentStep', 'loanStep', 'loanType', 'loanAmount', 'loanTerm', 'loanPurpose', 'loanPaymentFrequency',
            'paymentLoanId', 'paymentAmount', 'paymentNote', 'paymentTransactionMode', 'completedPaymentTransaction',
            'groupMembers', 'interviewSchedule',
        ]);
    }

    // Deposit wizard methods
    public function nextDepositStep()
    {
        if ($this->depositStep === 1) {
            $this->validate([
                'depositAmount' => 'required|numeric|min:100',
                'depositNote' => 'nullable|string|max:255',
            ]);
        } elseif ($this->depositStep === 2) {
            $this->validate([
                'depositTransactionMode' => 'required|in:counter,online',
            ]);
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
        $this->validate([
            'depositAmount' => 'required|numeric|min:100',
            'depositTransactionMode' => 'required|in:counter,online',
            'depositNote' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // Get target user (member if treasurer mode, or auth user)
            $targetUser = $this->isTreasurerMode && $this->selectedMemberId
                ? \App\Models\User::find($this->selectedMemberId)
                : Auth::user();

            $account = $targetUser->accounts()
                ->where('account_type', $this->depositAccountType)
                ->first();

            if (! $account) {
                $this->dispatch('error', message: 'Account not found');

                return;
            }

            // Only online/digital wallet transactions are auto-approved
            // Counter transactions need treasurer approval
            $isAutoApprove = $this->depositTransactionMode === 'online';

            // Create transaction
            $note = ($this->isTreasurerMode ? 'Processed by treasurer - ' : '')
                .($this->depositTransactionMode === 'counter' ? 'Over the counter' : 'Online payment')
                .($this->depositNote ? ' | '.$this->depositNote : '');

            $transaction = Transaction::create([
                'account_id' => $account->id,
                'transaction_type' => 'deposit',
                'amount' => $this->depositAmount,
                'transaction_date' => now(),
                'status' => $isAutoApprove ? 'completed' : 'pending',
                'note' => $note,
                'recorded_by' => Auth::id(),
            ]);

            // Update account balance only if auto-approved (online)
            if ($isAutoApprove) {
                $account->increment('balance', $this->depositAmount);
            }

            DB::commit();

            // Store transaction for receipt/confirmation
            $this->completedDepositTransaction = $transaction->fresh();

            // Move to appropriate step
            if ($this->depositTransactionMode === 'online') {
                $this->depositStep++; // Go to payment portal step
            } else {
                $this->depositStep = 99; // Go to success message
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Failed to submit deposit request: '.$e->getMessage());
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
            $this->validate([
                'withdrawalAmount' => 'required|numeric|min:100|max:'.$availableBalance,
                'withdrawalReason' => 'required|string|max:255',
            ]);
        } elseif ($this->withdrawalStep === 2) {
            $this->validate([
                'withdrawalTransactionMode' => 'required|in:counter,online',
            ]);
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

        // Calculate available balance (current balance minus pending withdrawals)
        $pendingWithdrawals = Transaction::where('account_id', $this->withdrawalAccountId)
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'pending')
            ->sum('amount');
        
        $availableBalance = $account->balance - $pendingWithdrawals;

        $this->validate([
            'withdrawalAmount' => 'required|numeric|min:100|max:'.$availableBalance,
            'withdrawalReason' => 'required|string|max:255',
            'withdrawalTransactionMode' => 'required|in:counter,online',
        ]);

        try {
            DB::beginTransaction();

            // Only online/digital wallet transactions are auto-approved
            // Counter transactions need treasurer approval
            $isAutoApprove = $this->withdrawalTransactionMode === 'online';

            $note = ($this->isTreasurerMode ? 'Processed by treasurer - ' : '')
                .$this->withdrawalReason.' | '
                .($this->withdrawalTransactionMode === 'counter' ? 'Over the counter' : 'Online payment');

            $transaction = Transaction::create([
                'account_id' => $this->withdrawalAccountId,
                'transaction_type' => 'withdrawal',
                'amount' => $this->withdrawalAmount,
                'transaction_date' => now(),
                'status' => $isAutoApprove ? 'completed' : 'pending',
                'note' => $note,
                'recorded_by' => Auth::id(),
            ]);

            // Update account balance only if auto-approved (online)
            if ($isAutoApprove) {
                $account->decrement('balance', $this->withdrawalAmount);
            }

            DB::commit();

            $this->completedWithdrawalTransaction = $transaction->fresh();

            if ($this->withdrawalTransactionMode === 'online') {
                $this->withdrawalStep++;
            } else {
                $this->withdrawalStep = 99;
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Failed to submit withdrawal: '.$e->getMessage());
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
            $this->validate([
                'paymentLoanId' => 'required|exists:loans,id',
                'paymentAmount' => 'required|numeric|min:100',
            ]);

            // Check if payment amount exceeds remaining balance
            $loan = Loan::find($this->paymentLoanId);
            if ($loan && $this->paymentAmount > $loan->remainingBalance()) {
                $this->addError('paymentAmount', 'Payment exceeds remaining loan balance (₱' . number_format($loan->remainingBalance(), 2) . ')');
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

    // Loan application methods
    public function selectLoanType($type)
    {
        $this->loanType = $type;
        
        // Reset group members when selecting group loan
        if ($type === 'group') {
            $this->groupMembers = [];
        }
        
        // Check share capital for business loan
        if ($type === 'business' && !$this->hasRequiredShareCapital) {
            $this->dispatch('warning', message: 'Business loan requires ₱4,000 share capital. You currently do not meet this requirement.');
            return;
        }
        
        $this->loanStep = 2;
    }
    
    public function addGroupMember($userId)
    {
        if (count($this->groupMembers) >= 4) {
            $this->dispatch('error', message: 'Maximum 4 co-makers allowed (5 total including you)');
            return;
        }
        
        if (in_array($userId, $this->groupMembers)) {
            $this->dispatch('warning', message: 'Member already added to group');
            return;
        }
        
        // Don't allow adding yourself
        $currentUserId = $this->isTreasurerMode && $this->selectedMemberId 
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
        $this->groupMembers = array_values(array_filter($this->groupMembers, function($id) use ($userId) {
            return $id != $userId;
        }));
    }

    public function nextLoanStep()
    {
        if ($this->loanStep === 2) {
            // Amount and term validation
            $rules = [
                'loanAmount' => 'required|numeric|min:100|max:'.$this->loanTypes[$this->loanType]['max_amount'],
                'loanTerm' => 'required|integer|min:1|max:'.$this->loanTypes[$this->loanType]['max_term'],
                'loanPaymentFrequency' => 'required|in:weekly,monthly',
            ];
            
            // Group loan specific validation
            if ($this->loanType === 'group') {
                if (count($this->groupMembers) !== 4) {
                    $this->dispatch('error', message: 'Group loan requires exactly 4 co-makers (5 members total)');
                    return;
                }
            }
            
            // Business loan specific validation
            if ($this->loanType === 'business') {
                $rules['interviewSchedule'] = 'required|date|after:now';
            }
            
            $this->validate($rules);
        } elseif ($this->loanStep === 3) {
            $this->validate([
                'loanPurpose' => 'required|string|min:10|max:500',
            ]);
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
        // Validate loan data before submission
        $this->validate([
            'loanType' => 'required|in:group,business',
            'loanAmount' => 'required|numeric|min:100|max:'.$this->loanTypes[$this->loanType]['max_amount'],
            'loanTerm' => 'required|integer|min:1|max:'.$this->loanTypes[$this->loanType]['max_term'],
            'loanPaymentFrequency' => 'required|in:weekly,monthly',
            'loanPurpose' => 'required|string|min:10|max:500',
        ]);

        // Additional validations based on loan type
        if ($this->loanType === 'group' && count($this->groupMembers) !== 4) {
            $this->dispatch('error', message: 'Group loan requires exactly 4 co-makers (5 members total)');
            return;
        }

        if ($this->loanType === 'business') {
            if (! $this->hasRequiredShareCapital) {
                $this->dispatch('error', message: 'Business loan requires at least ₱4,000 share capital');
                return;
            }
            if (empty($this->interviewSchedule)) {
                $this->dispatch('error', message: 'Interview schedule is required for business loans');
                return;
            }
        }

        try {
            DB::beginTransaction();

            // Get target user
            $targetUser = $this->isTreasurerMode && $this->selectedMemberId
                ? \App\Models\User::find($this->selectedMemberId)
                : Auth::user();
                
            $targetUserId = $targetUser->id;

            // Get employment info for business loan
            $employmentId = null;
            if ($this->loanType === 'business' && $targetUser->personalData && $targetUser->personalData->employment) {
                $employmentId = $targetUser->personalData->employment->id;
            }

            $loanData = [
                'user_id' => $targetUserId,
                'loan_type' => $this->loanType,
                'loan_amount' => $this->loanAmount,
                'interest_rate' => $this->loanTypes[$this->loanType]['interest_rate'],
                'term_months' => $this->loanTerm,
                'payment_frequency' => $this->loanPaymentFrequency,
                'loan_status' => 'pending',
                'applied_at' => now(),
            ];
            
            // Add business loan specific fields
            if ($this->loanType === 'business') {
                $loanData['employment_id'] = $employmentId;
                $loanData['interview_schedule'] = $this->interviewSchedule;
                $loanData['interview_status'] = 'scheduled';
            }

            $loan = Loan::create($loanData);
            
            // Add group members if group loan
            if ($this->loanType === 'group' && count($this->groupMembers) > 0) {
                foreach ($this->groupMembers as $memberId) {
                    $loan->groupMembers()->attach($memberId, [
                        'has_approved' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            $message = 'Loan application submitted successfully! Reference: '.$loan->loan_number;
            if ($this->loanType === 'business') {
                $message .= ' | Interview scheduled for: '.date('M d, Y h:i A', strtotime($this->interviewSchedule));
            }
            
            $this->dispatch('success', message: $message);
            $this->closeService();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Failed to submit loan application: '.$e->getMessage());
        }
    }

    // Loan payment methods
    public function submitLoanPayment()
    {
        $this->validate([
            'paymentLoanId' => 'required|exists:loans,id',
            'paymentAmount' => 'required|numeric|min:100',
        ]);

        try {
            DB::beginTransaction();

            $loan = Loan::find($this->paymentLoanId);

            // Validate payment doesn't exceed remaining balance
            $remainingBalance = $loan->remainingBalance();
            if ($this->paymentAmount > $remainingBalance) {
                $this->dispatch('error', message: 'Payment amount (₱' . number_format($this->paymentAmount, 2) . ') exceeds remaining loan balance (₱' . number_format($remainingBalance, 2) . ')');
                DB::rollBack();
                return;
            }

            // Get target user
            $targetUser = $this->isTreasurerMode && $this->selectedMemberId
                ? \App\Models\User::find($this->selectedMemberId)
                : Auth::user();

            $account = $targetUser->accounts()->where('account_type', 'savings')->first();

            if (! $account) {
                $this->dispatch('error', message: 'No savings account found');
                DB::rollBack();
                return;
            }

            // Only online/digital wallet transactions are auto-approved
            // Counter transactions need treasurer approval
            $isAutoApprove = $this->paymentTransactionMode === 'online';

            $note = ($this->isTreasurerMode ? 'Processed by treasurer - ' : '')
                .'Loan payment for '.$loan->loan_number
                .' | '.($this->paymentTransactionMode === 'counter' ? 'Over the counter' : 'Online payment')
                .($this->paymentNote ? ' | '.$this->paymentNote : '');

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

            // If auto-approved (online), check if loan is now fully paid
            if ($isAutoApprove) {
                $newRemainingBalance = $loan->loan_amount - $loan->completedPayments()->sum('amount');
                if ($newRemainingBalance <= 0) {
                    $loan->update(['loan_status' => 'paid']);
                }
            }

            DB::commit();;

            $this->completedPaymentTransaction = $transaction->fresh();

            if ($this->paymentTransactionMode === 'online') {
                $this->paymentStep++;
            } else {
                $this->paymentStep = 99;
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('error', message: 'Failed to submit payment: '.$e->getMessage());
        }
    }

    public function completeOnlinePaymentPayment()
    {
        $this->paymentStep++;
    }

    public function getMonthlyPaymentProperty()
    {
        if (! $this->loanAmount || ! $this->loanTerm || ! $this->loanType) {
            return 0;
        }

        $principal = $this->loanAmount;
        $rate = $this->loanTypes[$this->loanType]['interest_rate'] / 100 / 12;
        $months = $this->loanTerm;

        if ($rate == 0) {
            return $principal / $months;
        }

        return $principal * ($rate * pow(1 + $rate, $months)) / (pow(1 + $rate, $months) - 1);
    }

    public function getAvailableBalanceProperty()
    {
        $targetUser = $this->isTreasurerMode && $this->selectedMemberId
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

    public function render()
    {
        // Get target user based on treasurer mode
        $targetUser = $this->isTreasurerMode && $this->selectedMemberId
            ? \App\Models\User::find($this->selectedMemberId)
            : Auth::user();

        $savingsAccount = $targetUser?->accounts()->where('account_type', 'savings')->first();
        $shareCapitalAccount = $targetUser?->accounts()->where('account_type', 'share_capital')->first();
        $activeLoans = $targetUser?->loans()->whereIn('loan_status', ['approved', 'active'])->get() ?? collect();

        // For members: only count their own pending requests
        // For treasurers: count all pending requests if viewing approval section
        if ($this->isTreasurerMode) {
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

        // Get members list if treasurer
        $members = collect();
        if ($this->isTreasurerMode) {
            $members = \App\Models\User::where('role', 'member')
                ->with('personalData')
                ->when($this->memberSearch, function ($query) {
                    $query->whereHas('personalData', function ($q) {
                        $q->where('first_name', 'like', '%'.$this->memberSearch.'%')
                            ->orWhere('last_name', 'like', '%'.$this->memberSearch.'%');
                    });
                })
                ->limit(10)
                ->get();
        }

        return view('livewire.content.services', [
            'savingsAccount' => $savingsAccount,
            'shareCapitalAccount' => $shareCapitalAccount,
            'activeLoans' => $activeLoans,
            'pendingRequests' => $pendingRequests,
            'pendingTransactions' => $pendingTransactions,
            'members' => $members,
            'selectedMember' => $this->selectedMemberId ? \App\Models\User::with('personalData')->find($this->selectedMemberId) : null,
        ]);
    }
}
