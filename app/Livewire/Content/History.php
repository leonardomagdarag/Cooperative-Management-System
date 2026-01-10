<?php

namespace App\Livewire\Content;

use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
 
/**
 * History Component
 * 
 * Displays transaction history for the currently authenticated user.
 * Uses the unified Transaction model for all transaction types.
 */
class History extends Component
{
    use WithPagination;

    // Filters
    public $search = '';
    public $selectedCategory = 'all';
    public $statusFilter = 'all'; // all, pending, completed, approved, rejected
    public $dateFilter = 'all'; // all, today, week, month, year
    public $sortDirection = 'desc';

    // Available categories
    public $categories = [
        'all' => 'All Transactions',
        'deposit' => 'Deposits',
        'withdrawal' => 'Withdrawals',
        'loan_disbursement' => 'Loan Disbursements',
        'loan_payment' => 'Loan Payments',
    ];

    public $statusFilters = [
        'all' => 'All Status',
        'pending' => 'Pending',
        'completed' => 'Completed',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    public $dateFilters = [
        'all' => 'All Time',
        'today' => 'Today',
        'week' => 'This Week',
        'month' => 'This Month',
        'year' => 'This Year',
    ];

    protected $paginationTheme = 'tailwind';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingSelectedCategory()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingDateFilter()
    {
        $this->resetPage();
    }

    /**
     * Render user's transaction history using unified Transaction model
     */
    public function render()
    {
        $user = Auth::user();
        $userAccounts = $user->accounts->pluck('id');

        $query = Transaction::with(['account', 'loan', 'recordedBy'])
            ->whereIn('account_id', $userAccounts);

        // Filter by category
        if ($this->selectedCategory !== 'all') {
            $query->where('transaction_type', $this->selectedCategory);
        }

        // Filter by status
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Filter by date
        switch ($this->dateFilter) {
            case 'today':
                $query->whereDate('transaction_date', today());
                break;
            case 'week':
                $query->whereBetween('transaction_date', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('transaction_date', now()->month)
                      ->whereYear('transaction_date', now()->year);
                break;
            case 'year':
                $query->whereYear('transaction_date', now()->year);
                break;
        }

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('transaction_number', 'like', '%' . $this->search . '%')
                  ->orWhere('note', 'like', '%' . $this->search . '%')
                  ->orWhere('amount', 'like', '%' . $this->search . '%');
            });
        }

        // Sort
        $query->orderBy('transaction_date', $this->sortDirection)
              ->orderBy('created_at', $this->sortDirection);

        $transactions = $query->paginate(15);

        // Calculate summary statistics
        $totalDeposits = Transaction::whereIn('account_id', $userAccounts)
            ->where('transaction_type', 'deposit')
            ->sum('amount');
        
        $totalWithdrawals = Transaction::whereIn('account_id', $userAccounts)
            ->where('transaction_type', 'withdrawal')
            ->sum('amount');
        
        $totalLoanPayments = Transaction::whereIn('account_id', $userAccounts)
            ->where('transaction_type', 'loan_payment')
            ->sum('amount');

        return view('livewire.content.history', [
            'transactions' => $transactions,
            'totalDeposits' => $totalDeposits,
            'totalWithdrawals' => $totalWithdrawals,
            'totalLoanPayments' => $totalLoanPayments,
        ]);
    }
}
