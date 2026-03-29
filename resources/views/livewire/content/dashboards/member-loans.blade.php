<div class="min-h-screen">
    <div class="py-6 px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">My Loans</h1>
            <p class="mt-1 text-sm text-gray-600">View your personal and group loans, payment schedules, and history.</p>
        </div>

        {{-- Search and controls --}}
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                <div class="w-full sm:w-96">
                    <label for="search" class="sr-only">Search loans</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="search" wire:model.live.debounce.300ms="search" id="search"
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-cyan-500 focus:border-cyan-500 sm:text-sm"
                            placeholder="Search by loan number, type, amount, or status...">
                    </div>
                </div>
            </div>
        </div>

        {{-- Loans Table --}}
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-200"
                                wire:click="sortBy('loan_number')">
                                <div class="flex items-center space-x-1">
                                    <span>Loan Number</span>
                                    @if ($sortField === 'loan_number')
                                        @if ($sortDirection === 'asc')
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" />
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z" />
                                            </svg>
                                        @endif
                                    @endif
                                </div>
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-200"
                                wire:click="sortBy('loan_type')">
                                <div class="flex items-center space-x-1">
                                    <span>Type</span>
                                    @if ($sortField === 'loan_type')
                                        @if ($sortDirection === 'asc')
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" />
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z" />
                                            </svg>
                                        @endif
                                    @endif
                                </div>
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-200"
                                wire:click="sortBy('loan_amount')">
                                <div class="flex items-center justify-end space-x-1">
                                    <span>Amount</span>
                                    @if ($sortField === 'loan_amount')
                                        @if ($sortDirection === 'asc')
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" />
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z" />
                                            </svg>
                                        @endif
                                    @endif
                                </div>
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-200"
                                wire:click="sortBy('interest_rate')">
                                <div class="flex items-center justify-center space-x-1">
                                    <span>Interest Rate</span>
                                    @if ($sortField === 'interest_rate')
                                        @if ($sortDirection === 'asc')
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" />
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z" />
                                            </svg>
                                        @endif
                                    @endif
                                </div>
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-200"
                                wire:click="sortBy('loan_status')">
                                <div class="flex items-center justify-center space-x-1">
                                    <span>Status</span>
                                    @if ($sortField === 'loan_status')
                                        @if ($sortDirection === 'asc')
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" />
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z" />
                                            </svg>
                                        @endif
                                    @endif
                                </div>
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-200"
                                wire:click="sortBy('due_date')">
                                <div class="flex items-center justify-center space-x-1">
                                    <span>Due Date</span>
                                    @if ($sortField === 'due_date')
                                        @if ($sortDirection === 'asc')
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" />
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l2.293-2.293a1 1 0 011.414 0z" />
                                            </svg>
                                        @endif
                                    @endif
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($loans as $loan)
                            <tr class="hover:bg-gray-100 cursor-pointer transition-colors"
                                wire:click="viewDetails({{ $loan->id }})">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $loan->loan_number }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ ucfirst($loan->loan_type) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="text-sm font-semibold text-gray-900">
                                        ₱{{ number_format($loan->loan_amount, 2) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm text-gray-900">{{ $loan->interest_rate }}%</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if ($loan->loan_status === 'active')
                                        <span
                                            class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    @elseif($loan->loan_status === 'paid_off')
                                        <span
                                            class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            Paid Off
                                        </span>
                                    @elseif($loan->loan_status === 'approved')
                                        <span
                                            class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Approved
                                        </span>
                                    @elseif($loan->loan_status === 'pending')
                                        <span
                                            class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Pending
                                        </span>
                                    @else
                                        <span
                                            class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            {{ ucfirst($loan->loan_status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm text-gray-900">
                                        {{ $loan->due_date ? $loan->due_date->format('M d, Y') : 'N/A' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12">
                                    <div class="text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">No loans found</h3>
                                        <p class="mt-1 text-sm text-gray-500">
                                            @if ($search)
                                                No loans match your search criteria.
                                            @else
                                                You don't have any loans yet.
                                            @endif
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($loans->hasPages())
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    {{ $loans->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Details Modal --}}
    @if ($showModal && $selectedLoan)
        <div x-data="{ show: false, activeTab: 'details' }" x-init="setTimeout(() => show = true, 10)" x-show="show"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none"
            aria-labelledby="modal-title" role="dialog" aria-modal="true">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black opacity-25 transition-opacity" wire:click="closeModal"></div>

            {{-- Modal content --}}
            <div class="relative w-full max-w-5xl max-h-[90vh] mx-auto my-6 z-10">
                <div class="relative bg-white rounded-lg shadow overflow-hidden">
                    {{-- Modal header --}}
                    <div
                        class="flex items-start justify-between p-5 border-b border-gray-200 rounded-t bg-gradient-to-r from-cyan-500 to-emerald-500">
                        <div>
                            <h3 class="text-xl font-semibold text-white">
                                Loan Details
                            </h3>
                            <p class="text-sm text-white/90 mt-1">{{ $selectedLoan->loan_number }}</p>
                        </div>
                        <button type="button" wire:click="closeModal"
                            class="text-white bg-white/20 hover:bg-white/30 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    </div>

                    {{-- Tabs --}}
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button @click="activeTab = 'details'"
                                :class="activeTab === 'details' ? 'border-cyan-500 text-cyan-600' :
                                    'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm">
                                Loan Details
                            </button>
                            <button @click="activeTab = 'schedule'"
                                :class="activeTab === 'schedule' ? 'border-cyan-500 text-cyan-600' :
                                    'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm">
                                Payment Schedule
                            </button>
                            <button @click="activeTab = 'history'"
                                :class="activeTab === 'history' ? 'border-cyan-500 text-cyan-600' :
                                    'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm">
                                Payment History
                            </button>
                        </nav>
                    </div>

                    {{-- Modal body --}}
                    <div class="p-6 space-y-6 max-h-[calc(90vh-180px)] overflow-y-auto">

                        {{-- Details Tab --}}
                        <div x-show="activeTab === 'details'" class="space-y-6">
                            {{-- Loan Overview Cards --}}
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg">
                                    <div class="text-sm text-blue-600 font-medium">Loan Amount</div>
                                    <div class="text-2xl font-bold text-blue-900">
                                        ₱{{ number_format($selectedLoan->loan_amount, 2) }}</div>
                                </div>
                                <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg">
                                    <div class="text-sm text-green-600 font-medium">Total Paid</div>
                                    <div class="text-2xl font-bold text-green-900">
                                        ₱{{ number_format($selectedLoan->totalPaid(), 2) }}</div>
                                </div>
                                <div class="bg-gradient-to-br from-orange-50 to-orange-100 p-4 rounded-lg">
                                    <div class="text-sm text-orange-600 font-medium">Balance Remaining</div>
                                    <div class="text-2xl font-bold text-orange-900">
                                        ₱{{ number_format($selectedLoan->remainingBalance(), 2) }}</div>
                                </div>
                            </div>

                            {{-- Loan Information --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-3">Loan Information</h4>
                                    <dl class="space-y-2">
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-600">Loan Type:</dt>
                                            <dd class="text-sm font-medium text-gray-900">
                                                {{ ucfirst($selectedLoan->loan_type) }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-600">Interest Rate:</dt>
                                            <dd class="text-sm font-medium text-gray-900">
                                                {{ $selectedLoan->interest_rate }}% per annum</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-600">Term:</dt>
                                            <dd class="text-sm font-medium text-gray-900">
                                                {{ $selectedLoan->term_months }} months</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-600">Payment Frequency:</dt>
                                            <dd class="text-sm font-medium text-gray-900">
                                                {{ ucfirst($selectedLoan->payment_frequency) }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-600">Status:</dt>
                                            <dd>
                                                @if ($selectedLoan->loan_status === 'active')
                                                    <span
                                                        class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                                @elseif($selectedLoan->loan_status === 'paid_off')
                                                    <span
                                                        class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Paid
                                                        Off</span>
                                                @elseif($selectedLoan->loan_status === 'approved')
                                                    <span
                                                        class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Approved</span>
                                                @elseif($selectedLoan->loan_status === 'pending')
                                                    <span
                                                        class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Pending</span>
                                                @else
                                                    <span
                                                        class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">{{ ucfirst($selectedLoan->loan_status) }}</span>
                                                @endif
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-3">Important Dates</h4>
                                    <dl class="space-y-2">
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-600">Applied:</dt>
                                            <dd class="text-sm font-medium text-gray-900">
                                                {{ $selectedLoan->applied_at ? $selectedLoan->applied_at->format('M d, Y') : 'N/A' }}
                                            </dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-600">Approved:</dt>
                                            <dd class="text-sm font-medium text-gray-900">
                                                {{ $selectedLoan->approved_at ? $selectedLoan->approved_at->format('M d, Y') : 'N/A' }}
                                            </dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-600">Due Date:</dt>
                                            <dd class="text-sm font-medium text-gray-900">
                                                {{ $selectedLoan->due_date ? $selectedLoan->due_date->format('M d, Y') : 'N/A' }}
                                            </dd>
                                        </div>
                                        @if ($selectedLoan->approvedBy)
                                            <div class="flex justify-between">
                                                <dt class="text-sm text-gray-600">Approved By:</dt>
                                                <dd class="text-sm font-medium text-gray-900">
                                                    {{ $selectedLoan->approvedBy->personalData->first_name ?? 'N/A' }}
                                                    {{ $selectedLoan->approvedBy->personalData->last_name ?? '' }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                </div>
                            </div>

                            {{-- Group Members (if applicable) --}}
                            @if ($selectedLoan->groupMembers && $selectedLoan->groupMembers->count() > 0)
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-3">Group Loan Members</h4>
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <ul class="space-y-2">
                                            @foreach ($selectedLoan->groupMembers as $member)
                                                <li class="flex items-center justify-between">
                                                    <span class="text-sm text-gray-900">
                                                        {{ $member->personalData->first_name ?? '' }}
                                                        {{ $member->personalData->last_name ?? '' }}
                                                    </span>
                                                    @if ($member->pivot->has_approved)
                                                        <span
                                                            class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded">Approved
                                                            {{ $member->pivot->approved_at ? $member->pivot->approved_at->format('M d, Y') : '' }}</span>
                                                    @else
                                                        <span
                                                            class="text-xs px-2 py-1 bg-yellow-100 text-yellow-800 rounded">Pending
                                                            Approval</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Payment Schedule Tab --}}
                        <div x-show="activeTab === 'schedule'" class="space-y-4">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold text-gray-900">Amortization Schedule</h4>
                                <span class="text-sm text-gray-600">Total Periods:
                                    {{ count($this->generateAmortizationSchedule($selectedLoan)) }}</span>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                #</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Due Date</th>
                                            <th
                                                class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Payment</th>
                                            <th
                                                class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Principal</th>
                                            <th
                                                class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Interest</th>
                                            <th
                                                class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach ($this->generateAmortizationSchedule($selectedLoan) as $payment)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                    {{ $payment['payment_number'] }}</td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                    {{ $payment['due_date']->format('M d, Y') }}</td>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">
                                                    ₱{{ number_format($payment['payment_amount'], 2) }}</td>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">
                                                    ₱{{ number_format($payment['principal'], 2) }}</td>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 text-right">
                                                    ₱{{ number_format($payment['interest'], 2) }}</td>
                                                <td
                                                    class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                                    ₱{{ number_format($payment['balance'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Payment History Tab --}}
                        <div x-show="activeTab === 'history'" class="space-y-4">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold text-gray-900">Payment History</h4>
                                <span class="text-sm text-gray-600">Total Payments:
                                    {{ $selectedLoan->completedPayments->count() }}</span>
                            </div>

                            @if ($selectedLoan->completedPayments && $selectedLoan->completedPayments->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Date</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Transaction #</th>
                                                <th
                                                    class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Amount</th>
                                                <th
                                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach ($selectedLoan->completedPayments as $payment)
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                        {{ $payment->created_at->format('M d, Y') }}</td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                        {{ $payment->transaction_number ?? 'N/A' }}</td>
                                                    <td
                                                        class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 text-right">
                                                        ₱{{ number_format($payment->amount, 2) }}</td>
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <span
                                                            class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                            {{ ucfirst($payment->status) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-12">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No payment history</h3>
                                    <p class="mt-1 text-sm text-gray-500">No payments have been made for this loan yet.
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Modal footer --}}
                    <div class="flex items-center justify-end p-5 border-t border-gray-200 space-x-2">
                        <button type="button" wire:click="closeModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
