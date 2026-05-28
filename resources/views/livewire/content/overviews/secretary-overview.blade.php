<div class="space-y-6">
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-teal-700 via-teal-600 to-cyan-700 p-8 text-white shadow-xl">
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="mb-1 text-sm font-medium uppercase tracking-widest text-teal-200">Secretary</p>
                <h1 class="text-3xl font-extrabold leading-tight">
                    {{ $greeting }}, {{ Auth::user()->personalData?->first_name ?? 'Secretary' }}
                </h1>
                <p class="mt-1 text-teal-200">Member records and communications - {{ $kpis['period_label'] ?? now()->format('F Y') }}</p>
            </div>
            <div class="shrink-0 sm:text-right">
                <p class="text-xs uppercase tracking-wide text-teal-200">Today</p>
                <p class="text-2xl font-bold">{{ now()->format('M d, Y') }}</p>
                <p class="text-sm text-teal-200">{{ now()->format('l') }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-2xl font-extrabold text-gray-900">{{ number_format($kpis['members_total'] ?? 0) }}</p>
            <p class="mt-0.5 text-xs text-gray-500">Total Members</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-2xl font-extrabold text-gray-900">{{ number_format($kpis['members_new_month'] ?? 0) }}</p>
            <p class="mt-0.5 text-xs text-gray-500">New This Month</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-2xl font-extrabold text-gray-900">{{ number_format($kpis['members_active'] ?? 0) }}</p>
            <p class="mt-0.5 text-xs text-gray-500">Active Members</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-2xl font-extrabold text-gray-900">{{ number_format($kpis['announcements_total'] ?? 0) }}</p>
            <p class="mt-0.5 text-xs text-gray-500">Announcements</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm xl:col-span-2">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-bold text-gray-900">Recently Registered Members</h2>
                <p class="text-xs text-gray-500">Latest member enrollments</p>
            </div>
            <div class="p-6">
                @forelse ($recentMembers as $member)
                    <div class="flex items-center gap-4 border-b border-gray-50 py-2 last:border-0">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-teal-100 text-sm font-bold text-teal-700">
                            {{ strtoupper(substr($member->personalData?->first_name ?? 'M', 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-gray-900">
                                {{ $member->personalData?->first_name ?? 'Member' }} {{ $member->personalData?->last_name ?? '' }}
                            </p>
                            <p class="text-xs text-gray-400">{{ $member->user_number }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                {{ ucfirst($member->account_status ?? 'active') }}
                            </span>
                            <p class="mt-1 text-xs text-gray-400">{{ $member->created_at?->format('M d, Y') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-gray-400">No members registered yet.</p>
                @endforelse
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-3.5">
                <h3 class="text-sm font-bold text-gray-900">Announcements</h3>
            </div>
            <div class="space-y-4 p-5">
                @forelse ($recentAnnouncements as $announcement)
                    <div class="flex gap-3">
                        <div class="w-1 shrink-0 self-stretch rounded-full bg-teal-400"></div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $announcement->title }}</p>
                            <p class="mt-0.5 text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($announcement->content, 100) }}</p>
                            <p class="mt-1 text-xs text-gray-400">{{ $announcement->created_at?->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-gray-400">No announcements yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
