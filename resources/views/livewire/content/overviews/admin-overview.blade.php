<div class="space-y-6">

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- HERO HEADER                                                 --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-emerald-700 via-green-700 to-emerald-800 rounded-2xl shadow-xl p-8 text-white">
        <div class="absolute -top-10 -right-10 w-52 h-52 bg-white/5 rounded-full pointer-events-none"></div>
        <div class="absolute -bottom-14 -left-8 w-44 h-44 bg-white/5 rounded-full pointer-events-none"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <p class="text-emerald-200 text-sm font-medium uppercase tracking-widest mb-1">System Administrator</p>
                <h1 class="text-3xl font-extrabold leading-tight">{{ $greeting }}, {{ Auth::user()->personalData?->first_name ?? 'Admin' }}</h1>
                <p class="mt-1 text-emerald-200">Cooperative-wide overview &mdash; {{ $kpis['period_label'] ?? now()->format('F Y') }}</p>
            </div>
            <div class="sm:text-right shrink-0">
                <p class="text-emerald-200 text-xs uppercase tracking-wide">Today</p>
                <p class="text-2xl font-bold">{{ now()->format('M d, Y') }}</p>
                <p class="text-emerald-200 text-sm">{{ now()->format('l') }}</p>
            </div>
        </div>
        @php $totalAlerts = (int)($kpis['pending_transactions'] ?? 0) + (int)($kpis['pending_loans'] ?? 0) + (int)($kpis['loans_overdue'] ?? 0); @endphp
        @if ($totalAlerts > 0)
            <div class="relative mt-5 flex flex-wrap gap-3">
                @if (($kpis['pending_transactions'] ?? 0) > 0)
                    <span class="inline-flex items-center gap-1.5 bg-amber-400/20 border border-amber-300/40 text-amber-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                        {{ $kpis['pending_transactions'] }} pending transaction{{ $kpis['pending_transactions'] != 1 ? 's' : '' }}
                    </span>
                @endif
                @if (($kpis['pending_loans'] ?? 0) > 0)
                    <span class="inline-flex items-center gap-1.5 bg-cyan-400/20 border border-cyan-300/40 text-cyan-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 00-2 2v4a2 2 0 002 2h12a2 2 0 002-2v-4a2 2 0 00-2-2H6zm6 5a1 1 0 110-2 1 1 0 010 2z"/></svg>
                        {{ $kpis['pending_loans'] }} loan application{{ $kpis['pending_loans'] != 1 ? 's' : '' }}
                    </span>
                @endif
                @if (($kpis['loans_overdue'] ?? 0) > 0)
                    <span class="inline-flex items-center gap-1.5 bg-red-400/20 border border-red-300/40 text-red-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        {{ $kpis['loans_overdue'] }} overdue loan{{ $kpis['loans_overdue'] != 1 ? 's' : '' }}
                    </span>
                @endif
            </div>
        @endif
    </div>

    {{-- KPI Row 1 --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($kpis['members_total'] ?? 0) }}</p>
                    <p class="text-xs text-gray-500">Total Members</p>
                </div>
                <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full self-start">+{{ number_format($kpis['members_new_month'] ?? 0) }} mo.</span>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-teal-50 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-teal-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($kpis['staff_total'] ?? 0) }}</p>
                    <p class="text-xs text-gray-500">Officers &amp; Admins</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-2xl font-bold text-gray-900">₱{{ number_format($kpis['savings_total'] ?? 0, 2) }}</p>
                    <p class="text-xs text-gray-500">Total Savings</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-cyan-50 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-cyan-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-2xl font-bold text-gray-900">₱{{ number_format($kpis['share_capital_total'] ?? 0, 2) }}</p>
                    <p class="text-xs text-gray-500">Share Capital</p>
                </div>
            </div>
        </div>
    </div>

    {{-- KPI Row 2 --}}
    @php $net = (float)($kpis['month_net'] ?? 0); $netPos = $net >= 0; $totalAlerts2 = (int)($kpis['pending_transactions'] ?? 0) + (int)($kpis['pending_loans'] ?? 0) + (int)($kpis['loans_overdue'] ?? 0); @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 {{ $netPos ? 'bg-emerald-50' : 'bg-red-50' }} rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 {{ $netPos ? 'text-emerald-600' : 'text-red-600' }}" fill="currentColor" viewBox="0 0 20 20">
                        @if($netPos)<path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd"/>
                        @else<path fill-rule="evenodd" d="M12 13a1 1 0 100 2h5a1 1 0 001-1V9a1 1 0 10-2 0v2.586l-4.293-4.293a1 1 0 00-1.414 0L8 9.586 3.707 5.293a1 1 0 00-1.414 1.414l5 5a1 1 0 001.414 0L11 9.414 14.586 13H12z" clip-rule="evenodd"/>
                        @endif
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-2xl font-bold {{ $netPos ? 'text-emerald-700' : 'text-red-600' }}">{{ $netPos ? '' : '-' }}₱{{ number_format(abs($net), 2) }}</p>
                    <p class="text-xs text-gray-500">Monthly Net for {{ now()->format('M Y') }}</p>
                </div>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500 space-y-0.5">
                <div class="flex justify-between"><span>Deposits</span><span class="font-medium text-gray-700">₱{{ number_format($kpis['month_deposits'] ?? 0, 0) }}</span></div>
                <div class="flex justify-between"><span>Withdrawals</span><span class="font-medium text-gray-700">₱{{ number_format($kpis['month_withdrawals'] ?? 0, 0) }}</span></div>
                <div class="flex justify-between"><span>Expenses</span><span class="font-medium text-gray-700">₱{{ number_format($kpis['month_expenses'] ?? 0, 0) }}</span></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm2 10a1 1 0 10-2 0v3a1 1 0 102 0v-3zm2-3a1 1 0 011 1v5a1 1 0 11-2 0v-5a1 1 0 011-1zm4-1a1 1 0 10-2 0v7a1 1 0 102 0V8z" clip-rule="evenodd"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($kpis['loans_active'] ?? 0) }}</p>
                    <p class="text-xs text-gray-500">Active Loans</p>
                </div>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500">
                <div class="flex justify-between"><span>Outstanding</span><span class="font-medium text-gray-700">₱{{ number_format($kpis['loans_outstanding'] ?? 0, 0) }}</span></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 {{ ($kpis['loans_overdue'] ?? 0) > 0 ? 'border-red-200' : '' }}">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 {{ ($kpis['loans_overdue'] ?? 0) > 0 ? 'bg-red-50' : 'bg-gray-50' }} rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 {{ ($kpis['loans_overdue'] ?? 0) > 0 ? 'text-red-500' : 'text-gray-400' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-2xl font-bold {{ ($kpis['loans_overdue'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ number_format($kpis['loans_overdue'] ?? 0) }}</p>
                    <p class="text-xs text-gray-500">Overdue Loans</p>
                </div>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500">
                <div class="flex justify-between"><span>Due in 14 days</span><span class="font-medium text-amber-600">{{ number_format($kpis['loans_due_soon'] ?? 0) }}</span></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 {{ $totalAlerts2 > 0 ? 'border-amber-200' : '' }}">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 {{ $totalAlerts2 > 0 ? 'bg-amber-50' : 'bg-gray-50' }} rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 {{ $totalAlerts2 > 0 ? 'text-amber-600' : 'text-gray-400' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-2xl font-bold {{ $totalAlerts2 > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ number_format($totalAlerts2) }}</p>
                    <p class="text-xs text-gray-500">Pending Actions</p>
                </div>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500 space-y-0.5">
                <div class="flex justify-between"><span>Transactions</span><span class="font-medium text-gray-700">{{ number_format($kpis['pending_transactions'] ?? 0) }}</span></div>
                <div class="flex justify-between"><span>Loan apps</span><span class="font-medium text-gray-700">{{ number_format($kpis['pending_loans'] ?? 0) }}</span></div>
            </div>
        </div>
    </div>

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
        <div class="xl:col-span-3 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Monthly Cash Flow (Last 6 Months)</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Inflows vs. outflows by month.</p>
                </div>
            </div>
            <div class="space-y-4">
                @forelse ($monthlySeries as $m)
                    @php
                        $mNet  = (float)($m['net'] ?? 0);
                        $mDep  = (float)($m['deposits'] ?? 0);
                        $mOut  = (float)($m['withdrawals'] ?? 0) + (float)($m['expenses'] ?? 0);
                        $max   = max(1, (float)($monthlyMaxValue ?? 1));
                        $wDep  = min(100, ($mDep / $max) * 100);
                        $wOut  = min(100, ($mOut / $max) * 100);
                        $nCls  = $mNet >= 0 ? 'text-emerald-600' : 'text-red-500';
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-medium text-gray-700 w-16 shrink-0">{{ $m['label'] ?? '' }}</span>
                            <span class="{{ $nCls }} font-semibold ml-auto">{{ $mNet >= 0 ? '+' : '' }}₱{{ number_format($mNet, 0) }}</span>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400 w-14 shrink-0">In</span>
                                <div class="flex-1 bg-gray-100 rounded-full h-1.5"><div class="h-1.5 rounded-full bg-emerald-400" style="width: {{ $wDep }}%"></div></div>
                                <span class="text-xs text-gray-500 w-20 text-right">₱{{ number_format($mDep, 0) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400 w-14 shrink-0">Out</span>
                                <div class="flex-1 bg-gray-100 rounded-full h-1.5"><div class="h-1.5 rounded-full bg-rose-400" style="width: {{ $wOut }}%"></div></div>
                                <span class="text-xs text-gray-500 w-20 text-right">₱{{ number_format($mOut, 0) }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-6">No financial data yet.</p>
                @endforelse
            </div>
            <div class="mt-5 pt-4 border-t border-gray-100 flex flex-wrap gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5"><span class="w-3 h-1.5 rounded-full bg-emerald-400 inline-block"></span> Inflows</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-1.5 rounded-full bg-rose-400 inline-block"></span> Outflows</span>
            </div>
        </div>
        <div class="xl:col-span-2 space-y-4">
            {{-- Loan Distribution --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                @php $loanTotal = array_sum(array_column($loanStatusSeries, 'count')); @endphp
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">Loan Distribution</h2>
                        <p class="text-xs text-gray-500">All loans by status</p>
                    </div>
                    <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-lg font-medium">{{ number_format($loanTotal) }} total</span>
                </div>
                @if ($loanTotal > 0)
                    <div class="flex w-full rounded-full overflow-hidden h-4 mb-4 bg-gray-100">
                        @foreach ($loanStatusSeries as $row)
                            @if ($row['count'] > 0)
                                <div class="{{ $row['color'] }} h-full transition-all" style="width: {{ $row['percent'] }}%" title="{{ $row['label'] }}: {{ $row['count'] }}"></div>
                            @endif
                        @endforeach
                    </div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                        @foreach ($loanStatusSeries as $row)
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="w-2.5 h-2.5 rounded-sm {{ $row['color'] }} shrink-0"></span>
                                    <span class="text-xs text-gray-600 truncate">{{ $row['label'] }}</span>
                                </div>
                                <span class="text-xs font-semibold text-gray-800 shrink-0">{{ number_format($row['count']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400 text-center py-4">No loans yet.</p>
                @endif
            </div>
            {{-- Loan Portfolio --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-bold text-gray-900 mb-3">Loan Portfolio</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between"><span class="text-gray-500">Active</span><span class="font-semibold text-gray-900">{{ number_format($kpis['loans_active'] ?? 0) }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-gray-500">Outstanding</span><span class="font-semibold text-gray-900">₱{{ number_format($kpis['loans_outstanding'] ?? 0, 0) }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-gray-500">Due &lt; 14 days</span><span class="font-semibold text-amber-600">{{ number_format($kpis['loans_due_soon'] ?? 0) }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-gray-500">Overdue</span><span class="font-semibold text-red-600">{{ number_format($kpis['loans_overdue'] ?? 0) }}</span></div>
                </div>
                @if (count($loanStatusCounts) > 0)
                    <div class="mt-3 pt-3 border-t border-gray-100 grid grid-cols-2 gap-1.5">
                        @foreach ($loanStatusCounts as $status => $count)
                            <div class="flex items-center justify-between bg-gray-50 rounded-lg px-2.5 py-1.5">
                                <span class="text-xs text-gray-500 truncate">{{ ucwords(str_replace(['_', '-'], ' ', $status)) }}</span>
                                <span class="text-xs font-bold text-gray-800 ml-1">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Bottom Panels --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Pending Transaction Approvals</h2>
                    <p class="text-xs text-gray-500">Items waiting for action</p>
                </div>
                @if (($kpis['pending_transactions'] ?? 0) > 0)
                    <span class="text-xs font-semibold text-amber-700 bg-amber-100 px-2.5 py-1 rounded-full">{{ $kpis['pending_transactions'] }} pending</span>
                @endif
            </div>
            <div class="p-6">
                @if ($pendingTransactions->count() > 0)
                    <div class="overflow-x-auto -mx-1">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                    <th class="pb-3 pr-4 pl-1">Txn #</th>
                                    <th class="pb-3 pr-4">Type</th>
                                    <th class="pb-3 pr-4">Member</th>
                                    <th class="pb-3 pr-4">Amount</th>
                                    <th class="pb-3 pr-1">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($pendingTransactions as $txn)
                                    @php
                                        $type = ucwords(str_replace(['_', '-'], ' ', $txn->transaction_type));
                                        $badge = match ($txn->transaction_type) {
                                            'deposit'            => 'bg-emerald-100 text-emerald-700',
                                            'withdrawal'         => 'bg-amber-100 text-amber-700',
                                            'loan_payment'       => 'bg-blue-100 text-blue-700',
                                            'loan_disbursement'  => 'bg-cyan-100 text-cyan-700',
                                            default              => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="py-3 pr-4 pl-1 font-mono text-xs text-gray-700">{{ $txn->transaction_number }}</td>
                                        <td class="py-3 pr-4"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $badge }}">{{ $type }}</span></td>
                                        <td class="py-3 pr-4 font-medium text-gray-900">{{ $txn->account?->user?->personalData?->first_name ?? '—' }} {{ $txn->account?->user?->personalData?->last_name ?? '' }}</td>
                                        <td class="py-3 pr-4 font-bold text-gray-900">₱{{ number_format($txn->amount, 2) }}</td>
                                        <td class="py-3 pr-1 text-xs text-gray-400">{{ $txn->created_at?->format('M d') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center mb-3">
                            <svg class="w-6 h-6 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </div>
                        <p class="text-sm font-medium text-gray-700">All clear!</p>
                        <p class="text-xs text-gray-400 mt-0.5">No pending transactions.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-5">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-900">Recent Announcements</h3></div>
                <div class="p-5 space-y-3.5">
                    @forelse ($recentAnnouncements as $a)
                        <div class="flex gap-3">
                            <div class="w-1 shrink-0 rounded-full bg-emerald-400 self-stretch"></div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $a->title }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ \Illuminate\Support\Str::limit($a->content, 100) }}</p>
                                <p class="text-xs text-gray-400 mt-1">{{ $a->created_at?->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 text-center py-4">No announcements yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-900">Recent Expenses</h3></div>
                <div class="p-5">
                    @if ($recentExpenses->count() > 0)
                        <div class="space-y-3">
                            @foreach ($recentExpenses as $e)
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $e->category }}</p>
                                        <p class="text-xs text-gray-400">{{ $e->expense_date?->format('M d, Y') }}</p>
                                    </div>
                                    <span class="text-sm font-bold text-gray-800 shrink-0">₱{{ number_format($e->amount, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 text-center py-4">No expenses recorded.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
