<?php

namespace App\Livewire\Content;

use App\Models\LoanProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class LoanProducts extends Component
{
    public array $loanProducts = [];

    public ?int $editingLoanProductId = null;

    public string $code = '';
    public string $name = '';
    public string $description = '';
    public string $baseType = 'business';
    public string $icon = 'wallet';
    public string $interestRate = '0';
    public string $maxAmount = '0';
    public int $maxTerm = 1;
    public bool $isActive = true;
    public bool $requiresNoExistingLoan = false;
    public string $minimumShareCapital = '0';
    public int $minimumMembershipYears = 0;
    public int $requiredCoMakers = 0;
    public bool $requiresInterview = false;
    public bool $requiresSpecialEligibility = false;
    public int $sortOrder = 0;

    public bool $showModal = false;

    public function mount(): void
    {
        $this->loadLoanProducts();
    }

    public function openAddModal(): void
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->showModal = false;
    }

    public function toggleLoanProductStatus(int $id): void
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $product = LoanProduct::query()->find($id);
        if (! $product) {
            $this->dispatch('toast', ['message' => 'Loan product not found.', 'type' => 'error']);

            return;
        }

        $product->update([
            'is_active' => ! $product->is_active,
        ]);

        if ($this->editingLoanProductId === $product->id) {
            $this->isActive = (bool) $product->fresh()->is_active;
        }

        $this->loadLoanProducts();
        $this->dispatch('toast', ['message' => $product->name . ($product->is_active ? ' enabled.' : ' disabled.'), 'type' => 'success']);
    }

    public function saveLoanProduct(): void
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $validated = $this->validate([
            'code' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'alpha_dash',
                Rule::unique('loan_products', 'code')->ignore($this->editingLoanProductId),
            ],
            'name' => 'required|string|min:3|max:120',
            'description' => 'nullable|string|max:500',
            'baseType' => 'required|in:group,business,special',
            'icon' => 'nullable|string|max:50',
            'interestRate' => 'required|numeric|min:0|max:100',
            'maxAmount' => 'required|numeric|min:500|max:5000000',
            'maxTerm' => 'required|integer|min:1|max:120',
            'minimumShareCapital' => 'nullable|numeric|min:0|max:5000000',
            'minimumMembershipYears' => 'required|integer|min:0|max:50',
            'requiredCoMakers' => 'required|integer|min:0|max:20',
            'sortOrder' => 'required|integer|min:0|max:9999',
        ]);

        $payload = [
            'code' => strtolower(trim($validated['code'])),
            'name' => trim($validated['name']),
            'description' => trim((string) $validated['description']),
            'base_type' => $validated['baseType'],
            'icon' => trim($this->icon) !== '' ? trim($this->icon) : 'wallet',
            'interest_rate' => (float) $validated['interestRate'],
            'max_amount' => (float) $validated['maxAmount'],
            'max_term' => (int) $validated['maxTerm'],
            'is_active' => (bool) $this->isActive,
            'requires_no_existing_loan' => (bool) $this->requiresNoExistingLoan,
            'minimum_share_capital' => (float) ($validated['minimumShareCapital'] ?? 0),
            'minimum_membership_years' => (int) $validated['minimumMembershipYears'],
            'required_co_makers' => (int) $validated['requiredCoMakers'],
            'requires_interview' => (bool) $this->requiresInterview,
            'requires_special_eligibility' => (bool) $this->requiresSpecialEligibility,
            'sort_order' => (int) $validated['sortOrder'],
        ];

        if ($this->editingLoanProductId) {
            LoanProduct::query()->whereKey($this->editingLoanProductId)->update($payload);
            $this->dispatch('toast', ['message' => 'Loan product updated.', 'type' => 'success']);
        } else {
            LoanProduct::query()->create($payload);
            $this->dispatch('toast', ['message' => 'Loan product created.', 'type' => 'success']);
        }

        $this->resetForm();
        $this->showModal = false;
        $this->loadLoanProducts();
    }

    public function editLoanProduct(int $id): void
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $product = LoanProduct::query()->find($id);
        if (! $product) {
            return;
        }

        $this->resetErrorBag();
        $this->editingLoanProductId = $product->id;
        $this->code = (string) $product->code;
        $this->name = (string) $product->name;
        $this->description = (string) ($product->description ?? '');
        $this->baseType = (string) $product->base_type;
        $this->icon = (string) ($product->icon ?: 'wallet');
        $this->interestRate = (string) $product->interest_rate;
        $this->maxAmount = (string) $product->max_amount;
        $this->maxTerm = (int) $product->max_term;
        $this->isActive = (bool) $product->is_active;
        $this->requiresNoExistingLoan = (bool) $product->requires_no_existing_loan;
        $this->minimumShareCapital = (string) ($product->minimum_share_capital ?? '0');
        $this->minimumMembershipYears = (int) $product->minimum_membership_years;
        $this->requiredCoMakers = (int) $product->required_co_makers;
        $this->requiresInterview = (bool) $product->requires_interview;
        $this->requiresSpecialEligibility = (bool) $product->requires_special_eligibility;
        $this->sortOrder = (int) $product->sort_order;

        $this->showModal = true;
    }

    public function deleteLoanProduct(int $id): void
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        LoanProduct::query()->whereKey($id)->delete();

        if ($this->editingLoanProductId === $id) {
            $this->resetForm();
        }

        $this->loadLoanProducts();
        $this->dispatch('toast', ['message' => 'Loan product deleted.', 'type' => 'success']);
    }

    public function resetForm(): void
    {
        $this->resetErrorBag();
        $this->editingLoanProductId = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->baseType = 'business';
        $this->icon = 'wallet';
        $this->interestRate = '0';
        $this->maxAmount = '0';
        $this->maxTerm = 1;
        $this->isActive = true;
        $this->requiresNoExistingLoan = false;
        $this->minimumShareCapital = '0';
        $this->minimumMembershipYears = 0;
        $this->requiredCoMakers = 0;
        $this->requiresInterview = false;
        $this->requiresSpecialEligibility = false;
        $this->sortOrder = 0;
    }

    public function goToSettings(): void
    {
        $this->dispatch('sectionChanged', 'settings');
    }

    public function render()
    {
        return view('livewire.content.loan-products', [
            'canManage' => (bool) Auth::user()?->can('settings.update'),
            'loanProducts' => $this->loanProducts,
        ]);
    }

    private function loadLoanProducts(): void
    {
        $this->loanProducts = LoanProduct::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->toArray();
    }
}
