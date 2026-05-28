<div class="space-y-6">

    {{-- HERO --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-amber-600 via-orange-600 to-amber-700 rounded-2xl shadow-xl p-8 text-white">
        <div class="absolute -top-8 -right-8 w-48 h-48 bg-white/5 rounded-full pointer-events-none"></div>
        <div class="absolute -bottom-12 -left-6 w-40 h-40 bg-white/5 rounded-full pointer-events-none"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <p class="text-amber-200 text-sm font-medium uppercase tracking-widest mb-1">Loan Officer</p>
                <h1 class="text-3xl font-extrabold leading-tight">{{ $greeting }}, {{ Auth::user()->personalData?->first_name ?? 'Officer' }}</h1>
                <p class="mt-1 text-amber-200">Loan portfolio overview &mdash; {{ now()->format('F Y') }}</p>
            </div>
            <div class="sm:text-right shrink-0">
                <p class="text-amber-200 text-xs uppercase tracking-wide">Today</p>
                <p class="text-2xl font-bold">{{ now()->format('M d, Y') }}</p>
                <p class="text-amber-200 text-sm">{{ now()->format('l') }}</p>
            </div>
        </div>
        @if (($kpis['loans_pending'] ?? 0) > 0 || ($kpis['loans_overdue'] ?? 0) > 0)
            <div class="relative mt-5 flex flex-wrap gap-3">
                @if (($kpis['loans_pending'] ?? 0) > 0)
                    <span class="inline-flex items-center gap-1.5 bg-white/15 border border-white/20 text-amber-50 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                        {{ $kpis['loans_pending'] }} application{{ $kpis['loans_pending'] != 1 ? 's' : '' }} awaiting review
                    </span>
                @endif
                @if (($kpis['loans_overdue'] ?? 0) > 0)
                    <span class="inline-flex items-center gap-1.5 bg-red-500/20 border border-red-300/30 text-red-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        {{ $kpis['loans_overdue'] }} overdue loan{{ $kpis['loans_overdue'] != 1 ? 's' : '' }}
                    </span>
                @endif
                @if (($kpis['loans_due_soon'] ?? 0) > 0)
                    <span class="inline-flex items-center gap-1.5 bg-white/15 border border-white/20 text-amber-50 text-xs font-semibold px-3 py-1.5 rounded-full">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
                        {{ $kpis['loans_due_soon'] }} due within 14 days
                    </span>
                @endif
            </div>
        @endif
    </div>

    {{-- KPI Row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 {{ ($kpis['loans_pending'] ?? 0) > 0 ? 'border-amber-200' : '' }}">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 {{ ($kpis['loans_pending'] ?? 0) > 0 ? 'bg-amber-50' : 'bg-gray-50' }} rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 {{ ($kpis['loans_pending'] ?? 0) > 0 ? 'text-amber-600' : 'text-gray-400' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                </div>
            </div>
            <p class="text-2xl font-extrabold {{ ($kpis['loans_pending'] ?? 0) > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ number_format($kpis['loans_pending'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Pending Applications</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm2 10a1 1 0 10-2 0v3a1 1 0 102 0v-3zm2-3a1 1 0 011 1v5a1 1 0 11-2 0v-5a1 1 0 011-1zm4-1a1 1 0 10-2 0v7a1 1 0 102 0V8z" clip-rule="evenodd"/></svg>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-gray-900">{{ number_format($kpis['loans_active'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Active Loans</p>
            <div class="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500">
                <div class="flex justify-between"><span>Outstanding</span><span class="font-medium text-gray-700">₱{{ number_format($kpis['loans_outstanding'] ?? 0, 0) }}</span></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 {{ ($kpis['loans_overdue'] ?? 0) > 0 ? 'border-red-200' : '' }}">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 {{ ($kpis['loans_overdue'] ?? 0) > 0 ? 'bg-red-50' : 'bg-gray-50' }} rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 {{ ($kpis['loans_overdue'] ?? 0) > 0 ? 'text-red-500' : 'text-gray-400' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                </div>
            </div>
            <p class="text-2xl font-extrabold {{ ($kpis['loans_overdue'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ number_format($kpis['loans_overdue'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Overdue Loans</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-gray-900">{{ number_format($kpis['loans_due_soon'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Due in 14 Days</p>
            <div class="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500">
                <div class="flex justify-between"><span>Total disbursed</span><span class="font-medium text-gray-700">₱{{ number_format($kpis['loans_disbursed'] ?? 0, 0) }}</span></div>
            </div>
        </div>
    </div>

    {{-- Main panels --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Pending loan applications --}}
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Pending Loan Applications</h2>
                        <p class="text-xs text-gray-500">Applications awaiting review or approval</p>
                    </div>
                    @if (($kpis['loans_pending'] ?? 0) > 0)
                        <span class="text-xs font-semibold text-amber-700 bg-amber-100 px-2.5 py-1 rounded-full">{{ $kpis['loans_pending'] }} pending</span>
                    @endif
                </div>
                <div class="p-6">
                    @if ($pendingLoans->count() > 0)
                        <div class="overflow-x-auto -mx-1">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                        <th class="pb-3 pr-4 pl-1">Member</th>
                                        <th class="pb-3 pr-4">Product</th>
                                        <th class="pb-3 pr-4">Amount</th>
                                        <th class="pb-3 pr-1">Applied</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach ($pendingLoans as $loan)
                                        <tr class="hover:bg-gray-50/50 transition-colors">
                                            <td class="py-3 pr-4 pl-1 font-medium text-gray-900">
                                                {{ $loan->user?->personalData?->first_name ?? '—' }} {{ $loan->user?->personalData?->last_name ?? '' }}
                                            </td>
                                            <td class="py-3 pr-4 text-gray-600 text-xs">{{ $loan->loan_type_label }}</td>
                                            <td class="py-3 pr-4 font-bold text-gray-900">₱{{ number_format($loan->loan_amount, 2) }}</td>
                                            <td class="py-3 pr-1 text-xs text-gray-400">{{ $loan->created_at?->format('M d') }}</td>
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
                            <p class="text-sm font-medium text-gray-700">No pending applications</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Overdue Loans --}}
            @if ($overdueLoans->count() > 0)
            <div class="bg-white rounded-2xl shadow-sm border border-red-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-red-100 flex items-center justify-between bg-red-50/50">
                    <div>
                        <h2 class="text-base font-bold text-red-700">Overdue Loans</h2>
                        <p class="text-xs text-red-500">Past due date — follow-up required</p>
                    </div>
                    <span class="text-xs font-semibold text-red-700 bg-red-100 px-2.5 py-1 rounded-full">{{ $kpis['loans_overdue'] }} overdue</span>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto -mx-1">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                    <th class="pb-3 pr-4 pl-1">Member</th>
                                    <th class="pb-3 pr-4">Amount</th>
                                    <th class="pb-3 pr-4">Due Date</th>
                                    <th class="pb-3 pr-1">Days Overdue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($overdueLoans as $loan)
                                    @php $daysOverdue = now()->diffInDays($loan->due_date, false) * -1; @endphp
                                    <tr class="hover:bg-red-50/30 transition-colors">
                                        <td class="py-3 pr-4 pl-1 font-medium text-gray-900">
                                            {{ $loan->user?->personalData?->first_name ?? '—' }} {{ $loan->user?->personalData?->last_name ?? '' }}
                                        </td>
                                        <td class="py-3 pr-4 font-bold text-gray-900">₱{{ number_format($loan->loan_amount, 2) }}</td>
                                        <td class="py-3 pr-4 text-xs text-red-600">{{ \Carbon\Carbon::parse($loan->due_date)->format('M d, Y') }}</td>
                                        <td class="py-3 pr-1">
                                            <span class="text-xs font-semibold text-red-700 bg-red-100 px-2 py-0.5 rounded-full">{{ $daysOverdue }}d</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- Right: Loan distribution + announcements --}}
        <div class="space-y-5">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                @php $loanTotal = array_sum(array_column($loanStatusSeries, 'count')); @endphp
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-gray-900">Loan Distribution</h3>
                    <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-lg">{{ number_format($loanTotal) }} total</span>
                </div>
                <div class="space-y-3">
                    @forelse ($loanStatusSeries as $row)
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-medium text-gray-700">{{ $row['label'] }}</span>
                                <span class="text-gray-500">{{ number_format($row['count']) }} <span class="text-gray-400">({{ number_format($row['percent'], 1) }}%)</span></span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="h-2 rounded-full {{ $row['color'] }}" style="width: {{ min(100, $row['percent']) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 text-center py-4">No loans yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">Announcements</h3>
                </div>
                <div class="p-5 space-y-3.5">
                    @forelse ($recentAnnouncements as $a)
                        <div class="flex gap-3">
                            <div class="w-1 shrink-0 rounded-full bg-amber-400 self-stretch"></div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $a->title }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ \Illuminate\Support\Str::limit($a->content, 90) }}</p>
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
