<div class="space-y-6">
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Loan Products</h2>
                <p class="mt-1 text-sm text-gray-600">Configure loan caps and eligibility rules. Toggle loan availability per product.</p>
            </div>
            <button type="button" wire:click="goToSettings"
                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-100">
                Back to Settings
            </button>
        </div>
    </div>

    @if (!$isAdmin)
        <div class="rounded-lg border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800" role="alert">
            <span class="font-medium">Heads up:</span> Only admins can manage loan products. You currently have view-only access.
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Configured Loan Products</h3>
                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">{{ count($loanProducts) }} total</span>
            </div>

            @if (count($loanProducts) > 0)
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    @foreach ($loanProducts as $product)
                        <div wire:key="loan-product-card-{{ $product['id'] }}"
                            class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-cyan-300 hover:shadow-md">
                            <div class="mb-4 flex items-start justify-between gap-3">
                                <div>
                                    <div class="mb-1 flex items-center gap-2">
                                        <span class="rounded bg-cyan-50 px-2.5 py-0.5 text-xs font-medium text-cyan-700">{{ strtoupper($product['code']) }}</span>
                                        @if ($product['is_active'])
                                            <span class="rounded bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Active</span>
                                        @else
                                            <span class="rounded bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">Inactive</span>
                                        @endif
                                    </div>
                                    <h4 class="text-base font-semibold text-gray-900">{{ $product['name'] }}</h4>
                                    <p class="text-xs capitalize text-gray-500">{{ $product['base_type'] }} loan</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-gray-500">Max Amount</div>
                                    <div class="text-lg font-bold text-gray-900">P{{ number_format((float) $product['max_amount'], 2) }}</div>
                                </div>
                            </div>

                            <dl class="mb-4 grid grid-cols-2 gap-3 rounded-lg bg-gray-50 p-3 text-sm">
                                <div>
                                    <dt class="text-xs text-gray-500">Interest</dt>
                                    <dd class="font-semibold text-gray-900">{{ number_format((float) $product['interest_rate'], 2) }}%</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500">Max Term</dt>
                                    <dd class="font-semibold text-gray-900">{{ (int) $product['max_term'] }} months</dd>
                                </div>
                            </dl>

                            <div class="mb-4 flex flex-wrap gap-2 text-xs">
                                @if ($product['requires_no_existing_loan'])
                                    <span class="rounded bg-red-50 px-2.5 py-1 font-medium text-red-700">No active loan</span>
                                @endif
                                @if ((float) $product['minimum_share_capital'] > 0)
                                    <span class="rounded bg-blue-50 px-2.5 py-1 font-medium text-blue-700">Min share: P{{ number_format((float) $product['minimum_share_capital'], 2) }}</span>
                                @endif
                                @if ((int) $product['minimum_membership_years'] > 0)
                                    <span class="rounded bg-indigo-50 px-2.5 py-1 font-medium text-indigo-700">{{ (int) $product['minimum_membership_years'] }}+ years member</span>
                                @endif
                                @if ((int) $product['required_co_makers'] > 0)
                                    <span class="rounded bg-emerald-50 px-2.5 py-1 font-medium text-emerald-700">{{ (int) $product['required_co_makers'] }} co-maker(s)</span>
                                @endif
                                @if ($product['requires_interview'])
                                    <span class="rounded bg-amber-50 px-2.5 py-1 font-medium text-amber-700">Interview required</span>
                                @endif
                                @if ($product['requires_special_eligibility'])
                                    <span class="rounded bg-orange-50 px-2.5 py-1 font-medium text-orange-700">Special eligibility</span>
                                @endif
                            </div>

                            @if (!empty($product['description']))
                                <p class="mb-4 text-sm text-gray-600">{{ $product['description'] }}</p>
                            @endif

                            @if ($isAdmin)
                                <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-3">
                                    <button type="button" wire:click="editLoanProduct({{ $product['id'] }})"
                                        wire:loading.attr="disabled" wire:target="editLoanProduct({{ $product['id'] }})"
                                        class="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-1.5 text-xs font-medium text-cyan-700 hover:bg-cyan-100">
                                        Edit
                                    </button>
                                    <button type="button" wire:click="toggleLoanProductStatus({{ $product['id'] }})"
                                        wire:loading.attr="disabled" wire:target="toggleLoanProductStatus({{ $product['id'] }})"
                                        class="rounded-lg border px-3 py-1.5 text-xs font-medium {{ $product['is_active'] ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100' : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100' }}">
                                        {{ $product['is_active'] ? 'Disable' : 'Enable' }}
                                    </button>
                                    <button type="button" wire:click="deleteLoanProduct({{ $product['id'] }})" wire:confirm="Delete this loan product?"
                                        class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">
                                        Delete
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center">
                    <p class="text-sm text-gray-500">No loan products configured yet.</p>
                </div>
            @endif
        </div>

        <div @class([
            'rounded-xl bg-white p-6 shadow-sm transition-all',
            'border-2 border-cyan-400 ring-4 ring-cyan-100' => $editingLoanProductId,
            'border border-gray-200' => ! $editingLoanProductId,
        ])>
            <h3 class="mb-1 text-lg font-semibold text-gray-900">{{ $editingLoanProductId ? 'Edit Loan Product' : 'Add Loan Product' }}</h3>
            <p class="mb-5 text-xs text-gray-500">Use this form to define product limits and member eligibility requirements.</p>

            @if ($editingLoanProductId)
                <div class="mb-4 rounded-lg border border-cyan-200 bg-cyan-50 p-3 text-xs text-cyan-800">
                    Edit mode is active. Update fields, then click Update Product.
                </div>
            @endif

            @if ($isAdmin)
                <form wire:key="loan-product-form-{{ $editingLoanProductId ?? 'new' }}" wire:submit="saveLoanProduct" class="space-y-4">
                    <div>
                        <label for="loan-code" class="mb-2 block text-sm font-medium text-gray-900">Code</label>
                        <input id="loan-code" wire:model="code" type="text" placeholder="example: agri_boost"
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                        @error('code')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="loan-name" class="mb-2 block text-sm font-medium text-gray-900">Display Name</label>
                        <input id="loan-name" wire:model="name" type="text" placeholder="Agriculture Boost Loan"
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="loan-base-type" class="mb-2 block text-sm font-medium text-gray-900">Base Type</label>
                        <select id="loan-base-type" wire:model="baseType"
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                            <option value="group">group</option>
                            <option value="business">business</option>
                            <option value="special">special</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="loan-interest-rate" class="mb-2 block text-sm font-medium text-gray-900">Interest Rate (%)</label>
                            <input id="loan-interest-rate" wire:model="interestRate" type="number" step="0.01" min="0"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                        </div>
                        <div>
                            <label for="loan-max-amount" class="mb-2 block text-sm font-medium text-gray-900">Max Amount</label>
                            <input id="loan-max-amount" wire:model="maxAmount" type="number" step="0.01" min="0"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="loan-max-term" class="mb-2 block text-sm font-medium text-gray-900">Max Term (months)</label>
                            <input id="loan-max-term" wire:model="maxTerm" type="number" min="1"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                        </div>
                        <div>
                            <label for="loan-sort-order" class="mb-2 block text-sm font-medium text-gray-900">Sort Order</label>
                            <input id="loan-sort-order" wire:model="sortOrder" type="number" min="0"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="loan-min-share" class="mb-2 block text-sm font-medium text-gray-900">Minimum Share Capital</label>
                            <input id="loan-min-share" wire:model="minimumShareCapital" type="number" step="0.01" min="0"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                        </div>
                        <div>
                            <label for="loan-min-years" class="mb-2 block text-sm font-medium text-gray-900">Minimum Membership Years</label>
                            <input id="loan-min-years" wire:model="minimumMembershipYears" type="number" min="0"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                        </div>
                    </div>

                    <div>
                        <label for="loan-co-makers" class="mb-2 block text-sm font-medium text-gray-900">Required Co-Makers</label>
                        <input id="loan-co-makers" wire:model="requiredCoMakers" type="number" min="0"
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                    </div>

                    <div>
                        <label for="loan-icon" class="mb-2 block text-sm font-medium text-gray-900">Icon (Font Awesome name)</label>
                        <input id="loan-icon" wire:model="icon" type="text" placeholder="seedling, briefcase, users"
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                    </div>

                    <div>
                        <label for="loan-description" class="mb-2 block text-sm font-medium text-gray-900">Description</label>
                        <textarea id="loan-description" wire:model="description" rows="3"
                            class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500"></textarea>
                    </div>

                    <fieldset class="rounded-lg border border-gray-200 p-3">
                        <legend class="px-1 text-xs font-medium text-gray-500">Special Requirements</legend>
                        <div class="mt-2 space-y-2 text-sm text-gray-700">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="isActive" class="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500">
                                Active product
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="requiresNoExistingLoan" class="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500">
                                Must not have existing active loan
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="requiresInterview" class="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500">
                                Require interview schedule
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="requiresSpecialEligibility" class="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500">
                                Require special-eligibility flag
                            </label>
                        </div>
                    </fieldset>

                    <div class="flex gap-2 pt-2">
                        <button type="submit"
                            class="inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 focus:outline-none focus:ring-4 focus:ring-cyan-300">
                            {{ $editingLoanProductId ? 'Update Product' : 'Create Product' }}
                        </button>
                        <button type="button" wire:click="resetForm"
                            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-100">
                            Reset
                        </button>
                    </div>
                </form>
            @else
                <p class="text-sm text-gray-600">Admin access is required to add or edit loan products.</p>
            @endif
        </div>
    </div>
</div>
