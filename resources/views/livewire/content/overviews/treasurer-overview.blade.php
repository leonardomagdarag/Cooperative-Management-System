<div class="space-y-6">

    {{-- HERO --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-emerald-700 via-green-700 to-emerald-800 rounded-2xl shadow-xl p-8 text-white">
        <div class="absolute -top-8 -right-8 w-48 h-48 bg-white/5 rounded-full pointer-events-none"></div>
        <div class="absolute -bottom-12 -left-6 w-40 h-40 bg-white/5 rounded-full pointer-events-none"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <p class="text-emerald-200 text-sm font-medium uppercase tracking-widest mb-1">Treasurer</p>
                <h1 class="text-3xl font-extrabold leading-tight">{{ $greeting }}, {{ Auth::user()->personalData?->first_name ?? 'Treasurer' }}</h1>
                <p class="mt-1 text-emerald-200">Financial overview &mdash; {{ $kpis['period_label'] ?? now()->format('F Y') }}</p>
            </div>
            <div class="sm:text-right shrink-0">
                <p class="text-emerald-200 text-xs uppercase tracking-wide">Today</p>
                <p class="text-2xl font-bold">{{ now()->format('M d, Y') }}</p>
                <p class="text-emerald-200 text-sm">{{ now()->format('l') }}</p>
            </div>
        </div>
        @php $mNet = (float)($kpis['month_net'] ?? 0); @endphp
        <div class="relative mt-5 flex flex-wrap gap-3">
            @if ($mNet > 0)
                <span class="inline-flex items-center gap-1.5 bg-white/15 border border-white/20 text-emerald-50 text-xs font-semibold px-3 py-1.5 rounded-full">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd"/></svg>
                    Net +₱{{ number_format($mNet, 0) }} this month
                </span>
            @elseif ($mNet < 0)
                <span class="inline-flex items-center gap-1.5 bg-red-500/20 border border-red-300/30 text-red-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    Net -₱{{ number_format(abs($mNet), 0) }} this month
                </span>
            @endif
            @if (($kpis['pending_transactions'] ?? 0) > 0)
                <span class="inline-flex items-center gap-1.5 bg-amber-400/20 border border-amber-300/40 text-amber-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                    {{ $kpis['pending_transactions'] }} pending transaction{{ $kpis['pending_transactions'] != 1 ? 's' : '' }}
                </span>
            @endif
        </div>
    </div>

    {{-- KPI Row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-gray-900">₱{{ number_format($kpis['savings_total'] ?? 0, 2) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Total Savings</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-cyan-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-cyan-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-gray-900">₱{{ number_format($kpis['share_capital_total'] ?? 0, 2) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Share Capital</p>
        </div>
        @php $mNetPos = (float)($kpis['month_net'] ?? 0) >= 0; @endphp
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11 4a1 1 0 10-2 0v4a1 1 0 102 0V7zm-3 1a1 1 0 10-2 0v3a1 1 0 102 0V8zM8 9a1 1 0 00-2 0v2a1 1 0 102 0V9z" clip-rule="evenodd"/></svg>
                </div>
                <span class="text-xs text-gray-400">{{ now()->format('M Y') }}</span>
            </div>
            <p class="text-2xl font-extrabold text-gray-900">₱{{ number_format($kpis['month_deposits'] ?? 0, 2) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Deposits This Month</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 {{ $mNetPos ? 'bg-emerald-50' : 'bg-red-50' }} rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 {{ $mNetPos ? 'text-emerald-600' : 'text-red-500' }}" fill="currentColor" viewBox="0 0 20 20">
                        @if($mNetPos)<path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd"/>
                        @else<path fill-rule="evenodd" d="M12 13a1 1 0 100 2h5a1 1 0 001-1V9a1 1 0 10-2 0v2.586l-4.293-4.293a1 1 0 00-1.414 0L8 9.586 3.707 5.293a1 1 0 00-1.414 1.414l5 5a1 1 0 001.414 0L11 9.414 14.586 13H12z" clip-rule="evenodd"/>
                        @endif
                    </svg>
                </div>
                <span class="text-xs text-gray-400">{{ now()->format('M Y') }}</span>
            </div>
            <p class="text-2xl font-extrabold {{ $mNetPos ? 'text-emerald-700' : 'text-red-600' }}">
                {{ $mNetPos ? '+' : '-' }}₱{{ number_format(abs((float)($kpis['month_net'] ?? 0)), 2) }}
            </p>
            <p class="text-xs text-gray-500 mt-0.5">Monthly Net</p>
            <div class="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500 space-y-0.5">
                <div class="flex justify-between"><span>Withdrawals</span><span class="font-medium text-gray-700">₱{{ number_format($kpis['month_withdrawals'] ?? 0, 0) }}</span></div>
                <div class="flex justify-between"><span>Expenses</span><span class="font-medium text-gray-700">₱{{ number_format($kpis['month_expenses'] ?? 0, 0) }}</span></div>
            </div>
        </div>
    </div>

    {{-- Charts Row --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-base font-bold text-gray-900">Cash Flow Trend (Last 6 Months)</h2>
                <p class="text-xs text-gray-500 mt-0.5">Monthly deposits vs. total outflows (withdrawals + expenses).</p>
            </div>
            <div class="flex gap-4 shrink-0 text-right">
                <div>
                    <p class="text-xs text-gray-400">Total Assets</p>
                    <p class="text-sm font-bold text-gray-900">₱{{ number_format($kpis['total_assets'] ?? 0, 0) }}</p>
                </div>
            </div>
        </div>
        <div class="space-y-4">
            @forelse ($monthlySeries as $m)
                @php
                    $mD  = (float)($m['deposits'] ?? 0);
                    $mO  = (float)($m['withdrawals'] ?? 0) + (float)($m['expenses'] ?? 0);
                    $mEx = (float)($m['expenses'] ?? 0);
                    $max = max(1, (float)($monthlyMaxValue ?? 1));
                    $wD  = min(100, ($mD / $max) * 100);
                    $wO  = min(100, ($mO / $max) * 100);
                    $n   = (float)($m['net'] ?? 0);
                    $nC  = $n >= 0 ? 'text-emerald-600' : 'text-red-500';
                @endphp
                <div>
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-medium text-gray-700 w-16 shrink-0">{{ $m['label'] ?? '' }}</span>
                        <div class="flex gap-4 ml-auto">
                            <span class="text-gray-400">Exp: ₱{{ number_format($mEx, 0) }}</span>
                            <span class="{{ $nC }} font-semibold">{{ $n >= 0 ? '+' : '' }}₱{{ number_format($n, 0) }}</span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400 w-14 shrink-0">Deposits</span>
                            <div class="flex-1 bg-gray-100 rounded-full h-1.5"><div class="h-1.5 rounded-full bg-emerald-500" style="width: {{ $wD }}%"></div></div>
                            <span class="text-xs text-gray-500 w-20 text-right">₱{{ number_format($mD, 0) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400 w-14 shrink-0">Outflows</span>
                            <div class="flex-1 bg-gray-100 rounded-full h-1.5"><div class="h-1.5 rounded-full bg-rose-500" style="width: {{ $wO }}%"></div></div>
                            <span class="text-xs text-gray-500 w-20 text-right">₱{{ number_format($mO, 0) }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 text-center py-6">No financial data yet.</p>
            @endforelse
        </div>
        <div class="mt-5 pt-4 border-t border-gray-100 flex flex-wrap gap-4 text-xs text-gray-500">
            <span class="flex items-center gap-1.5"><span class="w-3 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Deposits</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-1.5 rounded-full bg-rose-500 inline-block"></span> Outflows</span>
        </div>
    </div>

    {{-- Bottom panels --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        {{-- Recent completed transactions --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-bold text-gray-900">Recent Transactions</h2>
                <p class="text-xs text-gray-500">Latest completed transactions</p>
            </div>
            <div class="p-6">
                @if ($recentTransactions->count() > 0)
                    <div class="space-y-2.5">
                        @foreach ($recentTransactions as $txn)
                            @php
                                $type = ucwords(str_replace(['_', '-'], ' ', $txn->transaction_type));
                                $badge = match ($txn->transaction_type) {
                                    'deposit'            => 'bg-emerald-100 text-emerald-700',
                                    'withdrawal'         => 'bg-amber-100 text-amber-700',
                                    'loan_payment'       => 'bg-blue-100 text-blue-700',
                                    'loan_disbursement'  => 'bg-cyan-100 text-cyan-700',
                                    default              => 'bg-gray-100 text-gray-600',
                                };
                                $amtCls = in_array($txn->transaction_type, ['deposit', 'loan_payment']) ? 'text-emerald-700' : 'text-gray-900';
                            @endphp
                            <div class="flex items-center justify-between gap-3 py-2 border-b border-gray-50 last:border-0">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $badge }} shrink-0">{{ $type }}</span>
                                    <span class="text-sm text-gray-700 truncate">
                                        {{ $txn->account?->user?->personalData?->first_name ?? '—' }} {{ $txn->account?->user?->personalData?->last_name ?? '' }}
                                    </span>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-bold {{ $amtCls }}">₱{{ number_format($txn->amount, 2) }}</p>
                                    <p class="text-xs text-gray-400">{{ $txn->created_at?->format('M d') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400 text-center py-8">No transactions recorded yet.</p>
                @endif
            </div>
        </div>

        {{-- Expenses + Announcements --}}
        <div class="space-y-5">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900">Recent Expenses</h3>
                    <span class="text-xs text-gray-400">{{ now()->format('M Y') }}</span>
                </div>
                <div class="p-5">
                    @if ($recentExpenses->count() > 0)
                        <div class="space-y-3">
                            @foreach ($recentExpenses as $e)
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $e->category }}</p>
                                        <p class="text-xs text-gray-400">{{ $e->expense_date?->format('M d, Y') }}</p>
                                    </div>
                                    <span class="text-sm font-bold text-red-600 shrink-0">₱{{ number_format($e->amount, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 text-center py-4">No expenses recorded.</p>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Announcements</h3>
                </div>
                <div class="p-5 space-y-3.5">
                    @forelse ($recentAnnouncements as $a)
                        <div class="flex gap-3">
                            <div class="w-1 shrink-0 rounded-full bg-emerald-400 self-stretch"></div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $a->title }}</p>
                                <p class="text-xs text-gray-400 mt-1">{{ $a->created_at?->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 text-center py-4">No announcements.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
