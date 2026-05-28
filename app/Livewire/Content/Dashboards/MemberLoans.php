<?php

namespace App\Livewire\Content\Dashboards;

use App\Models\Loan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Member Loans Component
 * 
 * Allows members to view their personal and group loans with detailed
 * payment history and amortization schedules. Read-only view.
 */
class MemberLoans extends Component
{
    use WithPagination;

    // Search and sorting
    public $search = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    // View details modal
    public $showModal = false;
    public $selectedLoan = null;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * Load loan details with payment history and amortization schedule
     * 
     * Eager loads:
     * - user.personalData: Borrower information
     * - approvedBy: Loan officer who approved
     * - completedPayments: Payment history
     * - groupMembers: Co-borrowers for group loans
     */
    public function viewDetails($loanId)
    {
        $this->selectedLoan = Loan::with([
            'user.personalData',
            'approvedBy',
            'completedPayments',
            'groupMembers.personalData'
        ])->find($loanId);

        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedLoan = null;
    }

    /**
     * Generate amortization schedule for a loan
     * 
     * Calculates payment schedule based on loan amount, interest rate,
     * term, and payment frequency using standard amortization formulas.
     */
    public function generateAmortizationSchedule($loan)
    {
        $principal = floatval($loan->loan_amount);
        $termMonths = intval($loan->term_months);

        $totalPeriods = match ($loan->payment_frequency) {
            'weekly' => $termMonths * 4.33,
            'bi-weekly' => $termMonths * 2.17,
            'monthly' => $termMonths,
            'quarterly' => $termMonths / 3,
            'annually' => $termMonths / 12,
            default => $termMonths
        };

        if ($totalPeriods <= 0) {
            return [];
        }

        $roundedPeriods = max(1, (int) ceil($totalPeriods));
        $totalInterest = method_exists($loan, 'totalInterest')
            ? $loan->totalInterest()
            : round($principal * (floatval($loan->interest_rate) / 100) * max(1, $termMonths), 2);
        $totalPayable = round($principal + $totalInterest, 2);
        $principalPerPeriod = $principal / $roundedPeriods;
        $interestPerPeriod = $totalInterest / $roundedPeriods;

        $schedule = [];
        $balance = $principal;
        $startDate = $loan->approved_at ?? $loan->applied_at;

        for ($i = 1; $i <= $roundedPeriods; $i++) {
            $interestPayment = $interestPerPeriod;
            $principalPayment = $principalPerPeriod;
            $paymentAmount = $principalPayment + $interestPayment;

            // Adjust last payment to zero out balance
            if ($i == $roundedPeriods) {
                $principalPayment = $balance;
                $paymentAmount = round($totalPayable - collect($schedule)->sum('payment_amount'), 2);
                $interestPayment = round($paymentAmount - $principalPayment, 2);
            }

            $balance -= $principalPayment;

            // Calculate due date based on frequency
            $dueDate = match ($loan->payment_frequency) {
                'weekly' => $startDate->copy()->addWeeks($i),
                'bi-weekly' => $startDate->copy()->addWeeks($i * 2),
                'monthly' => $startDate->copy()->addMonths($i),
                'quarterly' => $startDate->copy()->addMonths($i * 3),
                'annually' => $startDate->copy()->addYears($i),
                default => $startDate->copy()->addMonths($i)
            };

            $schedule[] = [
                'payment_number' => $i,
                'due_date' => $dueDate,
                'payment_amount' => round($paymentAmount, 2),
                'principal' => round($principalPayment, 2),
                'interest' => round($interestPayment, 2),
                'balance' => round(max($balance, 0), 2)
            ];
        }

        return $schedule;
    }

    /**
     * Render member loans list
     * 
     * Shows personal loans (user_id matches) and group loans
     * (member is in groupMembers relationship).
     */
    public function render()
    {
        $userId = Auth::id();

        $loans = Loan::with(['user.personalData', 'approvedBy'])
            ->where(function ($query) use ($userId) {
                // Personal loans
                $query->where('user_id', $userId)
                    // OR group loans where user is a member
                    ->orWhereHas('groupMembers', function ($q) use ($userId) {
                        $q->where('users.id', $userId);
                    });
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('loan_number', 'like', '%' . $this->search . '%')
                        ->orWhere('loan_type', 'like', '%' . $this->search . '%')
                        ->orWhere('loan_amount', 'like', '%' . $this->search . '%')
                        ->orWhere('loan_status', 'like', '%' . $this->search . '%')
                        ->orWhere('interest_rate', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('livewire.content.dashboards.member-loans', [
            'loans' => $loans,
        ]);
    }
}
