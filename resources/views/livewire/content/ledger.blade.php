{{-- Auto-refresh every 20 seconds --}}
<div wire:poll.20s>
    <div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
        <div class="w-full mb-1">
            <div class="mb-4">
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Ledger</h1>
                <p class="text-sm text-gray-500">Manage all financial transactions and records</p>
            </div>

            {{-- Tabs for filtering by transaction type --}}
            <div class="mb-4 border-b border-gray-200">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" role="tablist">
                    @foreach ($categories as $key => $label)
                        <li class="mr-2" role="presentation">
                            {{-- $set is Livewire magic method for direct property update --}}
                            <button wire:click="$set('selectedCategory', '{{ $key }}')"
                                @class([
                                    'inline-block p-4 border-b-2 rounded-t-lg transition-colors',
                                    'text-blue-600 border-blue-600' => $selectedCategory === $key,
                                    'border-transparent hover:text-gray-600 hover:border-gray-300' =>
                                        $selectedCategory !== $key,
                                ]) type="button" role="tab">
                                {{ $label }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="sm:flex">
                <div class="items-center hidden mb-3 sm:flex sm:divide-x sm:divide-gray-100 sm:mb-0">
                    <form class="lg:pr-3">
                        <label for="ledger-search" class="sr-only">Search</label>
                        <div class="relative mt-1 lg:w-64 xl:w-96">
                            <input type="text" wire:model.live.debounce.300ms="search" id="ledger-search"
                                class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                placeholder="Search by member name...">
                        </div>
                    </form>
                </div>
                <div class="flex items-center ml-auto space-x-2 sm:space-x-3">
                    <button type="button" wire:click="$refresh"
                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:ring-blue-300"
                        title="Refresh data">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                            </path>
                        </svg>
                    </button>
                    @if ($canEdit)
                        <button type="button" wire:click="addTransaction"
                            class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                            <svg class="w-5 h-5 mr-2 -ml-1" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z">
                                </path>
                            </svg>
                            Add Transaction
                        </button>
                    @endif
                    <button type="button"
                        class="inline-flex items-center justify-center w-1/2 px-3 py-2 text-sm font-medium text-center text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:ring-blue-300 sm:w-auto">
                        <svg class="w-5 h-5 mr-2 -ml-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                                clip-rule="evenodd"></path>
                        </svg>
                        Filter
                    </button>
                    <button type="button"
                        class="inline-flex items-center justify-center w-1/2 px-3 py-2 text-sm font-medium text-center text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 sm:w-auto">
                        <svg class="w-5 h-5 mr-2 -ml-1" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M8.707 7.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l2-2a1 1 0 00-1.414-1.414L11 7.586V3a1 1 0 10-2 0v4.586l-.293-.293z">
                            </path>
                            <path
                                d="M3 5a2 2 0 012-2h1a1 1 0 010 2H5v7h2l1 2h4l1-2h2V5h-1a1 1 0 110-2h1a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V5z">
                            </path>
                        </svg>
                        Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Daily Summary Strip (counts) --}}
    <div class="p-4 bg-white border-b border-gray-200">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">{{ $dailySummary['range_label'] ?? 'Today' }} Summary
                </h2>
                <p class="text-xs text-gray-500">Completed only</p>
            </div>

            <div class="flex items-center space-x-2">
                <label for="ledger-range" class="text-xs font-medium text-gray-500">Range</label>
                <select id="ledger-range" wire:model.live="range"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2">
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 mt-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="p-3 border border-gray-200 rounded-lg">
                <div class="text-xs font-medium text-gray-500">Deposits</div>
                <div class="mt-1 text-lg font-semibold text-gray-900">{{ $dailySummary['deposit_count'] ?? 0 }}</div>
            </div>
            <div class="p-3 border border-gray-200 rounded-lg">
                <div class="text-xs font-medium text-gray-500">Withdrawals</div>
                <div class="mt-1 text-lg font-semibold text-gray-900">{{ $dailySummary['withdrawal_count'] ?? 0 }}</div>
            </div>
            <div class="p-3 border border-gray-200 rounded-lg">
                <div class="text-xs font-medium text-gray-500">Loan Payments</div>
                <div class="mt-1 text-lg font-semibold text-gray-900">{{ $dailySummary['loan_payment_count'] ?? 0 }}
                </div>
            </div>
            <div class="p-3 border border-gray-200 rounded-lg">
                <div class="text-xs font-medium text-gray-500">Loans</div>
                <div class="mt-1 text-lg font-semibold text-gray-900">{{ $dailySummary['loan_count'] ?? 0 }}</div>
            </div>
        </div>
    </div>

    <div class="flex flex-col">
        <div class="overflow-x-auto">
            <div class="inline-block min-w-full align-middle">
                <div class="overflow-hidden shadow">
                    <table class="min-w-full divide-y divide-gray-200 table-fixed">
                        <thead class="bg-gray-100">
                            <tr>
                                <th scope="col" class="p-4">
                                    <div class="flex items-center">
                                        <input wire:model="selectAll" type="checkbox"
                                            class="w-4 h-4 bg-gray-50 rounded border-gray-300 focus:ring-3 focus:ring-blue-300">
                                        <label class="sr-only">checkbox</label>
                                    </div>
                                </th>
                                @if ($canEdit)
                                    <th scope="col" wire:click="sortBy('id')"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                                        Transaction ID
                                    </th>
                                @endif
                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                    Member
                                </th>
                                <th scope="col" wire:click="sortBy('transaction_type')"
                                    class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                                    Type
                                    @if ($sortField === 'transaction_type')
                                        <span class="ml-1">{!! $sortDirection === 'asc' ? '↑' : '↓' !!}</span>
                                    @endif
                                </th>
                                <th scope="col" wire:click="sortBy('amount')"
                                    class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                                    Amount
                                    @if ($sortField === 'amount')
                                        <span class="ml-1">{!! $sortDirection === 'asc' ? '↑' : '↓' !!}</span>
                                    @endif
                                </th>
                                <th scope="col" wire:click="sortBy('status')"
                                    class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                                    Status
                                    @if ($sortField === 'status')
                                        <span class="ml-1">{!! $sortDirection === 'asc' ? '↑' : '↓' !!}</span>
                                    @endif
                                </th>
                                <th scope="col" wire:click="sortBy('transaction_date')"
                                    class="p-4 text-xs font-medium text-left text-gray-500 uppercase cursor-pointer hover:bg-gray-200">
                                    Date
                                    @if ($sortField === 'transaction_date')
                                        <span class="ml-1">{!! $sortDirection === 'asc' ? '↑' : '↓' !!}</span>
                                    @endif
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($transactions as $transaction)
                                <tr class="hover:bg-gray-100 cursor-pointer"
                                    wire:click="viewDetails('{{ $transaction->id }}')">
                                    <td class="w-4 p-4" onclick="event.stopPropagation()">
                                        <div class="flex items-center">
                                            <input wire:model="selectedRows" value="{{ $transaction->id }}"
                                                type="checkbox"
                                                class="w-4 h-4 bg-gray-50 rounded border-gray-300 focus:ring-3 focus:ring-blue-300">
                                            <label class="sr-only">checkbox</label>
                                        </div>
                                    </td>
                                    @if ($canEdit)
                                        <td class="p-4 text-sm font-normal text-gray-900 whitespace-nowrap">
                                            <div class="text-base font-semibold text-gray-900">
                                                {{ $transaction->transaction_number }}
                                            </div>
                                        </td>
                                    @endif
                                    <td class="p-4 text-base font-medium text-gray-900 whitespace-nowrap">
                                        @if ($transaction->account?->user?->personalData)
                                            {{ $transaction->account->user->personalData->first_name }}
                                            {{ $transaction->account->user->personalData->last_name }}
                                        @elseif($transaction->loan?->user?->personalData)
                                            {{ $transaction->loan->user->personalData->first_name }}
                                            {{ $transaction->loan->user->personalData->last_name }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="p-4 text-base font-normal text-gray-900 whitespace-nowrap">
                                        @switch($transaction->transaction_type)
                                            @case('deposit')
                                                <span
                                                    class="bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Deposit</span>
                                            @break

                                            @case('withdrawal')
                                                <span
                                                    class="bg-orange-100 text-orange-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Withdrawal</span>
                                            @break

                                            @case('loan_payment')
                                                <span
                                                    class="bg-green-100 text-green-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Loan
                                                    Payment</span>
                                            @break

                                            @case('loan_disbursement')
                                                <span
                                                    class="bg-purple-100 text-purple-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Loan
                                                    Disbursement</span>
                                            @break

                                            @case('fee')
                                                <span
                                                    class="bg-red-100 text-red-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Fee</span>
                                            @break

                                            @default
                                                <span
                                                    class="bg-gray-100 text-gray-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">{{ ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}</span>
                                        @endswitch
                                    </td>
                                    <td class="p-4 text-base font-semibold text-gray-900 whitespace-nowrap">
                                        @if (in_array($transaction->transaction_type, ['withdrawal', 'fee']))
                                            <span
                                                class="text-red-600">-₱{{ number_format($transaction->amount, 2) }}</span>
                                        @else
                                            <span
                                                class="text-green-600">+₱{{ number_format($transaction->amount, 2) }}</span>
                                        @endif
                                    </td>
                                    <td class="p-4 text-base font-normal text-gray-900 whitespace-nowrap">
                                        <span
                                            class="bg-green-100 text-green-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded">Completed</span>
                                    </td>
                                    <td class="p-4 text-base font-normal text-gray-900 whitespace-nowrap">
                                        {{ $transaction->transaction_date ? $transaction->transaction_date->format('M d, Y') : $transaction->created_at->format('M d, Y') }}
                                    </td>
                                </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-4 text-center">
                                            <div class="flex flex-col items-center justify-center py-12">
                                                <svg class="w-16 h-16 text-gray-400 mb-4" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4">
                                                    </path>
                                                </svg>
                                                <p class="text-sm text-gray-500">No transactions found</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pagination --}}
        <div
            class="sticky bottom-0 right-0 items-center w-full p-4 bg-white border-t border-gray-200 sm:flex sm:justify-between">
            <div class="flex items-center mb-4 sm:mb-0">
                <span class="text-sm font-normal text-gray-500">Showing <span
                        class="font-semibold text-gray-900">{{ $transactions->firstItem() ?? 0 }}-{{ $transactions->lastItem() ?? 0 }}</span>
                    of <span class="font-semibold text-gray-900">{{ $transactions->total() }}</span></span>
            </div>
            <div class="flex items-center space-x-3">
                <button wire:click="previousPage" @if (!$transactions->onFirstPage()) @else disabled @endif
                    class="inline-flex items-center justify-center flex-1 px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-5 h-5 mr-1 -ml-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                            clip-rule="evenodd"></path>
                    </svg>
                    Previous
                </button>
                <span class="text-sm font-normal text-gray-500">Page <span
                        class="font-semibold text-gray-900">{{ $transactions->currentPage() }}</span> of <span
                        class="font-semibold text-gray-900">{{ $transactions->lastPage() }}</span></span>
                <button wire:click="nextPage" @if ($transactions->hasMorePages()) @else disabled @endif
                    class="inline-flex items-center justify-center flex-1 px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 disabled:opacity-50 disabled:cursor-not-allowed">
                    Next
                    <svg class="w-5 h-5 ml-1 -mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                            clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Modal --}}
        @if ($showModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none"
                aria-labelledby="modal-title" role="dialog" aria-modal="true">
                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-gray-900 opacity-30 transition-opacity" wire:click="closeModal"></div>

                {{-- Modal content --}}
                <div class="relative w-full max-w-2xl max-h-full mx-auto my-6">
                    <div class="relative bg-white rounded-lg shadow">
                        {{-- Modal header --}}
                        <div class="flex items-start justify-between p-5 border-b border-gray-200 rounded-t">
                            <h3 class="text-xl font-semibold text-gray-900">
                                Transaction Details
                            </h3>
                            <button type="button" wire:click="closeModal"
                                class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                        clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>
                        {{-- Modal body --}}
                        <div class="p-6 space-y-6">
                            @if ($selectedTransaction && isset($selectedTransaction['data']))
                                @php $txn = $selectedTransaction['data']; @endphp
                                <div class="grid grid-cols-2 gap-6">
                                    <div>
                                        <dt class="mb-2 font-semibold leading-none text-gray-900">Transaction Number</dt>
                                        <dd class="mb-4 font-light text-gray-500 sm:mb-5">{{ $txn->transaction_number }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="mb-2 font-semibold leading-none text-gray-900">Type</dt>
                                        <dd class="mb-4 font-light text-gray-500 sm:mb-5">
                                            {{ ucfirst(str_replace('_', ' ', $txn->transaction_type)) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="mb-2 font-semibold leading-none text-gray-900">Amount</dt>
                                        <dd class="mb-4 font-light text-gray-500 sm:mb-5">
                                            ₱{{ number_format($txn->amount, 2) }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="mb-2 font-semibold leading-none text-gray-900">Transaction Date</dt>
                                        <dd class="mb-4 font-light text-gray-500 sm:mb-5">
                                            {{ $txn->transaction_date ? $txn->transaction_date->format('F d, Y h:i A') : $txn->created_at->format('F d, Y h:i A') }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="mb-2 font-semibold leading-none text-gray-900">Member</dt>
                                        <dd class="mb-4 font-light text-gray-500 sm:mb-5">
                                            @if ($txn->account?->user?->personalData)
                                                {{ $txn->account->user->personalData->first_name }}
                                                {{ $txn->account->user->personalData->last_name }}
                                            @elseif ($txn->loan?->user?->personalData)
                                                {{ $txn->loan->user->personalData->first_name }}
                                                {{ $txn->loan->user->personalData->last_name }}
                                            @else
                                                N/A
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="mb-2 font-semibold leading-none text-gray-900">Recorded By</dt>
                                        <dd class="mb-4 font-light text-gray-500 sm:mb-5">
                                            {{ $txn->recordedBy?->personalData ? $txn->recordedBy->personalData->first_name . ' ' . $txn->recordedBy->personalData->last_name : 'System' }}
                                        </dd>
                                    </div>
                                    @if ($txn->note)
                                        <div class="col-span-2">
                                            <dt class="mb-2 font-semibold leading-none text-gray-900">Note</dt>
                                            <dd class="mb-4 font-light text-gray-500 sm:mb-5">{{ $txn->note }}</dd>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                        {{-- Modal footer --}}
                        <div class="flex items-center p-6 space-x-2 border-t border-gray-200 rounded-b">
                            @if ($canEdit)
                                <button type="button" wire:click="editTransaction('{{ $selectedTransaction['id'] }}')"
                                    class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                                    Edit Transaction
                                </button>
                                <button type="button" wire:click="deleteTransaction('{{ $selectedTransaction['id'] }}')"
                                    wire:confirm="Are you sure you want to delete this transaction?"
                                    class="text-red-600 inline-flex items-center hover:text-white border border-red-600 hover:bg-red-600 focus:ring-4 focus:outline-none focus:ring-red-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                                    <svg class="w-5 h-5 mr-1 -ml-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    Delete
                                </button>
                            @else
                                <button type="button" wire:click="closeModal"
                                    class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10">
                                    Close
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Edit Transaction Modal --}}
        @if ($showEditModal)
            <x-modals.transaction-edit wire:key="transaction-edit-{{ $editForm['id'] ?? 'new' }}" :transaction="$editForm" />
        @endif

        {{-- Handle session flash messages --}}
        @if (session()->has('message'))
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    window.toast.success('{{ session('message') }}');
                });
            </script>
        @endif
        @if (session()->has('error'))
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    window.toast.error('{{ session('error') }}');
                });
            </script>
        @endif
    </div>
