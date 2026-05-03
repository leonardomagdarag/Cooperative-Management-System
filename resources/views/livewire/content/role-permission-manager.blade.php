<div class="space-y-6">

    {{-- Back + Header --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Role Permissions</h2>
            <p class="text-sm text-gray-600 mt-1">
                Configure the default permissions each officer role inherits. Changes apply to all officers with that
                role
                unless they have a specific override.
            </p>
        </div>
        <button type="button" wire:click="goToSettings"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Settings
        </button>
    </div>

    {{-- Role Selector --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Select Role to Configure</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach ($roleLabels as $roleKey => $roleLabel)
                <button type="button" wire:click="$set('selectedRole', '{{ $roleKey }}')"
                    class="px-4 py-3 rounded-lg border-2 text-sm font-medium transition-all
                        {{ $selectedRole === $roleKey
                            ? 'border-cyan-500 bg-cyan-50 text-cyan-700'
                            : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50' }}">
                    {{ $roleLabel }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Permission Editor --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">

        {{-- Toolbar --}}
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900">
                    Permissions for <span class="text-cyan-600">{{ $roleLabels[$selectedRole] }}</span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ count($selectedPermissions) }} permission{{ count($selectedPermissions) === 1 ? '' : 's' }}
                    enabled
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="applyPreset"
                    class="px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">
                    Apply Default Preset
                </button>
                <button type="button" wire:click="clearAll"
                    class="px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                    Clear All
                </button>
            </div>
        </div>

        {{-- Permission groups --}}
        <div class="space-y-3">
            @foreach ($permissionsByGroup as $groupName => $permissions)
                @php
                    $allSelected = collect($permissions)->every(fn($p) => in_array($p, $selectedPermissions, true));
                    $someSelected =
                        !$allSelected &&
                        collect($permissions)->contains(fn($p) => in_array($p, $selectedPermissions, true));
                    $isExpanded = in_array($groupName, $expandedGroups, true);
                    $selectedCount = collect($permissions)
                        ->filter(fn($p) => in_array($p, $selectedPermissions, true))
                        ->count();
                @endphp
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <div class="w-full px-4 py-3 bg-gray-50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <button type="button" wire:click="toggleGroup('{{ $groupName }}')"
                                class="p-0.5 rounded hover:bg-gray-200 transition"
                                title="Toggle all in {{ $groupName }}">
                                @if ($allSelected)
                                    <svg class="w-5 h-5 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                @elseif ($someSelected)
                                    <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 000 2h6a1 1 0 100-2H7z"
                                            clip-rule="evenodd" />
                                    </svg>
                                @else
                                    <div class="w-5 h-5 border-2 border-gray-300 rounded"></div>
                                @endif
                            </button>
                            <span class="font-medium text-gray-800 text-sm">{{ $groupName }}</span>
                            <span
                                class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">{{ $selectedCount }}/{{ count($permissions) }}</span>
                        </div>
                        <button type="button" wire:click="toggleExpandedGroup('{{ $groupName }}')"
                            class="p-1 rounded hover:bg-gray-200 transition">
                            <svg class="w-4 h-4 text-gray-500 transition-transform {{ $isExpanded ? 'rotate-180' : '' }}"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>

                    @if ($isExpanded)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 p-4 bg-white">
                            @foreach ($permissions as $permission)
                                <label wire:key="rp-{{ str_replace('.', '-', $permission) }}"
                                    class="flex items-center gap-2 cursor-pointer hover:bg-blue-50 p-2 rounded transition">
                                    <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission }}"
                                        class="w-4 h-4 border-gray-300 rounded text-cyan-600 focus:ring-cyan-500" />
                                    <span
                                        class="text-sm text-gray-700">{{ \App\Support\PermissionCatalog::formatLabel($permission) }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Save bar --}}
        <div class="flex justify-end gap-3 mt-6 pt-5 border-t border-gray-200">
            <button type="button" wire:click="goToSettings"
                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium text-sm transition-colors">
                Cancel
            </button>
            <button type="button" wire:click="saveRolePermissions"
                class="inline-flex items-center gap-2 px-5 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 font-medium text-sm transition-colors"
                wire:loading.attr="disabled" wire:loading.class="opacity-75" wire:target="saveRolePermissions">
                <svg wire:loading wire:target="saveRolePermissions" class="animate-spin w-4 h-4" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                <span wire:loading.remove wire:target="saveRolePermissions">Save Role Permissions</span>
                <span wire:loading wire:target="saveRolePermissions">Saving…</span>
            </button>
        </div>
    </div>
</div>
