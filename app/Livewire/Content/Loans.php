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
     * - account.user.personalData: Borrower information
     * - payments: Complete payment history for the loan
     */
    public function viewDetails($loanId)
    {
        $this->selectedLoan = Loan::with(['account.user.personalData', 'payments'])->find($loanId);
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedLoan = null;
    }

    /**
     * Render loans list
     * 
     * Simpler than Ledger - single model query with standard pagination.
     * Loan officers, treasurers, managers, and admins can view/edit.
     */
    public function render()
    {
        $userRole = Auth::user()->role;
        $canEdit = in_array($userRole, ['admin', 'manager', 'treasurer', 'loan']);

        // Standard Eloquent query with pagination 
        $loans = Loan::with(['account.user.personalData'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    // Search by loan number
                    $q->where('loan_number', 'like', '%'.$this->search.'%')
                        // Search by loan type
                        ->orWhere('loan_type', 'like', '%'.$this->search.'%')
                        // Search by amount
                        ->orWhere('amount', 'like', '%'.$this->search.'%')
                        // Search by status
                        ->orWhere('status', 'like', '%'.$this->search.'%')
                        // Search by interest rate
                        ->orWhere('interest_rate', 'like', '%'.$this->search.'%')
                        // Search by borrower name
                        ->orWhereHas('account.user.personalData', function ($subQ) {
                            $subQ->where('first_name', 'like', '%'.$this->search.'%')
                                ->orWhere('last_name', 'like', '%'.$this->search.'%')
                                ->orWhere('mid_name', 'like', '%'.$this->search.'%');
                        })
                        // Search by user number
                        ->orWhereHas('account.user', function ($subQ) {
                            $subQ->where('user_number', 'like', '%'.$this->search.'%');
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
