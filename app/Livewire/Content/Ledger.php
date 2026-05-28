<?php

namespace App\Livewire\Content;

use App\Models\Loan;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Ledger extends Component
{
    use WithPagination;

    private function ensureCanManageTransactions(): void
    {
        abort_unless(Auth::user()?->can('transactions.update'), 403);
    }

    // Search and filters
    public $search = '';

    public $selectedCategory = 'all';  // Filter by transaction type

    public $sortField = 'created_at';

    public $sortDirection = 'desc';

    // Summary range hook (future Reports/Summaries reuse)
    // For now UI defaults to today; other ranges can be wired later.
    public $range = 'today';

    // Row selection
    public $selectedRows = [];

    public $selectAll = false;

    // View details modal
    public $showModal = false;

    public $selectedTransaction = null;  // Stores ['id', 'type', 'data']

    // Add/Edit transaction modal
    public $showEditModal = false;

    public $editForm = [];  // Stores transaction form data

    // Available transaction categories for tab navigation
    public $categories = [
        'all' => 'All Transactions',
        'deposit' => 'Deposits',
        'withdrawal' => 'Withdrawals',
        'loan_payment' => 'Loan Payments',
        'loan_disbursement' => 'Loan Disbursements',
        'fee' => 'Fees',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingSelectedCategory()
    {
        $this->resetPage();
        $this->selectedRows = [];
        $this->selectAll = false;
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
     * View transaction details modal
     */
    public function viewDetails($transactionId)
    {
        $transaction = Transaction::with(['account.user.personalData', 'loan', 'recordedBy'])->find($transactionId);

        if ($transaction) {
            $this->selectedTransaction = [
                'id' => $transactionId,
                'type' => $transaction->transaction_type,
                'data' => $transaction,
            ];
            $this->showModal = true;
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedTransaction = null;
    }

    public function addTransaction()
    {
        $this->ensureCanManageTransactions();

        $this->editForm = [
            'id' => null,
            'transaction_type' => 'deposit',
            'account_id' => '',
            'loan_id' => '',
            'amount' => '',
            'note' => '',
        ];
        $this->showEditModal = true;
    }

    /**
     * Load transaction into edit form
     */
    public function editTransaction($transactionId)
    {
        $this->ensureCanManageTransactions();

        $transaction = Transaction::find($transactionId);

        if ($transaction) {
            $this->editForm = [
                'id' => $transaction->id,
                'transaction_type' => $transaction->transaction_type,
                'account_id' => $transaction->account_id,
                'loan_id' => $transaction->loan_id,
                'amount' => $transaction->amount,
                'note' => $transaction->note ?? '',
            ];
            $this->showModal = false;
            $this->showEditModal = true;
        }
    }

    /**
     * Save transaction (create or update)
     */
    public function saveTransaction()
    {
        $this->ensureCanManageTransactions();

        $rules = [
            'editForm.transaction_type' => 'required|in:deposit,withdrawal,loan_payment,loan_disbursement,fee',
            'editForm.amount' => 'required|numeric|min:0.01',
            'editForm.note' => 'nullable|string|max:500',
        ];

        // account_id required for deposits/withdrawals, loan_id required for loan-related transactions
        if (in_array($this->editForm['transaction_type'], ['deposit', 'withdrawal'])) {
            $rules['editForm.account_id'] = 'required|exists:accounts,id';
        } elseif (in_array($this->editForm['transaction_type'], ['loan_payment', 'loan_disbursement'])) {
            $rules['editForm.loan_id'] = 'required|exists:loans,id';
        }

        $this->validate($rules);

        try {
            $data = [
                'transaction_type' => $this->editForm['transaction_type'],
                'amount' => $this->editForm['amount'],
                'note' => $this->editForm['note'] ?? null,
                'account_id' => $this->editForm['account_id'] ?: null,
                'loan_id' => $this->editForm['loan_id'] ?: null,
                'transaction_date' => now(),
                'recorded_by' => Auth::id(),
            ];

            if ($this->editForm['id']) {
                // UPDATE MODE
                $transaction = Transaction::find($this->editForm['id']);
                if ($transaction) {
                    $transaction->update($data);
                    session()->flash('message', 'Transaction updated successfully.');
                }
            } else {
                // CREATE MODE
                Transaction::create($data);
                session()->flash('message', 'Transaction created successfully.');
            }

            $this->closeEditModal();
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'The action could not be completed. Please try again.');
        }
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editForm = [];
    }

    /**
     * Validate a specific field on blur
     */
    public function validateField($field)
    {
        // Remove 'editForm.' prefix if present for validation
        $fieldName = str_replace('editForm.', '', $field);
        $validationField = 'editForm.' . $fieldName;

        $rules = [];

        // Define validation rules based on field
        switch ($fieldName) {
            case 'transaction_type':
                $rules[$validationField] = 'required|in:deposit,withdrawal,loan_payment,loan_disbursement,fee';
                break;
            case 'amount':
                $rules[$validationField] = 'required|numeric|min:0.01';
                break;
            case 'account_id':
                $rules[$validationField] = 'required|exists:accounts,id';
                break;
            case 'loan_id':
                $rules[$validationField] = 'required|exists:loans,id';
                break;
            case 'note':
                $rules[$validationField] = 'nullable|string|max:500';
                break;
        }

        if (!empty($rules)) {
            $this->validate($rules);
        }
    }

    /**
     * Render ledger with unified transaction list
     *
     * Uses the unified Transaction model which stores all transaction types
     * in a single table with transaction_type enum field.
     */
    public function render()
    {
        $canEdit = (bool) Auth::user()?->can('transactions.update');

        $query = Transaction::with(['account.user.personalData', 'loan.user.personalData', 'recordedBy']);

        // Filter by category/transaction type
        if ($this->selectedCategory !== 'all') {
            $query->where('transaction_type', $this->selectedCategory);
        }

        // Enhanced search across all displayed fields
        if ($this->search) {
            $query->where(function ($q) {
                // Search by transaction number
                $q->where('transaction_number', 'like', '%' . $this->search . '%')
                    // Search by amount
                    ->orWhere('amount', 'like', '%' . $this->search . '%')
                    // Search by status
                    ->orWhere('status', 'like', '%' . $this->search . '%')
                    // Search by transaction type
                    ->orWhere('transaction_type', 'like', '%' . $this->search . '%')
                    // Search by note
                    ->orWhere('note', 'like', '%' . $this->search . '%')
                    // Search by account holder's name
                    ->orWhereHas('account.user.personalData', function ($subQ) {
                        $subQ->where('first_name', 'like', '%' . $this->search . '%')
                            ->orWhere('last_name', 'like', '%' . $this->search . '%');
                    })
                    // Search by account user number
                    ->orWhereHas('account.user', function ($subQ) {
                        $subQ->where('user_number', 'like', '%' . $this->search . '%');
                    })
                    // Search by loan holder's name
                    ->orWhereHas('loan.user.personalData', function ($subQ) {
                        $subQ->where('first_name', 'like', '%' . $this->search . '%')
                            ->orWhere('last_name', 'like', '%' . $this->search . '%');
                    })
                    // Search by loan number
                    ->orWhereHas('loan', function ($subQ) {
                        $subQ->where('loan_number', 'like', '%' . $this->search . '%');
                    });
            });
        }

        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);

        // Paginate results
        $perPage = 10;
        $transactions = $query->paginate($perPage);

        $dailySummary = $this->dailySummary;

        return view('livewire.content.ledger', [
            'transactions' => $transactions,
            'canEdit' => $canEdit,
            'dailySummary' => $dailySummary,
        ]);
    }

    /**
     * Total transactions
     */
    public function getDailySummaryProperty(): array
    {
        $range = $this->range ?: 'today';

        $transactionCounts = $this->applyRangeToTransactionsQuery(
            Transaction::query()->where('status', 'completed'),
            $range
        )
            ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_payment'])
            ->selectRaw('transaction_type, COUNT(*) as count')
            ->groupBy('transaction_type')
            ->pluck('count', 'transaction_type');

        $loanCount = $this->applyRangeToLoansQuery(Loan::query(), $range)->count();

        return [
            'range' => $range,
            'range_label' => $this->getRangeLabel($range),
            'deposit_count' => (int) ($transactionCounts['deposit'] ?? 0),
            'withdrawal_count' => (int) ($transactionCounts['withdrawal'] ?? 0),
            'loan_payment_count' => (int) ($transactionCounts['loan_payment'] ?? 0),
            'loan_count' => (int) $loanCount,
        ];
    }

    private function applyRangeToTransactionsQuery($query, string $range)
    {
        // Prefer transaction_date; include legacy rows with null transaction_date using created_at.
        return match ($range) {
            'today' => $query->where(function ($q) {
                $q->whereDate('transaction_date', Carbon::today())
                    ->orWhere(function ($q2) {
                        $q2->whereNull('transaction_date')
                            ->whereDate('created_at', Carbon::today());
                    });
            }),
            'week' => $query->where(function ($q) {
                $start = Carbon::now()->startOfWeek();
                $end = Carbon::now()->endOfWeek();

                $q->whereBetween('transaction_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNull('transaction_date')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            }),
            'month' => $query->where(function ($q) {
                $start = Carbon::now()->startOfMonth();
                $end = Carbon::now()->endOfMonth();

                $q->whereBetween('transaction_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNull('transaction_date')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            }),
            default => $query,
        };
    }

    private function applyRangeToLoansQuery($query, string $range)
    {
        // Loans have applied_at; use it when present, otherwise fall back to created_at.
        return match ($range) {
            'today' => $query->where(function ($q) {
                $q->whereDate('applied_at', Carbon::today())
                    ->orWhere(function ($q2) {
                        $q2->whereNull('applied_at')
                            ->whereDate('created_at', Carbon::today());
                    });
            }),
            'week' => $query->where(function ($q) {
                $start = Carbon::now()->startOfWeek();
                $end = Carbon::now()->endOfWeek();

                $q->whereBetween('applied_at', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNull('applied_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            }),
            'month' => $query->where(function ($q) {
                $start = Carbon::now()->startOfMonth();
                $end = Carbon::now()->endOfMonth();

                $q->whereBetween('applied_at', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNull('applied_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            }),
            default => $query,
        };
    }

    private function getRangeLabel(string $range): string
    {
        return match ($range) {
            'today' => 'Today',
            'week' => 'This Week',
            'month' => 'This Month',
            default => ucfirst(str_replace('_', ' ', $range)),
        };
    }

    /**
     * Delete a transaction
     */
    public function deleteTransaction($transactionId)
    {
        $this->ensureCanManageTransactions();

        try {
            $transaction = Transaction::find($transactionId);
            if ($transaction) {
                $transaction->delete();
                session()->flash('message', 'Transaction deleted successfully.');
            }
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'The action could not be completed. Please try again.');
        }
    }
}
