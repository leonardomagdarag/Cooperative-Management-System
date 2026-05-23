<div class="space-y-6">

    {{-- HERO --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-teal-700 via-teal-600 to-cyan-700 rounded-2xl shadow-xl p-8 text-white">
        <div class="absolute -top-8 -right-8 w-48 h-48 bg-white/5 rounded-full pointer-events-none"></div>
        <div class="absolute -bottom-12 -left-6 w-40 h-40 bg-white/5 rounded-full pointer-events-none"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <p class="text-teal-200 text-sm font-medium uppercase tracking-widest mb-1">Secretary</p>
                <h1 class="text-3xl font-extrabold leading-tight">{{ $greeting }}, {{ Auth::user()->personalData?->first_name ?? 'Secretary' }}</h1>
                <p class="mt-1 text-teal-200">Member records &amp; communications &mdash; {{ $kpis['period_label'] ?? now()->format('F Y') }}</p>
            </div>
            <div class="sm:text-right shrink-0">
                <p class="text-teal-200 text-xs uppercase tracking-wide">Today</p>
                <p class="text-2xl font-bold">{{ now()->format('M d, Y') }}</p>
                <p class="text-teal-200 text-sm">{{ now()->format('l') }}</p>
            </div>
        </div>
        @if (($kpis['members_new_month'] ?? 0) > 0)
            <div class="relative mt-5">
                <span class="inline-flex items-center gap-1.5 bg-white/15 border border-white/20 text-teal-50 text-xs font-semibold px-3 py-1.5 rounded-full">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"/></svg>
                    {{ $kpis['members_new_month'] }} new member{{ $kpis['members_new_month'] != 1 ? 's' : '' }} this month
                </span>
            </div>
        @endif
    </div>

    {{-- KPI Row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-teal-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-teal-600" fill="currentColor" viewBox="0 0 20 20"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
                </div>
                <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">+{{ number_format($kpis['members_new_month'] ?? 0) }} mo.</span>
            </div>
            <p class="text-2xl font-extrabold text-gray-900">{{ number_format($kpis['members_total'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Total Members</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-gray-900">{{ number_format($kpis['members_active'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Active Members</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/></svg>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-gray-900">{{ number_format($kpis['members_inactive'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Inactive / Other</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-cyan-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-cyan-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 3a1 1 0 00-1.447-.894L8.763 6H5a3 3 0 000 6h.28l1.771 5.316A1 1 0 008 18h1a1 1 0 001-1v-4.382l6.553 3.276A1 1 0 0018 15V3z" clip-rule="evenodd"/></svg>
                </div>
                <span class="text-xs font-semibold text-cyan-600 bg-cyan-50 px-2 py-0.5 rounded-full">+{{ number_format($kpis['announcements_this_month'] ?? 0) }} mo.</span>
            </div>
            <p class="text-2xl font-extrabold text-gray-900">{{ number_format($kpis['announcements_total'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Total Announcements</p>
        </div>
    </div>

    {{-- Main panels --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Recent members table --}}
        <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Recently Registered Members</h2>
                    <p class="text-xs text-gray-500">Latest member enrollments</p>
                </div>
                @if (($kpis['members_new_month'] ?? 0) > 0)
                    <span class="text-xs font-semibold text-emerald-700 bg-emerald-100 px-2.5 py-1 rounded-full">+{{ $kpis['members_new_month'] }} this month</span>
                @endif
            </div>
            <div class="p-6">
                @if ($recentMembers->count() > 0)
                    <div class="space-y-3">
                        @foreach ($recentMembers as $member)
                            <div class="flex items-center gap-4 py-2 border-b border-gray-50 last:border-0">
                                <div class="w-9 h-9 rounded-full bg-teal-100 flex items-center justify-center text-teal-700 font-bold text-sm shrink-0">
                                    {{ strtoupper(substr($member->personalData?->first_name ?? 'M', 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">
                                        {{ $member->personalData?->first_name ?? '—' }} {{ $member->personalData?->last_name ?? '' }}
                                    </p>
                                    <p class="text-xs text-gray-400">{{ $member->user_number }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    @php
                                        $statusCls = match($member->account_status ?? 'inactive') {
                                            'active'     => 'bg-emerald-100 text-emerald-700',
                                            'inactive'   => 'bg-gray-100 text-gray-500',
                                            'terminated' => 'bg-red-100 text-red-600',
                                            default      => 'bg-gray-100 text-gray-500',
                                        };
                                    @endphp
                                    <span class="text-xs font-semibold {{ $statusCls }} px-2 py-0.5 rounded-full">{{ ucfirst($member->account_status ?? 'inactive') }}</span>
                                    <p class="text-xs text-gray-400 mt-1">{{ $member->created_at?->format('M d, Y') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400 text-center py-8">No members registered yet.</p>
                @endif
            </div>
        </div>

        {{-- Announcements panel --}}
        <div class="space-y-5">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900">Announcements</h3>
                    <span class="text-xs text-gray-400">{{ number_format($kpis['announcements_total'] ?? 0) }} total</span>
                </div>
                <div class="p-5 space-y-4">
                    @forelse ($recentAnnouncements as $a)
                        <div class="flex gap-3">
                            <div class="w-1 shrink-0 rounded-full bg-teal-400 self-stretch"></div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $a->title }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ \Illuminate\Support\Str::limit($a->content, 100) }}</p>
                                <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                                    {{ $a->created_at?->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 text-center py-4">No announcements yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Member stats breakdown --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-bold text-gray-900 mb-4">Membership Breakdown</h3>
                @php
                    $mTotal = max(1, (int)($kpis['members_total'] ?? 1));
                    $mActive = (int)($kpis['members_active'] ?? 0);
                    $mInactive = (int)($kpis['members_inactive'] ?? 0);
                    $wActive = min(100, ($mActive / $mTotal) * 100);
                    $wInactive = min(100, ($mInactive / $mTotal) * 100);
                @endphp
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-gray-600 font-medium">Active</span>
                            <span class="text-gray-500">{{ number_format($mActive) }} ({{ number_format($wActive, 1) }}%)</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $wActive }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-gray-600 font-medium">Inactive / Other</span>
                            <span class="text-gray-500">{{ number_format($mInactive) }} ({{ number_format($wInactive, 1) }}%)</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="h-2 rounded-full bg-gray-400" style="width: {{ $wInactive }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
