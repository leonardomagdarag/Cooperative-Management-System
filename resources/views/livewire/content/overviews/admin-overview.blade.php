<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-linear-to-r from-emerald-600 to-cyan-600 rounded-2xl shadow-xl p-8 text-white">
        <div class="flex items-start justify-between gap-6">
            <div>
                <h1 class="text-3xl font-bold mb-2">Cooperative Overview</h1>
                <p class="text-emerald-50 text-lg">High-level snapshot for
                    {{ $kpis['period_label'] ?? now()->format('M Y') }}</p>
            </div>
            <div class="text-right">
                <p class="text-emerald-50 text-sm">Today</p>
                <p class="text-2xl font-bold">{{ now()->format('M d, Y') }}</p>
            </div>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-xs font-semibold text-gray-500 tracking-wide">MEMBERS</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($kpis['members_total'] ?? 0) }}</p>
            <div class="mt-3 text-sm text-gray-600 flex items-center justify-between">
                <span>New this month</span>
                <span
                    class="font-semibold text-emerald-700">+{{ number_format($kpis['members_new_month'] ?? 0) }}</span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-xs font-semibold text-gray-500 tracking-wide">STAFF</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($kpis['staff_total'] ?? 0) }}</p>
            <p class="mt-3 text-sm text-gray-600">Admins, officers, and management</p>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-xs font-semibold text-gray-500 tracking-wide">TOTAL SAVINGS</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">₱{{ number_format($kpis['savings_total'] ?? 0, 2) }}</p>
            <p class="mt-3 text-sm text-gray-600">Current balances (all accounts)</p>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-xs font-semibold text-gray-500 tracking-wide">SHARE CAPITAL</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">₱{{ number_format($kpis['share_capital_total'] ?? 0, 2) }}
            </p>
            <p class="mt-3 text-sm text-gray-600">Total member investment</p>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-xs font-semibold text-gray-500 tracking-wide">NET (THIS MONTH)</p>
            @php
                $net = (float) ($kpis['month_net'] ?? 0);
                $netClass = $net >= 0 ? 'text-emerald-700' : 'text-red-700';
            @endphp
            <p class="text-3xl font-bold mt-2 {{ $netClass }}">₱{{ number_format($net, 2) }}</p>
            <div class="mt-3 text-xs text-gray-600 space-y-1">
                <div class="flex items-center justify-between"><span>Deposits</span><span
                        class="font-medium">₱{{ number_format($kpis['month_deposits'] ?? 0, 2) }}</span></div>
                <div class="flex items-center justify-between"><span>Withdrawals</span><span
                        class="font-medium">₱{{ number_format($kpis['month_withdrawals'] ?? 0, 2) }}</span></div>
                <div class="flex items-center justify-between"><span>Expenses</span><span
                        class="font-medium">₱{{ number_format($kpis['month_expenses'] ?? 0, 2) }}</span></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6">
            <p class="text-xs font-semibold text-gray-500 tracking-wide">PENDING</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ number_format($kpis['pending_transactions'] ?? 0) }}
            </p>
            <div class="mt-3 text-sm text-gray-600 flex items-center justify-between">
                <span>Transactions</span>
                <span class="font-semibold">{{ number_format($kpis['pending_transactions'] ?? 0) }}</span>
            </div>
            <div class="mt-2 text-sm text-gray-600 flex items-center justify-between">
                <span>Loan requests</span>
                <span class="font-semibold">{{ number_format($kpis['pending_loans'] ?? 0) }}</span>
            </div>
        </div>
    </div>

    {{-- Charts --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        {{-- Net trend --}}
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-start justify-between gap-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Net Trend (Last 6 Months)</h2>
                    <p class="text-sm text-gray-600">Net is computed from completed transactions and expenses.</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500">Current month net</p>
                    <p class="text-sm font-semibold text-gray-900">
                        ₱{{ number_format((float) ($kpis['month_net'] ?? 0), 2) }}</p>
                </div>
            </div>

            <div class="mt-6 space-y-3">
                @forelse ($monthlySeries as $m)
                    @php
                        $net = (float) ($m['net'] ?? 0);
                        $abs = abs($net);
                        $max = (float) ($monthlyMaxAbsNet ?? 0);
                        $width = $max > 0 ? min(100, ($abs / $max) * 100) : 0;
                        $bar = $net >= 0 ? 'bg-emerald-500' : 'bg-red-500';
                    @endphp
                    <div class="flex items-center gap-3">
                        <div class="w-20 shrink-0 text-xs text-gray-600">{{ $m['label'] ?? '' }}</div>
                        <div class="flex-1">
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="h-2 rounded-full {{ $bar }}" style="width: {{ $width }}%">
                                </div>
                            </div>
                        </div>
                        <div class="w-28 shrink-0 text-right text-xs font-semibold text-gray-900">
                            ₱{{ number_format($net, 2) }}</div>
                    </div>
                @empty
                    <p class="text-sm text-gray-600">No trend data yet.</p>
                @endforelse
            </div>

            <div class="mt-5 grid grid-cols-3 gap-3 text-xs">
                <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-emerald-500"></span><span
                        class="text-gray-600">Positive net</span></div>
                <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm bg-red-500"></span><span
                        class="text-gray-600">Negative net</span></div>
                <div class="text-right text-gray-500">Scale: max month</div>
            </div>
        </div>

        {{-- Loan status distribution --}}
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-start justify-between gap-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Loan Status Distribution</h2>
                    <p class="text-sm text-gray-600">Share of all loans by current status.</p>
                </div>
                @php
                    $loanTotal = 0;
                    foreach ($loanStatusSeries as $row) {
                        $loanTotal += (int) ($row['count'] ?? 0);
                    }
                @endphp
                <div class="text-right">
                    <p class="text-xs text-gray-500">Total loans</p>
                    <p class="text-sm font-semibold text-gray-900">{{ number_format($loanTotal) }}</p>
                </div>
            </div>

            <div class="mt-6 space-y-3">
                @forelse ($loanStatusSeries as $row)
                    @php
                        $percent = (float) ($row['percent'] ?? 0);
                        $count = (int) ($row['count'] ?? 0);
                        $color = (string) ($row['color'] ?? 'bg-slate-500');
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-xs text-gray-600">
                            <span class="font-medium text-gray-800">{{ $row['label'] ?? 'Unknown' }}</span>
                            <span>{{ number_format($count) }} ({{ number_format($percent, 1) }}%)</span>
                        </div>
                        <div class="mt-1 w-full bg-gray-100 rounded-full h-2">
                            <div class="h-2 rounded-full {{ $color }}"
                                style="width: {{ min(100, $percent) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-600">No loans yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Main panels --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Pending approvals --}}
        <div class="xl:col-span-2 bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-xl font-bold text-gray-900">Pending Transaction Approvals</h2>
                <p class="text-sm text-gray-600">Most recent items waiting for action</p>
            </div>

            <div class="p-6">
                @if ($pendingTransactions->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="py-2 pr-4">Txn #</th>
                                    <th class="py-2 pr-4">Type</th>
                                    <th class="py-2 pr-4">Member</th>
                                    <th class="py-2 pr-4">Amount</th>
                                    <th class="py-2 pr-4">Submitted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($pendingTransactions as $txn)
                                    <tr class="text-sm">
                                        <td class="py-3 pr-4 font-mono text-gray-900">{{ $txn->transaction_number }}
                                        </td>
                                        <td class="py-3 pr-4">
                                            @php
                                                $type = str_replace(
                                                    '_',
                                                    ' ',
                                                    str_replace('-', ' ', $txn->transaction_type),
                                                );
                                                $type = ucwords($type);
                                                $badge = match ($txn->transaction_type) {
                                                    'deposit' => 'bg-emerald-100 text-emerald-800',
                                                    'withdrawal' => 'bg-amber-100 text-amber-800',
                                                    'loan_payment' => 'bg-cyan-100 text-cyan-800',
                                                    default => 'bg-gray-100 text-gray-800',
                                                };
                                            @endphp
                                            <span
                                                class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $badge }}">{{ $type }}</span>
                                        </td>
                                        <td class="py-3 pr-4 text-gray-900">
                                            {{ $txn->account?->user?->personalData?->first_name ?? '—' }}
                                            {{ $txn->account?->user?->personalData?->last_name ?? '' }}
                                        </td>
                                        <td class="py-3 pr-4 font-semibold text-gray-900">
                                            ₱{{ number_format($txn->amount, 2) }}</td>
                                        <td class="py-3 pr-4 text-gray-600">{{ $txn->created_at?->format('M d, Y') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12">
                        <p class="text-gray-600 font-medium">No pending transactions</p>
                        <p class="text-sm text-gray-500">You’re all caught up.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right column: Loans + Announcements + Expenses --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-900">Loan Portfolio</h2>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Active loans</span>
                        <span
                            class="font-semibold text-gray-900">{{ number_format($kpis['loans_active'] ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Outstanding balance</span>
                        <span
                            class="font-semibold text-gray-900">₱{{ number_format($kpis['loans_outstanding'] ?? 0, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Due in 14 days</span>
                        <span
                            class="font-semibold text-gray-900">{{ number_format($kpis['loans_due_soon'] ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Overdue</span>
                        <span
                            class="font-semibold text-red-700">{{ number_format($kpis['loans_overdue'] ?? 0) }}</span>
                    </div>
                </div>

                <div class="mt-6">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Status Breakdown</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        @foreach ($loanStatusCounts as $status => $count)
                            <div class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                                <span
                                    class="text-xs text-gray-600">{{ ucwords(str_replace(['_', '-'], ' ', $status)) }}</span>
                                <span class="text-xs font-semibold text-gray-900">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-lg font-bold text-gray-900">Recent Announcements</h2>
                </div>
                <div class="p-6 space-y-4">
                    @forelse ($recentAnnouncements as $a)
                        <div class="border-l-4 border-cyan-400 pl-4">
                            <p class="font-semibold text-gray-900 text-sm">{{ $a->title }}</p>
                            <p class="text-xs text-gray-600 mt-1">
                                {{ \Illuminate\Support\Str::limit($a->content, 120) }}</p>
                            <p class="text-xs text-gray-500 mt-2">{{ $a->created_at?->diffForHumans() }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-600">No announcements yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-lg font-bold text-gray-900">Recent Expenses</h2>
                </div>
                <div class="p-6">
                    @if ($recentExpenses->count() > 0)
                        <div class="space-y-3">
                            @foreach ($recentExpenses as $e)
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $e->category }}</p>
                                        <p class="text-xs text-gray-500">{{ $e->expense_date?->format('M d, Y') }}</p>
                                    </div>
                                    <div class="text-sm font-bold text-gray-900">₱{{ number_format($e->amount, 2) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-600">No expenses recorded.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
