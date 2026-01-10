<?php

namespace App\Livewire\Content;

use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Ledger extends Component
{
    use WithPagination;

    // Search and filters
    public $search = '';

    public $selectedCategory = 'all';  // Filter by transaction type

    public $sortField = 'created_at';

    public $sortDirection = 'desc';

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
        'interest' => 'Interest',
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
        $rules = [
            'editForm.transaction_type' => 'required|in:deposit,withdrawal,loan_payment,loan_disbursement,interest,fee',
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
        } catch (\Exception $e) {
            session()->flash('error', 'An error occurred: '.$e->getMessage());
        }
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editForm = [];
    }

    /**
     * Render ledger with unified transaction list
     *
     * Uses the unified Transaction model which stores all transaction types
     * in a single table with transaction_type enum field.
     */
    public function render()
    {
        logger('trying to render...');
        $userRole = Auth::user()->role;
        $canEdit = in_array($userRole, ['admin', 'manager', 'treasurer']);

        $query = Transaction::with(['account.user.personalData', 'loan.user.personalData', 'recordedBy']);

        // Filter by category/transaction type
        if ($this->selectedCategory !== 'all') {
            $query->where('transaction_type', $this->selectedCategory);
        }

        // Enhanced search across all displayed fields
        if ($this->search) {
            $query->where(function ($q) {
                // Search by transaction number
                $q->where('transaction_number', 'like', '%'.$this->search.'%')
                    // Search by amount
                    ->orWhere('amount', 'like', '%'.$this->search.'%')
                    // Search by status
                    ->orWhere('status', 'like', '%'.$this->search.'%')
                    // Search by transaction type
                    ->orWhere('transaction_type', 'like', '%'.$this->search.'%')
                    // Search by note
                    ->orWhere('note', 'like', '%'.$this->search.'%')
                    // Search by account holder's name
                    ->orWhereHas('account.user.personalData', function ($subQ) {
                        $subQ->where('first_name', 'like', '%'.$this->search.'%')
                            ->orWhere('last_name', 'like', '%'.$this->search.'%');
                    })
                    // Search by account user number
                    ->orWhereHas('account.user', function ($subQ) {
                        $subQ->where('user_number', 'like', '%'.$this->search.'%');
                    })
                    // Search by loan holder's name
                    ->orWhereHas('loan.user.personalData', function ($subQ) {
                        $subQ->where('first_name', 'like', '%'.$this->search.'%')
                            ->orWhere('last_name', 'like', '%'.$this->search.'%');
                    })
                    // Search by loan number
                    ->orWhereHas('loan', function ($subQ) {
                        $subQ->where('loan_number', 'like', '%'.$this->search.'%');
                    });
            });
        }

        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);

        // Paginate results
        $perPage = 10;
        $transactions = $query->paginate($perPage);

        logger('Successfully rendered with '.$transactions->total().' transactions');

        return view('livewire.content.ledger', [
            'transactions' => $transactions,
            'canEdit' => $canEdit,
        ]);
    }

    /**
     * Delete a transaction
     */
    public function deleteTransaction($transactionId)
    {
        try {
            $transaction = Transaction::find($transactionId);
            if ($transaction) {
                $transaction->delete();
                session()->flash('message', 'Transaction deleted successfully.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'An error occurred: '.$e->getMessage());
        }
    }
}
