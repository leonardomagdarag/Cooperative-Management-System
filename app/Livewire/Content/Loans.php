<?php

namespace App\Livewire\Content;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Loans Component 
 * 
 * Manages loan viewing and tracking. Displays all loans with associated
 * borrower information and payment history. Read-only view with details modal.
 */
class Loans extends Component
{
    use WithPagination, WithFileUploads;

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
    public $loanDocumentUpload = null;

    // Encode loan modal
    public $showCreateModal = false;
    public $memberSearch = '';
    public $selectedMemberId = null;
    public $encodedLoanProduct = '';
    public $encodedLoanType = 'business';
    public $encodedLoanStatus = 'pending';
    public $encodedLoanAmount = '';
    public $encodedInterestRate = '';
    public $encodedLoanTerm = '';
    public $encodedPaymentFrequency = 'monthly';
    public $appliedAt = '';

    public function mount()
    {
        $this->resetCreateForm();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatedMemberSearch($value)
    {
        $search = trim((string) $value);

        if ($search === '') {
            $this->selectedMemberId = null;
            return;
        }

        $selectedMember = $this->selectedMember;

        if ($selectedMember && $search !== $this->formatMemberLabel($selectedMember)) {
            $this->selectedMemberId = null;
        }
    }

    public function updatedEncodedLoanProduct($value)
    {
        $product = $this->findLoanProductOption((string) $value);

        if (! $product) {
            return;
        }

        $this->encodedLoanType = (string) $product['base_type'];
        $this->encodedInterestRate = (string) $product['interest_rate'];
        $this->encodedLoanTerm = (string) $product['max_term'];
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
        $this->selectedLoan = $this->loadLoanDetails((int) $loanId);
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedLoan = null;
        $this->reset('loanDocumentUpload');
        $this->resetValidation('loanDocumentUpload');
    }

    public function uploadLoanDocument()
    {
        abort_unless($this->selectedLoan, 404);

        $loan = Loan::query()->findOrFail($this->selectedLoan['id'] ?? $this->selectedLoan->id);

        Gate::authorize('uploadDocument', $loan);

        $validated = $this->validate([
            'loanDocumentUpload' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:10240'],
        ]);

        $upload = $validated['loanDocumentUpload'];
        $path = $upload->store('loan-documents/' . $loan->id, 'public');

        $loan->documents()->create([
            'original_name' => $upload->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $upload->getMimeType(),
            'file_size' => $upload->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        $this->selectedLoan = $this->loadLoanDetails($loan->id);
        $this->reset('loanDocumentUpload');
        $this->resetValidation('loanDocumentUpload');

        $this->dispatch('success', message: 'Loan document uploaded successfully.');
    }

    public function openCreateModal()
    {
        if (! $this->canEncodeLoans()) {
            $this->dispatch('error', message: 'Only administrators with loan encoding access can encode loans.');
            return;
        }

        $this->resetCreateForm();
        $this->showCreateModal = true;
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->resetCreateForm();
    }

    public function selectMember(int $memberId)
    {
        $member = User::query()
            ->memberUsers()
            ->with('personalData')
            ->find($memberId);

        if (! $member) {
            $this->addError('selectedMemberId', 'Please select a valid member.');
            return;
        }

        $this->selectedMemberId = $member->id;
        $this->memberSearch = $this->formatMemberLabel($member);
        $this->resetValidation('selectedMemberId');
    }

    public function saveEncodedLoan()
    {
        Gate::authorize('encode', Loan::class);

        $validated = $this->validate($this->encodeLoanRules());

        $member = User::query()
            ->memberUsers()
            ->find($validated['selectedMemberId']);

        if (! $member) {
            $this->addError('selectedMemberId', 'Please select a valid member.');
            return;
        }

        $appliedAt = Carbon::parse($validated['appliedAt'])->startOfDay();
        $status = $validated['encodedLoanStatus'];
        $isApprovedStatus = in_array($status, ['approved', 'active', 'paid_off'], true);

        $loan = Loan::create([
            'user_id' => $member->id,
            'loan_type' => $validated['encodedLoanType'],
            'loan_amount' => $validated['encodedLoanAmount'],
            'interest_rate' => $validated['encodedInterestRate'],
            'term_months' => $validated['encodedLoanTerm'],
            'payment_frequency' => $validated['encodedPaymentFrequency'],
            'loan_status' => $status,
            'applied_at' => $appliedAt,
            'approved_at' => $isApprovedStatus ? $appliedAt : null,
            'approved_by' => $isApprovedStatus ? Auth::id() : null,
            'due_date' => $isApprovedStatus ? $appliedAt->copy()->addMonths((int) $validated['encodedLoanTerm'])->toDateString() : null,
        ]);

        $this->dispatch('success', message: 'Loan encoded successfully. Reference: ' . $loan->loan_number);
        $this->closeCreateModal();
        $this->resetPage();
    }

    public function getMemberSearchResultsProperty(): Collection
    {
        $search = trim($this->memberSearch);

        if (! $this->showCreateModal || $search === '') {
            return collect();
        }

        $selectedMember = $this->selectedMember;
        if ($selectedMember && $search === $this->formatMemberLabel($selectedMember)) {
            return collect();
        }

        return User::query()
            ->memberUsers()
            ->with('personalData')
            ->where(function ($query) use ($search) {
                $query->where('user_number', 'like', '%' . $search . '%')
                    ->orWhereHas('personalData', function ($personalDataQuery) use ($search) {
                        $personalDataQuery->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhere('mid_name', 'like', '%' . $search . '%')
                            ->orWhere('personal_data_number', 'like', '%' . $search . '%');
                    });
            })
            ->orderBy('user_number')
            ->limit(8)
            ->get();
    }

    public function getSelectedMemberProperty(): ?User
    {
        if (! $this->selectedMemberId) {
            return null;
        }

        return User::query()
            ->memberUsers()
            ->with('personalData')
            ->find($this->selectedMemberId);
    }

    public function getLoanProductOptionsProperty(): Collection
    {
        return collect($this->availableLoanProducts());
    }

    /**
     * Generate an amortization schedule for a loan.
     *
     * This is used by the loan detail modal to render the payment schedule tab.
     */
    public function generateAmortizationSchedule($loan): array
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

    /**
     * Render loans list
     * 
     * Simpler than Ledger - single model query with standard pagination.
     * Loan officers, treasurers, managers, and admins can view/edit.
     */
    public function render()
    {
        $canViewAllLoans = $this->canViewAllLoans();
        $canEdit = $this->canManageLoanRecords();
        $canEncode = $this->canEncodeLoans();
        $canUploadDocuments = $this->canUploadLoanDocuments();

        // Standard Eloquent query with pagination 
        $loans = Loan::with(['user.personalData'])
            ->when(! $canViewAllLoans, fn ($query) => $query->where('user_id', Auth::id()))
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    // Search by loan number
                    $q->where('loan_number', 'like', '%' . $this->search . '%')
                        // Search by loan type
                        ->orWhere('loan_type', 'like', '%' . $this->search . '%')
                        // Search by amount
                        ->orWhere('loan_amount', 'like', '%' . $this->search . '%')
                        // Search by status
                        ->orWhere('loan_status', 'like', '%' . $this->search . '%')
                        // Search by interest rate
                        ->orWhere('interest_rate', 'like', '%' . $this->search . '%')
                        // Search by borrower name
                        ->orWhereHas('user.personalData', function ($subQ) {
                            $subQ->where('first_name', 'like', '%' . $this->search . '%')
                                ->orWhere('last_name', 'like', '%' . $this->search . '%')
                                ->orWhere('mid_name', 'like', '%' . $this->search . '%');
                        })
                        // Search by user number
                        ->orWhereHas('user', function ($subQ) {
                            $subQ->where('user_number', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('livewire.content.loans', [
            'loans' => $loans,
            'canEdit' => $canEdit,
            'canEncode' => $canEncode,
            'canUploadDocuments' => $canUploadDocuments,
            'loanProductOptions' => $this->loanProductOptions,
        ]);
    }

    protected function encodeLoanRules(): array
    {
        return [
            'selectedMemberId' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    if (! User::query()->memberUsers()->whereKey($value)->exists()) {
                        $fail('Please select a valid member.');
                    }
                },
            ],
            'encodedLoanProduct' => ['required', Rule::in(array_keys($this->availableLoanProducts()))],
            'encodedLoanType' => ['required', Rule::in(['group', 'business', 'special'])],
            'encodedLoanStatus' => ['required', Rule::in(['pending', 'approved', 'active', 'paid_off'])],
            'encodedLoanAmount' => ['required', 'numeric', 'min:1'],
            'encodedInterestRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'encodedLoanTerm' => ['required', 'integer', 'min:1', 'max:360'],
            'encodedPaymentFrequency' => ['required', Rule::in(['weekly', 'bi-weekly', 'monthly', 'quarterly', 'annually'])],
            'appliedAt' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    protected function canEncodeLoans(): bool
    {
        $user = Auth::user();

        return (bool) $user && $user->hasRole('admin') && $user->can('loans.encode');
    }

    protected function canViewAllLoans(): bool
    {
        $user = Auth::user();

        return (bool) $user
            && $this->isOfficerUser($user)
            && $user->can('loans.view');
    }

    protected function canManageLoanRecords(): bool
    {
        $user = Auth::user();

        return (bool) $user
            && $this->isOfficerUser($user)
            && ($user->can('loans.update') || $user->can('loans.approve'));
    }

    protected function canUploadLoanDocuments(): bool
    {
        $user = Auth::user();

        return (bool) $user && $user->hasAnyRole(['admin', 'manager', 'accountant']);
    }

    protected function resetCreateForm(): void
    {
        $this->resetValidation();
        $defaultProduct = $this->defaultLoanProductOption();
        $this->memberSearch = '';
        $this->selectedMemberId = null;
        $this->encodedLoanProduct = $defaultProduct['code'] ?? '';
        $this->encodedLoanType = $defaultProduct['base_type'] ?? 'business';
        $this->encodedLoanStatus = 'pending';
        $this->encodedLoanAmount = '';
        $this->encodedInterestRate = isset($defaultProduct['interest_rate']) ? (string) $defaultProduct['interest_rate'] : '';
        $this->encodedLoanTerm = isset($defaultProduct['max_term']) ? (string) $defaultProduct['max_term'] : '';
        $this->encodedPaymentFrequency = 'monthly';
        $this->appliedAt = now()->toDateString();
    }

    protected function formatMemberLabel(User $member): string
    {
        $name = trim(implode(' ', array_filter([
            $member->personalData?->first_name,
            $member->personalData?->last_name,
        ])));

        return trim(($name !== '' ? $name : 'Member') . ' (' . ($member->user_number ?: 'No ID') . ')');
    }

    protected function availableLoanProducts(): array
    {
        $products = LoanProduct::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($products->isEmpty()) {
            return [
                'business' => [
                    'code' => 'business',
                    'name' => 'Business Loan',
                    'base_type' => 'business',
                    'interest_rate' => 3.0,
                    'max_term' => 12,
                ],
                'group' => [
                    'code' => 'group',
                    'name' => 'Group Loan',
                    'base_type' => 'group',
                    'interest_rate' => 1.5,
                    'max_term' => 6,
                ],
                'special' => [
                    'code' => 'special',
                    'name' => 'Special Loan',
                    'base_type' => 'special',
                    'interest_rate' => 2.0,
                    'max_term' => 12,
                ],
            ];
        }

        return $products
            ->mapWithKeys(fn (LoanProduct $product) => [
                $product->code => [
                    'code' => $product->code,
                    'name' => $product->name,
                    'base_type' => $product->base_type,
                    'interest_rate' => (float) $product->interest_rate,
                    'max_term' => (int) $product->max_term,
                ],
            ])
            ->toArray();
    }

    protected function loadLoanDetails(int $loanId): ?Loan
    {
        $loan = Loan::with([
            'user.personalData',
            'approvedBy',
            'completedPayments',
            'groupMembers.personalData',
            'documents.uploadedBy.personalData',
        ])->findOrFail($loanId);

        Gate::authorize('view', $loan);

        return $loan;
    }

    protected function isOfficerUser(User $user): bool
    {
        return (bool) (($user->is_officer ?? false)
            || $user->hasRole('admin')
            || $user->hasAnyRole(PermissionCatalog::officerRoles()));
    }

    protected function defaultLoanProductOption(): ?array
    {
        $products = $this->availableLoanProducts();

        if ($products === []) {
            return null;
        }

        return reset($products) ?: null;
    }

    protected function findLoanProductOption(string $code): ?array
    {
        $products = $this->availableLoanProducts();

        return $products[$code] ?? null;
    }
}
