<div class="space-y-5">

    {{-- Officer context --}}
    <div class="bg-gradient-to-r from-cyan-50 to-emerald-50 border border-cyan-100 rounded-lg p-4">
        <h3 class="text-base font-semibold text-gray-900">
            Permission Overrides — {{ $officer->personalData->first_name ?? '' }}
            {{ $officer->personalData->last_name ?? '' }}
        </h3>
        <p class="text-xs text-gray-600 mt-1">
            Role: <span
                class="font-medium text-cyan-700">{{ ucfirst($officer->getRoleNames()->first() ?? 'unknown') }}</span>
            &nbsp;·&nbsp;
            Overrides layer on top of the role's default permissions.
            <strong class="text-red-600">Disallow</strong> wins over the role grant;
            <strong class="text-emerald-600">Allow</strong> supplements the role.
        </p>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap gap-4 text-xs text-gray-600">
        <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-gray-300 inline-block"></span>
            Inherit (role default)
        </span>
        <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span>
            Allow (grant even if role doesn't have it)
        </span>
        <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span>
            Disallow (deny even if role grants it)
        </span>
    </div>

    {{-- Clear all --}}
    <div class="flex justify-end">
        <button type="button" wire:click="clearAllOverrides"
            class="px-3 py-1.5 text-xs font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Reset All to Inherit
        </button>
    </div>

    {{-- Permission groups --}}
    <div class="space-y-3">
        @foreach ($permissionsByGroup as $groupName => $permissions)
            @php
                $isExpanded = in_array($groupName, $expandedGroups, true);
                $groupOverrideCounts = [
                    'allow' => collect($permissions)
                        ->filter(fn($p) => ($overrides[$p] ?? 'inherit') === 'allow')
                        ->count(),
                    'disallow' => collect($permissions)
                        ->filter(fn($p) => ($overrides[$p] ?? 'inherit') === 'disallow')
                        ->count(),
                ];
            @endphp
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <button type="button" wire:click="toggleExpandedGroup('{{ $groupName }}')"
                    class="w-full px-4 py-3 bg-gray-50 hover:bg-gray-100 flex items-center justify-between transition text-left">
                    <div class="flex items-center gap-3">
                        <span class="font-medium text-gray-800 text-sm">{{ $groupName }}</span>
                        @if ($groupOverrideCounts['allow'] > 0)
                            <span
                                class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-medium">+{{ $groupOverrideCounts['allow'] }}
                                allow</span>
                        @endif
                        @if ($groupOverrideCounts['disallow'] > 0)
                            <span
                                class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">{{ $groupOverrideCounts['disallow'] }}
                                deny</span>
                        @endif
                    </div>
                    <svg class="w-4 h-4 text-gray-500 transition-transform {{ $isExpanded ? 'rotate-180' : '' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                @if ($isExpanded)
                    <div class="divide-y divide-gray-100">
                        @foreach ($permissions as $permission)
                            @php
                                $currentOverride = $overrides[$permission] ?? 'inherit';
                                $roleHas = in_array($permission, $this->rolePermissions, true);
                            @endphp
                            <div wire:key="override-{{ str_replace('.', '-', $permission) }}"
                                class="flex items-center justify-between px-4 py-2.5 bg-white hover:bg-gray-50 transition">
                                <div class="flex items-center gap-2 min-w-0">
                                    {{-- Role baseline indicator --}}
                                    @if ($roleHas)
                                        <span
                                            class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700"
                                            title="Role grants this">Role</span>
                                    @else
                                        <span
                                            class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-400">—</span>
                                    @endif
                                    <span
                                        class="text-sm text-gray-700 truncate">{{ \App\Support\PermissionCatalog::formatLabel($permission) }}</span>
                                </div>

                                {{-- Three-state toggle --}}
                                <div class="flex items-center gap-1 shrink-0 ml-3">
                                    <button type="button" wire:click="setOverride('{{ $permission }}', 'inherit')"
                                        class="px-2 py-1 text-xs font-medium rounded-l-md border transition
                                            {{ $currentOverride === 'inherit'
                                                ? 'bg-gray-700 text-white border-gray-700'
                                                : 'bg-white text-gray-500 border-gray-300 hover:bg-gray-100' }}">
                                        Inherit
                                    </button>
                                    <button type="button" wire:click="setOverride('{{ $permission }}', 'allow')"
                                        class="px-2 py-1 text-xs font-medium border-t border-b transition
                                            {{ $currentOverride === 'allow'
                                                ? 'bg-emerald-600 text-white border-emerald-600'
                                                : 'bg-white text-gray-500 border-gray-300 hover:bg-emerald-50 hover:text-emerald-700' }}">
                                        Allow
                                    </button>
                                    <button type="button" wire:click="setOverride('{{ $permission }}', 'disallow')"
                                        class="px-2 py-1 text-xs font-medium rounded-r-md border transition
                                            {{ $currentOverride === 'disallow'
                                                ? 'bg-red-600 text-white border-red-600'
                                                : 'bg-white text-gray-500 border-gray-300 hover:bg-red-50 hover:text-red-700' }}">
                                        Disallow
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Summary --}}
    @php
        $allowCount = collect($overrides)->filter(fn($v) => $v === 'allow')->count();
        $disallowCount = collect($overrides)->filter(fn($v) => $v === 'disallow')->count();
    @endphp
    @if ($allowCount > 0 || $disallowCount > 0)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
            @if ($allowCount > 0)
                <span class="font-medium text-emerald-700">{{ $allowCount }}
                    allow</span>{{ $disallowCount > 0 ? ' · ' : '' }}
            @endif
            @if ($disallowCount > 0)
                <span class="font-medium text-red-700">{{ $disallowCount }} disallow</span>
            @endif
            override{{ $allowCount + $disallowCount === 1 ? '' : 's' }} set for this officer.
        </div>
    @endif

    {{-- Actions --}}
    <div class="flex justify-end gap-3 pt-3 border-t border-gray-200">
        <button type="button" wire:click="$dispatch('permission-override-close')"
            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium text-sm transition-colors">
            Cancel
        </button>
        <button type="button" wire:click="saveOverrides"
            class="inline-flex items-center gap-2 px-5 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 font-medium text-sm transition-colors"
            wire:loading.attr="disabled" wire:loading.class="opacity-75" wire:target="saveOverrides">
            <svg wire:loading wire:target="saveOverrides" class="animate-spin w-4 h-4" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            <span wire:loading.remove wire:target="saveOverrides">Save Overrides</span>
            <span wire:loading wire:target="saveOverrides">Saving…</span>
        </button>
    </div>
</div>
