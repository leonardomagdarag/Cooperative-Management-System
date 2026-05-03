<div class="space-y-6">

    {{-- ── Page header ── --}}
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Loan Products</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Configure loan caps and eligibility rules. Toggle availability per product.
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @if ($isAdmin)
                    <button type="button" wire:click="openAddModal"
                        class="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 focus:outline-none focus:ring-4 focus:ring-cyan-300">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                clip-rule="evenodd" />
                        </svg>
                        Add Loan Product
                    </button>
                @endif
                <button type="button" wire:click="goToSettings"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-100">
                    Back to Settings
                </button>
            </div>
        </div>
    </div>
    {{-- ── Non-admin notice ── --}}
    @if (!$isAdmin)
        <div class="rounded-lg border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800" role="alert">
            <span class="font-medium">Heads up:</span> Only admins can manage loan products. You currently have
            view-only access.
        </div>
    @endif

    {{-- ── Quick-stats bar ── --}}
    @php
        $totalActive   = collect($loanProducts)->where('is_active', true)->count();
        $totalInactive = count($loanProducts) - $totalActive;
        $typeCount     = collect($loanProducts)->pluck('base_type')->unique()->count();
    @endphp
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">Total</p>
            <p class="text-2xl font-bold text-gray-900">{{ count($loanProducts) }}</p>
        </div>
        <div class="rounded-xl border border-green-200 bg-green-50 p-4 shadow-sm">
            <p class="text-xs text-green-600">Active</p>
            <p class="text-2xl font-bold text-green-700">{{ $totalActive }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 shadow-sm">
            <p class="text-xs text-gray-500">Inactive</p>
            <p class="text-2xl font-bold text-gray-600">{{ $totalInactive }}</p>
        </div>
        <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm">
            <p class="text-xs text-cyan-600">Loan Types</p>
            <p class="text-2xl font-bold text-cyan-700">{{ $typeCount }}</p>
        </div>
    </div>

    {{-- ── Expandable cards list ── --}}
    <div class="space-y-3">
        @forelse ($loanProducts as $product)
            <div wire:key="loan-product-card-{{ $product['id'] }}"
                x-data="{ open: false }"
                class="rounded-xl border border-gray-200 bg-white shadow-sm transition-all duration-200"
                :class="{ 'border-cyan-300 ring-1 ring-cyan-200 shadow-md': open }">

                {{-- ── Summary row (always visible) ── --}}
                <button type="button"
                    class="flex w-full cursor-pointer items-center gap-4 px-5 py-4 text-left"
                    @click="open = !open"
                    :aria-expanded="open">

                    {{-- Icon bubble --}}
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan-50 text-cyan-600">
                        <i class="fa-solid fa-{{ $product['icon'] ?? 'wallet' }} text-base"></i>
                    </div>

                    {{-- Name + badges --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-sm font-semibold text-gray-900">{{ $product['name'] }}</span>
                            <span class="rounded bg-cyan-50 px-2 py-0.5 text-xs font-medium text-cyan-700">
                                {{ strtoupper($product['code']) }}
                            </span>
                            <span class="rounded px-2 py-0.5 text-xs font-medium capitalize
                                {{ $product['base_type'] === 'group'   ? 'bg-purple-50 text-purple-700' :
                                   ($product['base_type'] === 'special' ? 'bg-orange-50 text-orange-700' :
                                                                          'bg-blue-50 text-blue-700') }}">
                                {{ $product['base_type'] }}
                            </span>
                            @if ($product['is_active'])
                                <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Active</span>
                            @else
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                            @endif
                        </div>
                        @if (!empty($product['description']))
                            <p class="mt-0.5 truncate text-xs text-gray-400">{{ $product['description'] }}</p>
                        @endif
                    </div>

                    {{-- Key metrics (hidden on xs) --}}
                    <div class="hidden shrink-0 items-center gap-6 sm:flex">
                        <div class="text-right">
                            <p class="text-xs text-gray-400">Max Amount</p>
                            <p class="text-sm font-bold text-gray-900">₱{{ number_format((float) $product['max_amount'], 2) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-400">Interest</p>
                            <p class="text-sm font-bold text-gray-900">{{ number_format((float) $product['interest_rate'], 2) }}%</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-400">Max Term</p>
                            <p class="text-sm font-bold text-gray-900">{{ (int) $product['max_term'] }} mo</p>
                        </div>
                    </div>

                    {{-- Chevron --}}
                    <svg class="h-5 w-5 shrink-0 text-gray-400 transition-transform duration-200"
                        :class="{ 'rotate-180': open }"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                {{-- ── Expanded details ── --}}
                <div x-show="open" x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-1"
                    class="border-t border-gray-100 px-5 pb-5 pt-4">

                    {{-- Mobile metrics --}}
                    <div class="mb-4 grid grid-cols-3 gap-2 sm:hidden">
                        <div class="rounded-lg bg-gray-50 p-3 text-center">
                            <p class="text-xs text-gray-400">Max Amount</p>
                            <p class="text-sm font-bold text-gray-900">₱{{ number_format((float) $product['max_amount'], 2) }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 text-center">
                            <p class="text-xs text-gray-400">Interest</p>
                            <p class="text-sm font-bold text-gray-900">{{ number_format((float) $product['interest_rate'], 2) }}%</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 text-center">
                            <p class="text-xs text-gray-400">Max Term</p>
                            <p class="text-sm font-bold text-gray-900">{{ (int) $product['max_term'] }} mo</p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex-1">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                Eligibility Requirements
                            </p>
                            <div class="flex flex-wrap gap-2">
                                @if ($product['requires_no_existing_loan'])
                                    <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                                        <i class="fa-solid fa-ban text-xs"></i> No active loan
                                    </span>
                                @endif
                                @if ((float) $product['minimum_share_capital'] > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                                        <i class="fa-solid fa-coins text-xs"></i>
                                        Min share: ₱{{ number_format((float) $product['minimum_share_capital'], 2) }}
                                    </span>
                                @endif
                                @if ((int) $product['minimum_membership_years'] > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">
                                        <i class="fa-solid fa-calendar-days text-xs"></i>
                                        {{ (int) $product['minimum_membership_years'] }}+ yrs member
                                    </span>
                                @endif
                                @if ((int) $product['required_co_makers'] > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                                        <i class="fa-solid fa-users text-xs"></i>
                                        {{ (int) $product['required_co_makers'] }} co-maker(s)
                                    </span>
                                @endif
                                @if ($product['requires_interview'])
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                                        <i class="fa-solid fa-comments text-xs"></i> Interview required
                                    </span>
                                @endif
                                @if ($product['requires_special_eligibility'])
                                    <span class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-3 py-1 text-xs font-medium text-orange-700">
                                        <i class="fa-solid fa-star text-xs"></i> Special eligibility
                                    </span>
                                @endif
                                @if (!$product['requires_no_existing_loan']
                                    && (float) $product['minimum_share_capital'] === 0.0
                                    && (int) $product['minimum_membership_years'] === 0
                                    && (int) $product['required_co_makers'] === 0
                                    && !$product['requires_interview']
                                    && !$product['requires_special_eligibility'])
                                    <span class="text-xs italic text-gray-400">No special requirements</span>
                                @endif
                            </div>
                            @if (!empty($product['description']))
                                <p class="mt-3 text-sm text-gray-600">{{ $product['description'] }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right text-xs text-gray-400">
                            <p>Sort order: {{ (int) $product['sort_order'] }}</p>
                        </div>
                    </div>

                    @if ($isAdmin)
                        <div class="mt-4 flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
                            <button type="button"
                                wire:click="editLoanProduct({{ $product['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="editLoanProduct({{ $product['id'] }})"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-1.5 text-xs font-medium text-cyan-700 hover:bg-cyan-100 focus:outline-none focus:ring-2 focus:ring-cyan-300">
                                <i class="fa-solid fa-pen-to-square text-xs"></i> Edit
                            </button>
                            <button type="button"
                                wire:click="toggleLoanProductStatus({{ $product['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="toggleLoanProductStatus({{ $product['id'] }})"
                                class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium focus:outline-none focus:ring-2
                                    {{ $product['is_active']
                                        ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 focus:ring-amber-300'
                                        : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100 focus:ring-green-300' }}">
                                <i class="fa-solid fa-{{ $product['is_active'] ? 'eye-slash' : 'eye' }} text-xs"></i>
                                {{ $product['is_active'] ? 'Disable' : 'Enable' }}
                            </button>
                            <button type="button"
                                wire:click="deleteLoanProduct({{ $product['id'] }})"
                                wire:confirm="Are you sure you want to delete '{{ $product['name'] }}'? This action cannot be undone."
                                class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-300">
                                <i class="fa-solid fa-trash text-xs"></i> Delete
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-16 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                    <i class="fa-solid fa-hand-holding-dollar text-2xl text-gray-400"></i>
                </div>
                <h3 class="mb-1 text-sm font-semibold text-gray-900">No loan products yet</h3>
                <p class="mb-4 text-sm text-gray-500">Get started by adding your first loan product.</p>
                @if ($isAdmin)
                    <button type="button" wire:click="openAddModal"
                        class="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 focus:outline-none focus:ring-4 focus:ring-cyan-300">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                clip-rule="evenodd" />
                        </svg>
                        Add Loan Product
                    </button>
                @endif
            </div>
        @endforelse
    </div>

    {{-- ── Add / Edit Modal ── --}}
    @if ($showModal)
        <div x-data="{ show: false }"
            x-init="setTimeout(() => show = true, 10)"
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-16"
            aria-labelledby="loan-modal-title" role="dialog" aria-modal="true">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-gray-900 opacity-50"
                @click="show = false; setTimeout(() => $wire.closeModal(), 150)"></div>

            {{-- Modal panel --}}
            <div x-show="show"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative z-10 w-full max-w-2xl">
                <div class="relative rounded-xl bg-white shadow-2xl">

                    {{-- Modal header --}}
                    <div class="flex items-start justify-between rounded-t-xl border-b border-gray-200 px-6 py-4">
                        <div>
                            <h3 id="loan-modal-title" class="text-lg font-semibold text-gray-900">
                                {{ $editingLoanProductId ? 'Edit Loan Product' : 'Add Loan Product' }}
                            </h3>
                            <p class="mt-0.5 text-xs text-gray-500">
                                {{ $editingLoanProductId
                                    ? 'Update product limits and eligibility rules.'
                                    : 'Define a new product with limits and member eligibility rules.' }}
                            </p>
                        </div>
                        <button type="button"
                            @click="show = false; setTimeout(() => $wire.closeModal(), 150)"
                            class="ml-4 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-300">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                    {{-- Modal body --}}
                    <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
                        <form wire:key="loan-product-form-{{ $editingLoanProductId ?? 'new' }}"
                            wire:submit="saveLoanProduct"
                            id="loan-product-form"
                            class="space-y-4">

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <x-forms.text-input name="code" label="Code" model="code" :required="true"
                                    maxlength="50" placeholder="agri_boost"
                                    helperText="Unique slug, e.g. agri_boost" :validateOnBlur="false" />
                                <x-forms.text-input name="name" label="Display Name" model="name" :required="true"
                                    maxlength="120" placeholder="Agriculture Boost Loan" :validateOnBlur="false" />
                            </div>

                            <div>
                                <label for="loan-base-type"
                                    class="mb-2 block text-sm font-medium text-gray-900">Base Type <span class="text-red-500">*</span></label>
                                <select id="loan-base-type" wire:model="baseType"
                                    class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                                    <option value="group">Group</option>
                                    <option value="business">Business</option>
                                    <option value="special">Special</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label for="loan-interest-rate"
                                        class="mb-2 block text-sm font-medium text-gray-900">Interest Rate (%)</label>
                                    <input id="loan-interest-rate" wire:model="interestRate" type="number"
                                        step="0.01" min="0"
                                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                                    @error('interestRate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="loan-max-amount"
                                        class="mb-2 block text-sm font-medium text-gray-900">Max Amount (₱)</label>
                                    <input id="loan-max-amount" wire:model="maxAmount" type="number"
                                        step="0.01" min="0"
                                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                                    @error('maxAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="loan-max-term"
                                        class="mb-2 block text-sm font-medium text-gray-900">Max Term (months)</label>
                                    <input id="loan-max-term" wire:model="maxTerm" type="number" min="1"
                                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                                    @error('maxTerm') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label for="loan-min-share"
                                        class="mb-2 block text-sm font-medium text-gray-900">Min Share Capital (₱)</label>
                                    <input id="loan-min-share" wire:model="minimumShareCapital" type="number"
                                        step="0.01" min="0"
                                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                                    @error('minimumShareCapital') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="loan-min-years"
                                        class="mb-2 block text-sm font-medium text-gray-900">Min Membership Yrs</label>
                                    <input id="loan-min-years" wire:model="minimumMembershipYears" type="number"
                                        min="0"
                                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                                    @error('minimumMembershipYears') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="loan-co-makers"
                                        class="mb-2 block text-sm font-medium text-gray-900">Required Co-Makers</label>
                                    <input id="loan-co-makers" wire:model="requiredCoMakers" type="number"
                                        min="0"
                                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                                    @error('requiredCoMakers') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="loan-icon"
                                        class="mb-2 block text-sm font-medium text-gray-900">
                                        Icon
                                        <span class="font-normal text-gray-400">(Font Awesome name)</span>
                                    </label>
                                    <input id="loan-icon" wire:model="icon" type="text"
                                        placeholder="wallet, briefcase, users, seedling"
                                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                                </div>
                                <div>
                                    <label for="loan-sort-order"
                                        class="mb-2 block text-sm font-medium text-gray-900">Sort Order</label>
                                    <input id="loan-sort-order" wire:model="sortOrder" type="number" min="0"
                                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500">
                                    @error('sortOrder') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <label for="loan-description"
                                    class="mb-2 block text-sm font-medium text-gray-900">Description</label>
                                <textarea id="loan-description" wire:model="description" rows="3"
                                    placeholder="Brief description of this loan product…"
                                    class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500"></textarea>
                                @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <fieldset class="rounded-lg border border-gray-200 p-4">
                                <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Flags & Requirements
                                </legend>
                                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" wire:model="isActive"
                                            class="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500">
                                        Active product
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" wire:model="requiresNoExistingLoan"
                                            class="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500">
                                        No existing active loan
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" wire:model="requiresInterview"
                                            class="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500">
                                        Require interview schedule
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" wire:model="requiresSpecialEligibility"
                                            class="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500">
                                        Require special-eligibility flag
                                    </label>
                                </div>
                            </fieldset>
                        </form>
                    </div>

                    {{-- Modal footer --}}
                    <div class="flex items-center justify-end gap-2 rounded-b-xl border-t border-gray-200 px-6 py-4">
                        <button type="button"
                            @click="show = false; setTimeout(() => $wire.closeModal(), 150)"
                            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-100">
                            Cancel
                        </button>
                        <button type="submit" form="loan-product-form"
                            class="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 focus:outline-none focus:ring-4 focus:ring-cyan-300 disabled:opacity-60">
                            <span wire:loading.remove wire:target="saveLoanProduct">
                                {{ $editingLoanProductId ? 'Update Product' : 'Create Product' }}
                            </span>
                            <span wire:loading wire:target="saveLoanProduct" class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                Saving…
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>

