<?php

namespace App\Livewire\Content;

use App\Models\Loan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Loans Component 
 * 
 * Manages loan viewing and tracking. Displays all loans with associated
 * borrower information and payment history. Read-only view with details modal.
 */
class Loans extends Component
{
    use WithPagination;

    // Search and sorting
    public $search = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    // Row selection
    public $selectedRows = [];
    public $selectAll = false;

    // View details modal
    public $showModal = false;
    public $selectedLoan = null;  // Includes loan with payments relationship

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
     * Load loan details with payment history
     * 
     * Eager loads:
     * - user.personalData: Borrower information
     * - approvedBy: Loan officer who approved
     * - completedPayments: Complete payment history for the loan
     * - groupMembers.personalData: Co-borrowers for group loans
     */
    public function viewDetails($loanId)
    {
        $this->selectedLoan = Loan::with([
            'user.personalData',
            'approvedBy',
            'completedPayments',
            'groupMembers.personalData',
        ])->find($loanId);
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedLoan = null;
    }

    /**
     * Generate an amortization schedule for a loan.
     *
     * This is used by the loan detail modal to render the payment schedule tab.
     */
    public function generateAmortizationSchedule($loan): array
    {
        $principal = (float) $loan->loan_amount;
        $annualRate = (float) $loan->interest_rate / 100;
        $termMonths = (int) ($loan->term_months ?: 0);

        $periodsPerYear = match ($loan->payment_frequency) {
            'weekly' => 52,
            'bi-weekly' => 26,
            'monthly' => 12,
            'quarterly' => 4,
            'annually' => 1,
            default => 12,
        };

        $periodRate = $annualRate / $periodsPerYear;
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

        if ($periodRate > 0) {
            $periodicPayment = $principal * ($periodRate * pow(1 + $periodRate, $totalPeriods)) /
                (pow(1 + $periodRate, $totalPeriods) - 1);
        } else {
            $periodicPayment = $principal / $totalPeriods;
        }

        $schedule = [];
        $balance = $principal;
        $startDate = $loan->approved_at ?? $loan->applied_at ?? now();

        for ($i = 1; $i <= ceil($totalPeriods); $i++) {
            $interestPayment = $balance * $periodRate;
            $principalPayment = $periodicPayment - $interestPayment;

            if ($i === ceil($totalPeriods)) {
                $principalPayment = $balance;
                $periodicPayment = $principalPayment + $interestPayment;
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
                'payment_amount' => round($periodicPayment, 2),
                'principal' => round($principalPayment, 2),
                'interest' => round($interestPayment, 2),
                'balance' => round(max($balance, 0), 2),
            ];
        }

        return $schedule;
    }

    /**
     * Render loans list
     * 
     * Simpler than Ledger - single model query with standard pagination.
     * Loan officers, treasurers, managers, and admins can view/edit.
     */
    public function render()
    {
        $canEdit = Auth::user()->can('loans.update') || Auth::user()->can('loans.approve');

        // Standard Eloquent query with pagination 
        $loans = Loan::with(['account.user.personalData'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    // Search by loan number
                    $q->where('loan_number', 'like', '%' . $this->search . '%')
                        // Search by loan type
                        ->orWhere('loan_type', 'like', '%' . $this->search . '%')
                        // Search by amount
                        ->orWhere('amount', 'like', '%' . $this->search . '%')
                        // Search by status
                        ->orWhere('status', 'like', '%' . $this->search . '%')
                        // Search by interest rate
                        ->orWhere('interest_rate', 'like', '%' . $this->search . '%')
                        // Search by borrower name
                        ->orWhereHas('account.user.personalData', function ($subQ) {
                            $subQ->where('first_name', 'like', '%' . $this->search . '%')
                                ->orWhere('last_name', 'like', '%' . $this->search . '%')
                                ->orWhere('mid_name', 'like', '%' . $this->search . '%');
                        })
                        // Search by user number
                        ->orWhereHas('account.user', function ($subQ) {
                            $subQ->where('user_number', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('livewire.content.loans', [
            'loans' => $loans,
            'canEdit' => $canEdit,
        ]);
    }
}
