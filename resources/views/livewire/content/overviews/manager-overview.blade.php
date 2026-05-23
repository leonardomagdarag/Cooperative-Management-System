<div class="space-y-6">

    {{-- HERO --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-blue-700 via-blue-600 to-sky-700 rounded-2xl shadow-xl p-8 text-white">
        <div class="absolute -top-8 -right-8 w-48 h-48 bg-white/5 rounded-full pointer-events-none"></div>
        <div class="absolute -bottom-12 -left-6 w-40 h-40 bg-white/5 rounded-full pointer-events-none"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <p class="text-blue-200 text-sm font-medium uppercase tracking-widest mb-1">Manager</p>
                <h1 class="text-3xl font-extrabold leading-tight">{{ $greeting }}, {{ Auth::user()->personalData?->first_name ?? 'Manager' }}</h1>
                <p class="mt-1 text-blue-200">Operations overview &mdash; {{ $kpis['period_label'] ?? now()->format('F Y') }}</p>
            </div>
            <div class="sm:text-right shrink-0">
                <p class="text-blue-200 text-xs uppercase tracking-wide">Today</p>
                <p class="text-2xl font-bold">{{ now()->format('M d, Y') }}</p>
                <p class="text-blue-200 text-sm">{{ now()->format('l') }}</p>
            </div>
        </div>
        @php $alerts = (int)($kpis['pending_transactions'] ?? 0) + (int)($kpis['loans_pending'] ?? 0) + (int)($kpis['loans_overdue'] ?? 0); @endphp
        @if ($alerts > 0)
            <div class="relative mt-5 flex flex-wrap gap-3">
                @if (($kpis['pending_transactions'] ?? 0) > 0)
                    <span class="inline-flex items-center gap-1.5 bg-amber-400/20 border border-amber-300/40 text-amber-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                        {{ $kpis['pending_transactions'] }} pending transaction{{ $kpis['pending_transactions'] != 1 ? 's' : '' }}
                    </span>
                @endif
                @if (($kpis['loans_pending'] ?? 0) > 0)
                    <span class="inline-flex items-center gap-1.5 bg-sky-400/20 border border-sky-300/40 text-sky-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 00-2 2v4a2 2 0 002 2h12a2 2 0 002-2v-4a2 2 0 00-2-2H6zm6 5a1 1 0 110-2 1 1 0 010 2z"/></svg>
                        {{ $kpis['loans_pending'] }} loan application{{ $kpis['loans_pending'] != 1 ? 's' : '' }}
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

    {{-- KPI Row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
                </div>
                <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">+{{ number_format($kpis['members_new_month'] ?? 0) }} mo.</span>
            </div>
            <p class="text-2xl font-extrabold text-gray-900">{{ number_format($kpis['members_total'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Total Members</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-sky-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-sky-600" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-gray-900">₱{{ number_format($kpis['savings_total'] ?? 0, 2) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Total Savings</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm2 10a1 1 0 10-2 0v3a1 1 0 102 0v-3zm2-3a1 1 0 011 1v5a1 1 0 11-2 0v-5a1 1 0 011-1zm4-1a1 1 0 10-2 0v7a1 1 0 102 0V8z" clip-rule="evenodd"/></svg>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-gray-900">{{ number_format($kpis['loans_active'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Active Loans</p>
            <div class="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500">
                <div class="flex justify-between"><span>Overdue</span><span class="font-semibold text-red-600">{{ number_format($kpis['loans_overdue'] ?? 0) }}</span></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 {{ ($kpis['pending_transactions'] ?? 0) > 0 ? 'border-amber-200' : '' }}">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 {{ ($kpis['pending_transactions'] ?? 0) > 0 ? 'bg-amber-50' : 'bg-gray-50' }} rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 {{ ($kpis['pending_transactions'] ?? 0) > 0 ? 'text-amber-600' : 'text-gray-400' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                </div>
            </div>
            <p class="text-2xl font-extrabold {{ ($kpis['pending_transactions'] ?? 0) > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ number_format($kpis['pending_transactions'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Pending Approvals</p>
            <div class="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500">
                <div class="flex justify-between"><span>Loan apps</span><span class="font-semibold text-gray-700">{{ number_format($kpis['loans_pending'] ?? 0) }}</span></div>
            </div>
        </div>
    </div>

    {{-- Monthly Cash Flow --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        @php $mNet = (float)($kpis['month_net'] ?? 0); $mNetPos = $mNet >= 0; @endphp
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-base font-bold text-gray-900">Monthly Cash Flow (Last 6 Months)</h2>
                <p class="text-xs text-gray-500 mt-0.5">Deposits vs. withdrawals + expenses.</p>
            </div>
            <div class="flex gap-4 shrink-0">
                <div class="text-right">
                    <p class="text-xs text-gray-400">This month net</p>
                    <p class="text-sm font-bold {{ $mNetPos ? 'text-emerald-600' : 'text-red-500' }}">{{ $mNetPos ? '+' : '' }}₱{{ number_format($mNet, 0) }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-400">Deposits</p>
                    <p class="text-sm font-bold text-gray-900">₱{{ number_format($kpis['month_deposits'] ?? 0, 0) }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-400">Withdrawals</p>
                    <p class="text-sm font-bold text-gray-900">₱{{ number_format($kpis['month_withdrawals'] ?? 0, 0) }}</p>
                </div>
            </div>
        </div>
        <div class="space-y-4">
            @forelse ($monthlySeries as $m)
                @php
                    $mD  = (float)($m['deposits'] ?? 0);
                    $mO  = (float)($m['withdrawals'] ?? 0) + (float)($m['expenses'] ?? 0);
                    $max = max(1, (float)($monthlyMaxValue ?? 1));
                    $wD  = min(100, ($mD / $max) * 100);
                    $wO  = min(100, ($mO / $max) * 100);
                    $n   = (float)($m['net'] ?? 0);
                    $nC  = $n >= 0 ? 'text-emerald-600' : 'text-red-500';
                @endphp
                <div>
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-medium text-gray-700 w-16 shrink-0">{{ $m['label'] ?? '' }}</span>
                        <span class="{{ $nC }} font-semibold ml-auto">{{ $n >= 0 ? '+' : '' }}₱{{ number_format($n, 0) }}</span>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400 w-14 shrink-0">In</span>
                            <div class="flex-1 bg-gray-100 rounded-full h-1.5"><div class="h-1.5 rounded-full bg-blue-400" style="width: {{ $wD }}%"></div></div>
                            <span class="text-xs text-gray-500 w-20 text-right">₱{{ number_format($mD, 0) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400 w-14 shrink-0">Out</span>
                            <div class="flex-1 bg-gray-100 rounded-full h-1.5"><div class="h-1.5 rounded-full bg-rose-400" style="width: {{ $wO }}%"></div></div>
                            <span class="text-xs text-gray-500 w-20 text-right">₱{{ number_format($mO, 0) }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 text-center py-6">No financial data yet.</p>
            @endforelse
        </div>
        <div class="mt-5 pt-4 border-t border-gray-100 flex flex-wrap gap-4 text-xs text-gray-500">
            <span class="flex items-center gap-1.5"><span class="w-3 h-1.5 rounded-full bg-blue-400 inline-block"></span> Inflows</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-1.5 rounded-full bg-rose-400 inline-block"></span> Outflows</span>
        </div>
    </div>

    {{-- Bottom Panels --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Pending Transactions --}}
        <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Pending Transaction Approvals</h2>
                    <p class="text-xs text-gray-500">Items waiting for review</p>
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
                        <p class="text-xs text-gray-400">No pending transactions.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-5">
            {{-- Recent Members --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900">Newest Members</h3>
                    <span class="text-xs text-gray-400">+{{ number_format($kpis['members_new_month'] ?? 0) }} this month</span>
                </div>
                <div class="p-5 space-y-3">
                    @forelse ($recentMembers as $member)
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs shrink-0">
                                {{ strtoupper(substr($member->personalData?->first_name ?? 'M', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ $member->personalData?->first_name ?? '—' }} {{ $member->personalData?->last_name ?? '' }}
                                </p>
                                <p class="text-xs text-gray-400">{{ $member->created_at?->format('M d, Y') }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 text-center py-4">No members yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Recent Announcements --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Recent Announcements</h3>
                </div>
                <div class="p-5 space-y-3.5">
                    @forelse ($recentAnnouncements as $a)
                        <div class="flex gap-3">
                            <div class="w-1 shrink-0 rounded-full bg-blue-400 self-stretch"></div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $a->title }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ \Illuminate\Support\Str::limit($a->content, 90) }}</p>
                                <p class="text-xs text-gray-400 mt-1">{{ $a->created_at?->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 text-center py-4">No announcements yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
