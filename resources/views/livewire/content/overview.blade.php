<div class="space-y-6">
    {{-- Welcome Section --}}
    <div class="bg-gradient-to-r from-cyan-500 to-emerald-500 rounded-2xl shadow-xl p-8 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">Welcome back,
                    {{ Auth::user()->personalData?->first_name ?? 'Member' }}! 👋</h1>
                <p class="text-cyan-50 text-lg">Here's your financial overview for today</p>
            </div>
            <div class="text-right">
                <p class="text-cyan-50 text-sm">Member ID</p>
                <p class="text-2xl font-bold">{{ Auth::user()->user_number }}</p>
            </div>
        </div>
    </div>

    {{-- Account Balance Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Savings Account Card --}}
        <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow overflow-hidden">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-white/20 p-3 rounded-lg">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                            <path fill-rule="evenodd"
                                d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <span class="text-white/80 text-sm font-medium">SAVINGS</span>
                </div>
                <div>
                    <p class="text-white/90 text-sm mb-1">Available Balance</p>
                    <p class="text-3xl font-bold text-white">₱{{ number_format($totalSavings, 2) }}</p>
                </div>
            </div>
            @if ($savingsAccount)
                <div class="p-4 bg-gray-50">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">Account No.</span>
                        <span class="font-mono font-semibold text-gray-900">{{ $savingsAccount->account_number }}</span>
                    </div>
                </div>
            @else
                <div class="p-4 bg-gray-50">
                    <p class="text-sm text-gray-500 text-center">No savings account yet</p>
                </div>
            @endif
        </div>

        {{-- Share Capital Card --}}
        <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow overflow-hidden">
            <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-white/20 p-3 rounded-lg">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <span class="text-white/80 text-sm font-medium">SHARE CAPITAL</span>
                </div>
                <div>
                    <p class="text-white/90 text-sm mb-1">Total Investment</p>
                    <p class="text-3xl font-bold text-white">₱{{ number_format($totalShareCapital, 2) }}</p>
                </div>
            </div>
            @if ($shareCapitalAccount)
                <div class="p-4 bg-gray-50">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">Account No.</span>
                        <span
                            class="font-mono font-semibold text-gray-900">{{ $shareCapitalAccount->account_number }}</span>
                    </div>
                </div>
            @else
                <div class="p-4 bg-gray-50">
                    <p class="text-sm text-gray-500 text-center">No share capital yet</p>
                </div>
            @endif
        </div>

        {{-- Total Loan Balance Card --}}
        <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow overflow-hidden">
            <div class="bg-gradient-to-br from-orange-500 to-red-500 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-white/20 p-3 rounded-lg">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <span class="text-white/80 text-sm font-medium">TOTAL LOANS</span>
                </div>
                <div>
                    <p class="text-white/90 text-sm mb-1">Outstanding Balance</p>
                    <p class="text-3xl font-bold text-white">₱{{ number_format($totalLoanBalance, 2) }}</p>
                </div>
            </div>
            <div class="p-4 bg-gray-50">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">Active Loans</span>
                    <span class="font-semibold text-gray-900">{{ $activeLoans->count() }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Loans and Announcements Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Active Loans Section --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <svg class="w-6 h-6 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"
                            clip-rule="evenodd" />
                    </svg>
                    Active Loans
                </h2>
            </div>
            <div class="p-6">
                @if ($activeLoans && $activeLoans->count() > 0)
                    <div class="space-y-4">
                        @foreach ($activeLoans as $loan)
                            <div
                                class="bg-gradient-to-r from-gray-50 to-white border-2 border-gray-200 rounded-lg p-5 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-900">
                                            {{ ucwords(str_replace('_', ' ', $loan->loan_type)) }} Loan
                                        </h3>
                                        <p class="text-sm text-gray-500">Loan No: <span
                                                class="font-mono">{{ $loan->loan_number }}</span></p>
                                    </div>
                                    <span
                                        class="px-3 py-1 text-xs font-semibold rounded-full {{ $loan->loan_status === 'approved' || $loan->loan_status === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ ucfirst($loan->loan_status) }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <p class="text-xs text-gray-600 mb-1">Principal Amount</p>
                                        <p class="text-lg font-bold text-gray-900">
                                            ₱{{ number_format($loan->loan_amount, 2) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600 mb-1">Remaining Balance</p>
                                        <p class="text-lg font-bold text-red-600">
                                            ₱{{ number_format($loan->remainingBalance(), 2) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600 mb-1">Next Due Date</p>
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ $loan->due_date ? \Carbon\Carbon::parse($loan->due_date)->format('M d, Y') : 'N/A' }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Progress bar --}}
                                @php
                                    $totalPaid = $loan->payments->sum('amount');
                                    $totalAmount = $loan->loan_amount;
                                    $progress = $totalAmount > 0 ? ($totalPaid / $totalAmount) * 100 : 0;
                                @endphp
                                <div class="mt-4">
                                    <div class="flex justify-between text-xs text-gray-600 mb-1">
                                        <span>Payment Progress</span>
                                        <span>{{ number_format($progress, 1) }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-gradient-to-r from-cyan-500 to-emerald-500 h-2 rounded-full transition-all"
                                            style="width: {{ $progress }}%"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="w-20 h-20 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-600 mb-2">No Active Loans</h3>
                        <p class="text-gray-500">You currently have no active loans</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Announcements Section --}}
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <svg class="w-6 h-6 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
                    </svg>
                    Announcements
                </h2>
            </div>
            <div class="p-4 max-h-96 overflow-y-auto">
                @if ($announcements && $announcements->count() > 0)
                    <div class="space-y-3">
                        @foreach ($announcements as $announcement)
                            <div
                                class="bg-{{ $announcement['type'] === 'info' ? 'blue' : ($announcement['type'] === 'success' ? 'green' : 'amber') }}-50 border-l-4 border-{{ $announcement['type'] === 'info' ? 'blue' : ($announcement['type'] === 'success' ? 'green' : 'amber') }}-400 p-4 rounded-r-lg">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0">
                                        @if ($announcement['type'] === 'info')
                                            <svg class="w-5 h-5 text-blue-600" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        @elseif($announcement['type'] === 'success')
                                            <svg class="w-5 h-5 text-green-600" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        @else
                                            <svg class="w-5 h-5 text-amber-600" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-bold text-gray-900 mb-1">{{ $announcement['title'] }}
                                        </h4>
                                        <p class="text-xs text-gray-700">{{ $announcement['content'] }}</p>
                                        <p class="text-xs text-gray-500 mt-2">
                                            {{ \Carbon\Carbon::parse($announcement['date'])->diffForHumans() }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <p class="text-sm text-gray-500">No announcements</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-6 h-6 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                <path
                    d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1h4v1a2 2 0 11-4 0zM12 14c.015-.34.208-.646.477-.859a4 4 0 10-4.954 0c.27.213.462.519.476.859h4.002z" />
            </svg>
            Quick Actions
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <button
                class="flex flex-col items-center justify-center p-6 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg hover:shadow-md transition-all group">
                <div
                    class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8zM12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-gray-700">Make Deposit</span>
            </button>

            <button
                class="flex flex-col items-center justify-center p-6 bg-gradient-to-br from-green-50 to-green-100 rounded-lg hover:shadow-md transition-all group">
                <div
                    class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-gray-700">Apply for Loan</span>
            </button>

            <button
                class="flex flex-col items-center justify-center p-6 bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg hover:shadow-md transition-all group">
                <div
                    class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                        <path fill-rule="evenodd"
                            d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-gray-700">View Statements</span>
            </button>

            <button
                class="flex flex-col items-center justify-center p-6 bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg hover:shadow-md transition-all group">
                <div
                    class="w-12 h-12 bg-orange-500 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <span class="text-sm font-semibold text-gray-700">Contact Support</span>
            </button>
        </div>
    </div>
</div>
