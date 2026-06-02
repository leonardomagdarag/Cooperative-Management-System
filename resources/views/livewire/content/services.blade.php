<div>
    <div class="space-y-6">
        {{-- Member Selection (Officer / Admin) --}}
        @if ($canManageMemberServices)
            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-cyan-500">
                <h3 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-user-tie text-cyan-600 mr-2"></i>
                    @if ($isTreasurerMode)
                        Approval Mode - Select Member
                    @elseif ($isLoanReviewMode)
                        Service Officer Mode - Select Member
                    @else
                        Select Member
                    @endif
                </h3>

                @if ($selectedMember)
                    <div class="flex items-center justify-between p-4 bg-cyan-50 rounded-lg border border-cyan-200">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-12 h-12 rounded-full bg-cyan-500 flex items-center justify-center text-white font-bold text-lg">
                                {{ strtoupper(substr($selectedMember->personalData->first_name ?? 'M', 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">
                                    {{ $selectedMember->personalData->first_name ?? '' }}
                                    {{ $selectedMember->personalData->last_name ?? '' }}
                                </p>
                                <p class="text-sm text-gray-600">{{ $selectedMember->user_number }}</p>
                            </div>
                        </div>
                        <button wire:click="clearMemberSelection"
                            class="px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                            <i class="fas fa-times mr-2"></i>Change Member
                        </button>
                    </div>
                @else
                    <div class="space-y-3">
                        <div class="relative">
                            <input type="text" wire:model.live.debounce.300ms="memberSearch"
                                placeholder="Search member by name..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-transparent">
                            <i
                                class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>

                        @if ($memberSearch && $members->count() > 0)
                            <div class="border border-gray-200 rounded-lg divide-y max-h-64 overflow-y-auto">
                                @foreach ($members as $member)
                                    <button wire:click="selectMember({{ $member->id }})"
                                        class="w-full flex items-center gap-3 p-3 hover:bg-gray-50 text-left transition-colors">
                                        <div
                                            class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-semibold">
                                            {{ strtoupper(substr($member->personalData->first_name ?? 'M', 0, 1)) }}
                                        </div>
                                        <div class="flex-1">
                                            <p class="font-medium text-gray-900">
                                                {{ $member->personalData->first_name ?? '' }}
                                                {{ $member->personalData->last_name ?? '' }}
                                            </p>
                                            <p class="text-sm text-gray-500">{{ $member->user_number }}</p>
                                        </div>
                                        <i class="fas fa-chevron-right text-gray-400"></i>
                                    </button>
                                @endforeach
                            </div>
                        @elseif($memberSearch)
                            <p class="text-sm text-gray-500 text-center py-4">No members found</p>
                        @else
                            <p class="text-sm text-gray-500 text-center py-4">Start typing to search for a member</p>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- Quick Info Cards --}}
        <div class="grid grid-cols-1 {{ $hideSelectedMemberFinancials ? 'md:grid-cols-2' : 'md:grid-cols-4' }} gap-4">
            @unless ($hideSelectedMemberFinancials)
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-5 border-l-4 border-green-500">
                    <p class="text-sm font-medium text-green-700 mb-1">Savings Balance</p>
                    <p class="text-2xl font-bold text-green-900">₱{{ number_format($savingsAccount?->balance ?? 0, 2) }}</p>
                </div>

                <div class="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl p-5 border-l-4 border-blue-500">
                    <p class="text-sm font-medium text-blue-700 mb-1">Share Capital</p>
                    <p class="text-2xl font-bold text-blue-900">
                        ₱{{ number_format($shareCapitalAccount?->balance ?? 0, 2) }}
                    </p>
                </div>
            @endunless

            <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-5 border-l-4 border-purple-500">
                <p class="text-sm font-medium text-purple-700 mb-1">Active Loans</p>
                <p class="text-2xl font-bold text-purple-900">{{ $activeLoans->count() }}</p>
            </div>

            <div class="bg-gradient-to-br from-orange-50 to-red-50 rounded-xl p-5 border-l-4 border-orange-500">
                <p class="text-sm font-medium text-orange-700 mb-1">Pending Requests</p>
                <p class="text-2xl font-bold text-orange-900">{{ $pendingRequests }}</p>
            </div>
        </div>

        {{-- Pending Withdrawals Section (Member View) --}}
        @if (!$isTreasurerMode && $pendingWithdrawals->count() > 0)
            <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">
                        <i class="fas fa-clock text-orange-600 mr-2"></i>
                        Pending Withdrawal Requests
                    </h3>
                    <span class="px-3 py-1 bg-orange-100 text-orange-800 text-sm font-semibold rounded-full">
                        {{ $pendingWithdrawals->count() }}
                        {{ $pendingWithdrawals->count() === 1 ? 'Request' : 'Requests' }}
                    </span>
                </div>

                <div class="space-y-3">
                    @foreach ($pendingWithdrawals as $withdrawal)
                        <div
                            class="flex items-center justify-between p-4 bg-orange-50 rounded-lg border border-orange-200">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span
                                        class="font-semibold text-gray-900">₱{{ number_format($withdrawal->amount, 2) }}</span>
                                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 text-xs font-medium rounded">
                                        Awaiting Approval
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600">
                                    Requested on {{ $withdrawal->created_at->format('M d, Y') }} at
                                    {{ $withdrawal->created_at->format('h:i A') }}
                                </p>
                                @if ($withdrawal->note)
                                    <p class="text-xs text-gray-500 mt-1">{{ $withdrawal->note }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <span class="text-sm text-gray-500">
                                    {{ $withdrawal->transaction_mode === 'counter' ? 'Counter' : 'Online' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
                        <p class="text-sm text-blue-800">
                            Your withdrawal request is being reviewed. You will receive a notification once it has been
                            approved or if additional information is needed.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Service Cards --}}
        <div class="bg-white rounded-xl shadow-sm p-6" x-data="{ needsMember: {{ $canManageMemberServices ? 'true' : 'false' }}, hasMember: {{ $canManageMemberServices && $selectedMember ? 'true' : 'false' }} }" x-init="$wire.on('member-selected', () => hasMember = true);
        $wire.on('member-cleared', () => hasMember = false)">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Available Services</h2>

            @if ($canManageMemberServices)
                {{-- Officer / Admin row: action panels (no member needed) --}}
                @if ($isTreasurerMode || $isAdminMode || $isLoanReviewMode)
                    <div
                        class="grid grid-cols-1 {{ ($isTreasurerMode || $isAdminMode) && $isLoanReviewMode ? 'md:grid-cols-2' : 'md:grid-cols-1' }} gap-4 mb-4">
                        {{-- Approve Transactions --}}
                        @if (in_array('approve', $availableServices, true))
                            <button wire:click="openService('approve')"
                                class="group bg-gradient-to-br from-yellow-50 to-amber-100 hover:from-yellow-100 hover:to-amber-200 rounded-xl p-5 text-left transition-all hover:shadow-lg relative">
                                @if ($pendingRequests > 0)
                                    <span
                                        class="absolute top-3 right-3 bg-red-500 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center">
                                        {{ $pendingRequests }}
                                    </span>
                                @endif
                                <div
                                    class="w-12 h-12 bg-yellow-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                    <i class="fas fa-clipboard-check text-white text-xl"></i>
                                </div>
                                <h3 class="text-base font-bold text-gray-900 mb-1">Approve Transactions</h3>
                                <p class="text-xs text-gray-600">Review and approve pending member requests</p>
                            </button>
                        @endif

                        {{-- Loan Applications (review) --}}
                        @if (in_array('loan-review', $availableServices, true))
                            <button wire:click="openService('loan-review')"
                                class="group bg-gradient-to-br from-indigo-50 to-violet-100 hover:from-indigo-100 hover:to-violet-200 rounded-xl p-5 text-left transition-all hover:shadow-lg relative">
                                @if ($pendingLoanReviews > 0)
                                    <span
                                        class="absolute top-3 right-3 bg-red-500 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center">
                                        {{ $pendingLoanReviews }}
                                    </span>
                                @endif
                                <div
                                    class="w-12 h-12 bg-indigo-600 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                    <i class="fas fa-file-alt text-white text-xl"></i>
                                </div>
                                <h3 class="text-base font-bold text-gray-900 mb-1">Loan Applications</h3>
                                <p class="text-xs text-gray-600">Review and endorse pending loan applications</p>
                            </button>
                        @endif
                    </div>
                @endif

                {{-- Divider --}}
                <div class="border-t border-gray-100 mb-4 pt-2">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">
                        <i class="fas fa-user-circle mr-1"></i>Member Services
                        <span x-show="needsMember && !hasMember" class="ml-2 text-amber-500 normal-case font-normal">
                            — select a member above to enable
                        </span>
                    </p>
                </div>

                {{-- Member service row (requires member selection) --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    @if (in_array('deposit', $availableServices, true))
                        {{-- Deposit --}}
                        <div class="relative group/card">
                            <button wire:click="openService('deposit')"
                                :class="needsMember && !hasMember ? 'opacity-50 cursor-not-allowed' : ''"
                                :disabled="needsMember && !hasMember"
                                class="group w-full bg-gradient-to-br from-green-50 to-emerald-100 hover:from-green-100 hover:to-emerald-200 rounded-xl p-5 text-left transition-all hover:shadow-lg">
                                <div
                                    class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <h3 class="text-base font-bold text-gray-900 mb-1">Deposit Money</h3>
                                <p class="text-xs text-gray-600">Add funds to savings or share capital</p>
                            </button>
                            <div x-show="needsMember && !hasMember"
                                class="absolute bottom-2 left-0 right-0 flex justify-center pointer-events-none">
                                <span
                                    class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full border border-amber-200">
                                    <i class="fas fa-lock text-amber-500 mr-1 text-xs"></i>Select member
                                </span>
                            </div>
                        </div>
                    @endif

                    @if (in_array('withdraw', $availableServices, true))
                        {{-- Withdraw --}}
                        <div class="relative group/card">
                            <button wire:click="openService('withdraw')"
                                :class="needsMember && !hasMember ? 'opacity-50 cursor-not-allowed' : ''"
                                :disabled="needsMember && !hasMember"
                                class="group w-full bg-gradient-to-br from-orange-50 to-red-100 hover:from-orange-100 hover:to-red-200 rounded-xl p-5 text-left transition-all hover:shadow-lg">
                                <div
                                    class="w-12 h-12 bg-orange-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z"
                                            clip-rule="evenodd" transform="rotate(180 10 10)" />
                                    </svg>
                                </div>
                                <h3 class="text-base font-bold text-gray-900 mb-1">Withdraw Money</h3>
                                <p class="text-xs text-gray-600">Request withdrawal from savings account</p>
                            </button>
                            <div x-show="needsMember && !hasMember"
                                class="absolute bottom-2 left-0 right-0 flex justify-center pointer-events-none">
                                <span
                                    class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full border border-amber-200">
                                    <i class="fas fa-lock text-amber-500 mr-1 text-xs"></i>Select member
                                </span>
                            </div>
                        </div>
                    @endif

                    @if (in_array('loan', $availableServices, true))
                        {{-- Apply for Loan --}}
                        <div class="relative group/card">
                            <button wire:click="openService('loan')"
                                :class="needsMember && !hasMember ? 'opacity-50 cursor-not-allowed' : ''"
                                :disabled="needsMember && !hasMember"
                                class="group w-full bg-gradient-to-br from-purple-50 to-pink-100 hover:from-purple-100 hover:to-pink-200 rounded-xl p-5 text-left transition-all hover:shadow-lg">
                                <div
                                    class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <h3 class="text-base font-bold text-gray-900 mb-1">Apply for Loan</h3>
                                <p class="text-xs text-gray-600">Apply for personal, housing, business or emergency loans
                                </p>
                            </button>
                            <div x-show="needsMember && !hasMember"
                                class="absolute bottom-2 left-0 right-0 flex justify-center pointer-events-none">
                                <span
                                    class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full border border-amber-200">
                                    <i class="fas fa-lock text-amber-500 mr-1 text-xs"></i>Select member
                                </span>
                            </div>
                        </div>
                    @endif

                    @if (in_array('payment', $availableServices, true))
                        {{-- Make Payment --}}
                        <div class="relative group/card">
                            <button wire:click="openService('payment')"
                                :class="needsMember && !hasMember ? 'opacity-50 cursor-not-allowed' : ''"
                                :disabled="needsMember && !hasMember"
                                class="group w-full bg-gradient-to-br from-blue-50 to-cyan-100 hover:from-blue-100 hover:to-cyan-200 rounded-xl p-5 text-left transition-all hover:shadow-lg">
                                <div
                                    class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <h3 class="text-base font-bold text-gray-900 mb-1">Make Payment</h3>
                                <p class="text-xs text-gray-600">Pay a member's active loan installments</p>
                            </button>
                            <div x-show="needsMember && !hasMember"
                                class="absolute bottom-2 left-0 right-0 flex justify-center pointer-events-none">
                                <span
                                    class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full border border-amber-200">
                                    <i class="fas fa-lock text-amber-500 mr-1 text-xs"></i>Select member
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
            @else
                {{-- Member view: single 3-column grid --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Deposit --}}
                    <button wire:click="openService('deposit')"
                        class="group bg-gradient-to-br from-green-50 to-emerald-100 hover:from-green-100 hover:to-emerald-200 rounded-xl p-5 text-left transition-all hover:shadow-lg">
                        <div
                            class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-gray-900 mb-1">Deposit Money</h3>
                        <p class="text-xs text-gray-600">Add funds to your savings or share capital account</p>
                    </button>

                    {{-- Withdraw --}}
                    <button wire:click="openService('withdraw')"
                        class="group bg-gradient-to-br from-orange-50 to-red-100 hover:from-orange-100 hover:to-red-200 rounded-xl p-5 text-left transition-all hover:shadow-lg">
                        <div
                            class="w-12 h-12 bg-orange-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z"
                                    clip-rule="evenodd" transform="rotate(180 10 10)" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-gray-900 mb-1">Withdraw Money</h3>
                        <p class="text-xs text-gray-600">Request withdrawal from your savings account</p>
                    </button>

                    {{-- Apply for Loan --}}
                    <button wire:click="openService('loan')"
                        class="group bg-gradient-to-br from-purple-50 to-pink-100 hover:from-purple-100 hover:to-pink-200 rounded-xl p-5 text-left transition-all hover:shadow-lg">
                        <div
                            class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-gray-900 mb-1">Apply for Loan</h3>
                        <p class="text-xs text-gray-600">Apply for personal, housing, business or emergency loans</p>
                    </button>

                    {{-- Loan Review (officers with loans.update) --}}
                    @if ($isLoanReviewMode)
                        <button wire:click="openService('loan-review')"
                            class="group bg-gradient-to-br from-indigo-50 to-violet-100 hover:from-indigo-100 hover:to-violet-200 rounded-xl p-5 text-left transition-all hover:shadow-lg relative">
                            @if ($pendingLoanReviews > 0)
                                <span
                                    class="absolute top-3 right-3 bg-red-500 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center">
                                    {{ $pendingLoanReviews }}
                                </span>
                            @endif
                            <div
                                class="w-12 h-12 bg-indigo-600 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                <i class="fas fa-file-alt text-white text-xl"></i>
                            </div>
                            <h3 class="text-base font-bold text-gray-900 mb-1">Loan Applications</h3>
                            <p class="text-xs text-gray-600">Review and endorse pending loan applications</p>
                        </button>
                    @endif

                    {{-- Make Payment --}}
                    <button wire:click="openService('payment')"
                        class="group bg-gradient-to-br from-blue-50 to-cyan-100 hover:from-blue-100 hover:to-cyan-200 rounded-xl p-5 text-left transition-all hover:shadow-lg">
                        <div
                            class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-gray-900 mb-1">Make Payment</h3>
                        <p class="text-xs text-gray-600">Pay your active loan installments</p>
                    </button>
                </div>
            @endif
        </div>

        {{-- Modals --}}
        @include('livewire.content.services.deposit-modal')
        @include('livewire.content.services.withdrawal-modal')
        @include('livewire.content.services.loan-modal')
        @include('livewire.content.services.payment-modal')
        @include('livewire.content.services.approve-modal')
        @include('livewire.content.services.loan-review-modal')
    </div>
