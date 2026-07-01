{{-- Auto-refresh occasionally --}}
<div wire:poll.visible.60s>
    <div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
        <div class="w-full mb-1">
            <div class="mb-4">
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Loans Management</h1>
                <p class="text-sm text-gray-500">Manage loan applications and payments</p>
            </div>

            <div class="sm:flex">
                <div class="items-center hidden mb-3 sm:flex sm:mb-0">
                    <form class="lg:pr-3">
                        <label for="loans-search" class="sr-only">Search</label>
                        <div class="relative mt-1 lg:w-64 xl:w-96">
                            <input type="text" wire:model.live.debounce.300ms="search" id="loans-search"
                                class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                placeholder="Search loans...">
                        </div>
                    </form>
                </div>
                <div class="flex items-center ml-auto space-x-2">
                    @if ($canEncode)
                        <button type="button" wire:click="openCreateModal"
                            class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-blue-700 border border-transparent rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4"></path>
                            </svg>
                            Encode Loan
                        </button>
                    @endif
                    <button type="button" wire:click="$refresh"
                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:ring-blue-300"
                        title="Refresh data">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                            </path>
                        </svg>
                    </button>

                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-col">
        <div class="overflow-x-auto">
            <div class="inline-block min-w-full align-middle">
                <div class="overflow-hidden shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-4"><input wire:model="selectAll" type="checkbox"
                                        class="w-4 h-4 rounded"></th>
                                @if ($canEdit)
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Loan ID</th>
                                @endif
                                <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Member</th>
                                <th wire:click="sortBy('loan_amount')"
                                    class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                                    Amount
                                    @if ($sortField === 'loan_amount')
                                        <span class="ml-1">{!! $sortDirection === 'asc' ? '↑' : '↓' !!}</span>
                                    @endif
                                </th>
                                <th wire:click="sortBy('loan_type')"
                                    class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                                    Type
                                    @if ($sortField === 'loan_type')
                                        <span class="ml-1">{!! $sortDirection === 'asc' ? '↑' : '↓' !!}</span>
                                    @endif
                                </th>
                                <th wire:click="sortBy('loan_status')"
                                    class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                                    Status
                                    @if ($sortField === 'loan_status')
                                        <span class="ml-1">{!! $sortDirection === 'asc' ? '↑' : '↓' !!}</span>
                                    @endif
                                </th>
                                <th wire:click="sortBy('created_at')"
                                    class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                                    Date
                                    @if ($sortField === 'created_at')
                                        <span class="ml-1">{!! $sortDirection === 'asc' ? '↑' : '↓' !!}</span>
                                    @endif
                                </th>
                                @if ($canEdit)
                                    <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($loans as $loan)
                                <tr class="hover:bg-gray-100 cursor-pointer"
                                    wire:click="viewDetails({{ $loan->id }})">
                                    <td class="p-4" onclick="event.stopPropagation()">
                                        <input wire:model="selectedRows" value="{{ $loan->id }}" type="checkbox"
                                            class="w-4 h-4 rounded">
                                    </td>
                                    @if ($canEdit)
                                        <td class="p-4 text-sm font-semibold text-gray-900">{{ $loan->loan_number }}</td>
                                    @endif
                                    <td class="p-4 text-base font-medium text-gray-900">
                                        {{ $loan->user?->personalData?->first_name ?? '' }}
                                        {{ $loan->user?->personalData?->last_name ?? '' }}
                                        @if ($loan->user?->user_number)
                                            <div class="text-xs text-gray-500">{{ $loan->user->user_number }}</div>
                                        @endif
                                    </td>
                                    <td class="p-4 text-base font-semibold text-gray-900">
                                        ₱{{ number_format($loan->loan_amount, 2) }}</td>
                                    <td class="p-4">
                                        <span
                                            class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">{{ ucfirst($loan->loan_type ?? 'Regular') }}</span>
                                    </td>
                                    <td class="p-4">
                                        @if ($loan->loan_status === 'approved')
                                            <span
                                                class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">Approved</span>
                                        @elseif($loan->loan_status === 'active')
                                            <span
                                                class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">Active</span>
                                        @elseif($loan->loan_status === 'paid_off')
                                            <span
                                                class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded">Paid Off</span>
                                        @elseif($loan->loan_status === 'pending')
                                            <span
                                                class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2.5 py-0.5 rounded">Pending</span>
                                        @else
                                            <span
                                                class="bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded">{{ ucfirst($loan->loan_status) }}</span>
                                        @endif
                                    </td>
                                    <td class="p-4 text-base text-gray-900">{{ $loan->created_at->format('M d, Y') }}
                                    </td>
                                    @if ($canEdit)
                                        <td class="p-4 space-x-2" onclick="event.stopPropagation()">
                                            <button
                                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path
                                                        d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z">
                                                    </path>
                                                </svg>
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canEdit ? '8' : '7' }}" class="p-12 text-center">
                                        <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                            </path>
                                        </svg>
                                        <p class="text-sm text-gray-500">No loans found</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="sticky bottom-0 items-center w-full p-4 bg-white border-t border-gray-200 sm:flex sm:justify-between">
        <span class="text-sm text-gray-500">Showing <span
                class="font-semibold text-gray-900">{{ $loans->firstItem() ?? 0 }}-{{ $loans->lastItem() ?? 0 }}</span>
            of <span class="font-semibold text-gray-900">{{ $loans->total() }}</span></span>
        <div class="flex items-center space-x-3">
            {{ $loans->links() }}
        </div>
    </div>

    {{-- Details Modal --}}
    @if ($showModal && $selectedLoan)
        <x-modals.member-loan-detail :loan="$selectedLoan" :can-upload-documents="$canUploadDocuments" />
    @endif

    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 sm:px-6" wire:keydown.escape="closeCreateModal">
            <div class="absolute inset-0 bg-gray-900/50" wire:click="closeCreateModal"></div>

            <div class="relative w-full max-w-3xl overflow-hidden bg-white rounded-2xl shadow-2xl">
                <div class="flex items-start justify-between px-6 py-5 border-b border-gray-200">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Encode Loan</h2>
                        <p class="mt-1 text-sm text-gray-500">Create a loan record for a member using the active loan products in the system. This action is restricted to admins with the loan encoding permission.</p>
                    </div>
                    <button type="button" wire:click="closeCreateModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form wire:submit="saveEncodedLoan" class="px-6 py-5 space-y-6">
                    <div>
                        <label for="loan-member-search" class="block text-sm font-medium text-gray-700">Member</label>
                        <div class="relative mt-1">
                            <input id="loan-member-search" type="text" wire:model.live.debounce.300ms="memberSearch"
                                autocomplete="off"
                                class="block w-full px-4 py-3 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Search by member name or ID">

                            @if ($this->memberSearchResults->isNotEmpty())
                                <div class="absolute z-10 w-full mt-2 overflow-hidden bg-white border border-gray-200 rounded-lg shadow-lg">
                                    @foreach ($this->memberSearchResults as $member)
                                        <button type="button" wire:key="member-option-{{ $member->id }}"
                                            wire:click="selectMember({{ $member->id }})"
                                            class="flex items-center justify-between w-full px-4 py-3 text-left hover:bg-gray-50">
                                            <span class="text-sm font-medium text-gray-900">
                                                {{ $member->personalData?->first_name ?? '' }} {{ $member->personalData?->last_name ?? '' }}
                                            </span>
                                            <span class="text-xs text-gray-500">{{ $member->user_number }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif (filled(trim($memberSearch)) && ! $selectedMemberId)
                                <div class="mt-2 text-sm text-gray-500">No matching members found.</div>
                            @endif
                        </div>
                        @if ($this->selectedMember)
                            <p class="mt-2 text-sm text-green-700">Selected: {{ $this->selectedMember->personalData?->first_name ?? '' }} {{ $this->selectedMember->personalData?->last_name ?? '' }} ({{ $this->selectedMember->user_number }})</p>
                        @endif
                        @error('selectedMemberId')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="encoded-loan-product" class="block text-sm font-medium text-gray-700">Loan Product</label>
                            <select id="encoded-loan-product" wire:model.live="encodedLoanProduct"
                                class="block w-full px-4 py-3 mt-1 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500">
                                @foreach ($loanProductOptions as $product)
                                    <option value="{{ $product['code'] }}">{{ $product['name'] }}</option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-xs text-gray-500">Base type: {{ ucfirst($encodedLoanType) }}</p>
                            @error('encodedLoanProduct')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('encodedLoanType')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="encoded-loan-status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="encoded-loan-status" wire:model="encodedLoanStatus"
                                class="block w-full px-4 py-3 mt-1 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="active">Active</option>
                                <option value="paid_off">Paid Off</option>
                            </select>
                            @error('encodedLoanStatus')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="encoded-payment-frequency" class="block text-sm font-medium text-gray-700">Payment Frequency</label>
                            <select id="encoded-payment-frequency" wire:model="encodedPaymentFrequency"
                                class="block w-full px-4 py-3 mt-1 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500">
                                <option value="weekly">Weekly</option>
                                <option value="bi-weekly">Bi-weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="annually">Annually</option>
                            </select>
                            @error('encodedPaymentFrequency')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="encoded-loan-amount" class="block text-sm font-medium text-gray-700">Loan Amount</label>
                            <input id="encoded-loan-amount" type="number" min="1" step="0.01" wire:model="encodedLoanAmount"
                                class="block w-full px-4 py-3 mt-1 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500"
                                placeholder="0.00">
                            @error('encodedLoanAmount')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="encoded-interest-rate" class="block text-sm font-medium text-gray-700">Interest Rate (%)</label>
                            <input id="encoded-interest-rate" type="number" min="0" max="100" step="0.01" wire:model="encodedInterestRate"
                                class="block w-full px-4 py-3 mt-1 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500"
                                placeholder="0.00">
                            @error('encodedInterestRate')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="encoded-loan-term" class="block text-sm font-medium text-gray-700">Term (Months)</label>
                            <input id="encoded-loan-term" type="number" min="1" max="360" step="1" wire:model="encodedLoanTerm"
                                class="block w-full px-4 py-3 mt-1 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500"
                                placeholder="12">
                            @error('encodedLoanTerm')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="encoded-applied-at" class="block text-sm font-medium text-gray-700">Application Date</label>
                            <input id="encoded-applied-at" type="date" wire:model="appliedAt"
                                class="block w-full px-4 py-3 mt-1 bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-500">
                            @error('appliedAt')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end pt-2 space-x-3 border-t border-gray-200">
                        <button type="button" wire:click="closeCreateModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                            Save Loan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
