@php
    $officerName = trim(
        collect([$officer->personalData->first_name ?? null, $officer->personalData->last_name ?? null])
            ->filter()
            ->implode(' '),
    );
    $displayName = $officerName !== '' ? $officerName : $officer->email;
    $roleLabel = \App\Support\PermissionCatalog::roleDisplayNames()[$roleName] ?? ucfirst((string) $roleName);
@endphp

<div class="space-y-4" wire:loading.class="pointer-events-none opacity-60" wire:loading.class.remove="">

    {{-- ── Unsaved Changes Banner ──────────────────────────────── --}}
    @if ($isDirty)
        <div class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
            <svg class="h-4 w-4 shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                    clip-rule="evenodd" />
            </svg>
            <span class="text-sm font-semibold text-amber-800">Unsaved changes</span>
            <span class="text-xs text-amber-700">— use <strong>Save Overrides</strong> at the bottom to apply.</span>
        </div>
    @endif

    {{-- ── Header / Stats ──────────────────────────────────────── --}}
    <div
        class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h4 class="text-base font-semibold text-gray-900">Permission Overrides</h4>
            <p class="mt-1 text-sm text-gray-500">
                <span class="font-medium text-gray-800">{{ $displayName }}</span> inherits permissions from the
                <span class="font-semibold text-indigo-700">{{ $roleLabel }}</span> role.
                Overrides apply exceptions on top.
            </p>
        </div>
        <div class="grid shrink-0 grid-cols-4 gap-2">
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-center">
                <p class="text-xs uppercase tracking-wide text-gray-400">Role</p>
                <p class="text-xl font-bold text-gray-900">{{ count($rolePermissions) }}</p>
            </div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2.5 text-center">
                <p class="text-xs uppercase tracking-wide text-blue-500">Total</p>
                <p class="text-xl font-bold text-blue-800">{{ $totalOverrides }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-center">
                <p class="text-xs uppercase tracking-wide text-emerald-600">Allows</p>
                <p class="text-xl font-bold text-emerald-700">{{ $allowCount }}</p>
            </div>
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-center">
                <p class="text-xs uppercase tracking-wide text-red-500">Denies</p>
                <p class="text-xl font-bold text-red-700">{{ $denyCount }}</p>
            </div>
        </div>
    </div>

    {{-- ── Legend ──────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-lg border border-gray-100 bg-gray-50 px-4 py-2.5 text-xs text-gray-500">
        <span class="font-semibold text-gray-600">Effective access:</span>
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>Active (from role)
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Granted (override)
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>Denied (override)
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-gray-300"></span>Inactive
        </span>
    </div>

    {{-- ── Toolbar ─────────────────────────────────────────────── --}}
    <div
        class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
            {{-- Search --}}
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                </svg>
                <input id="permission-search" type="text" wire:model.live.debounce.250ms="search"
                    placeholder="Search permissions…"
                    class="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-4 text-sm text-gray-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200" />
            </div>
            {{-- Show only overrides --}}
            <label class="inline-flex cursor-pointer select-none items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" wire:model.live="showOnlyOverrides"
                    class="rounded border-gray-300 text-cyan-600 focus:ring-cyan-400" />
                Show only overrides
            </label>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Clear All with inline confirmation --}}
            @if ($confirmingClearAll)
                <span class="text-xs font-semibold text-red-700">Clear all overrides?</span>
                <button type="button" wire:click="doClearAll"
                    class="inline-flex items-center rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-300">
                    Yes, clear all
                </button>
                <button type="button" wire:click="cancelClearAll"
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-100">
                    Cancel
                </button>
            @else
                <button type="button" wire:click="confirmClearAll"
                    @if ($totalOverrides === 0) disabled @endif
                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-100 disabled:cursor-not-allowed disabled:opacity-40">
                    Clear All
                </button>
            @endif
        </div>
    </div>

    {{-- ── Permission Groups ────────────────────────────────────── --}}
    <div class="space-y-4">
        @forelse ($groups as $groupName => $permissions)
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                {{-- Group header --}}
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50/80 px-5 py-3">
                    <h5 class="text-xs font-bold uppercase tracking-wider text-gray-500">{{ $groupName }}</h5>
                    <span
                        class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-600">{{ count($permissions) }}</span>
                </div>

                <div class="divide-y divide-gray-100">
                    @foreach ($permissions as $permission)
                        @php
                            $currentState = $overrides[$permission] ?? 'inherit';
                            $inherits = in_array($permission, $rolePermissions, true);
                            $isChanged =
                                ($overrides[$permission] ?? 'inherit') !==
                                ($savedOverrides[$permission] ?? 'inherit');

                            // Effective access preview
                            if ($currentState === 'disallow') {
                                $effectiveAccess = 'denied';
                            } elseif ($currentState === 'allow') {
                                $effectiveAccess = 'granted';
                            } elseif ($inherits) {
                                $effectiveAccess = 'active';
                            } else {
                                $effectiveAccess = 'inactive';
                            }
                        @endphp

                        <div
                            class="flex flex-col gap-3 px-5 py-4 transition-colors lg:flex-row lg:items-center lg:justify-between {{ $isChanged ? 'bg-amber-50/50' : '' }}">
                            {{-- Info column --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ \App\Support\PermissionCatalog::formatLabel($permission) }}</p>
                                    @if ($isChanged)
                                        <span
                                            class="rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-bold uppercase tracking-wide text-amber-700">Modified</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 font-mono text-xs text-gray-400">{{ $permission }}</p>

                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    {{-- Role badge --}}
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ $inherits ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-400' }}">
                                        @if ($inherits)
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            In role
                                        @else
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            Not in role
                                        @endif
                                    </span>

                                    {{-- Effective access badge --}}
                                    @if ($effectiveAccess === 'denied')
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M13.477 14.89A6 6 0 015.11 6.524L13.477 14.89zm1.414-1.414L6.524 5.11A6 6 0 0114.89 13.476zM18 10a8 8 0 11-16 0 8 8 0 0116 0z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            Denied
                                        </span>
                                    @elseif ($effectiveAccess === 'granted')
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            Granted{{ !$inherits ? ' (override)' : '' }}
                                        </span>
                                    @elseif ($effectiveAccess === 'active')
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            Active
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-400">
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            Inactive
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Tri-state toggle buttons --}}
                            <div class="inline-flex shrink-0 gap-0.5 rounded-lg border border-gray-200 bg-gray-100 p-1">
                                <button type="button"
                                    wire:click="setPermissionState('{{ $permission }}', 'inherit')"
                                    title="Inherit from role — no override"
                                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all focus:outline-none
                                        {{ $currentState === 'inherit' ? 'bg-white text-gray-800 shadow ring-1 ring-gray-200' : 'text-gray-500 hover:bg-white/60 hover:text-gray-700' }}">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Role Default
                                </button>
                                <button type="button"
                                    wire:click="setPermissionState('{{ $permission }}', 'allow')"
                                    title="Always allow — even if not in role"
                                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all focus:outline-none
                                        {{ $currentState === 'allow' ? 'bg-emerald-600 text-white shadow' : 'text-emerald-600 hover:bg-emerald-50' }}">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Allow
                                </button>
                                <button type="button"
                                    wire:click="setPermissionState('{{ $permission }}', 'disallow')"
                                    title="Always deny — even if granted by role"
                                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all focus:outline-none
                                        {{ $currentState === 'disallow' ? 'bg-red-600 text-white shadow' : 'text-red-600 hover:bg-red-50' }}">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Deny
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div
                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center">
                <svg class="mb-3 h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" />
                </svg>
                <p class="text-sm font-medium text-gray-500">No permissions found</p>
                @if ($showOnlyOverrides)
                    <p class="mt-1 text-xs text-gray-400">No overrides are set yet. Uncheck "Show only overrides" to see
                        all permissions.</p>
                @else
                    <p class="mt-1 text-xs text-gray-400">Try a different search term.</p>
                @endif
            </div>
        @endforelse
    </div>
</div>
