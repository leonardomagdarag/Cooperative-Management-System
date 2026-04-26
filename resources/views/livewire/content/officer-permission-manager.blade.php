<div class="space-y-6">
    {{-- Header with presets --}}
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-lg border border-blue-100">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Officer Permissions</h3>
        <p class="text-sm text-gray-600 mb-4">
            Assign individual permissions to customize this officer's access. Use presets below for common roles.
        </p>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
            <button wire:click="presetManager"
                class="px-3 py-2 bg-white border border-gray-300 rounded hover:bg-gray-50 text-xs font-medium text-gray-700 transition"
                title="Common manager permissions">
                Manager
            </button>
            <button wire:click="presetTreasurer"
                class="px-3 py-2 bg-white border border-gray-300 rounded hover:bg-gray-50 text-xs font-medium text-gray-700 transition"
                title="Common treasurer permissions">
                Treasurer
            </button>
            <button wire:click="presetLoanOfficer"
                class="px-3 py-2 bg-white border border-gray-300 rounded hover:bg-gray-50 text-xs font-medium text-gray-700 transition"
                title="Common loan officer permissions">
                Loan Officer
            </button>
            <button wire:click="presetSecretary"
                class="px-3 py-2 bg-white border border-gray-300 rounded hover:bg-gray-50 text-xs font-medium text-gray-700 transition"
                title="Common secretary permissions">
                Secretary
            </button>
            <button wire:click="clearAll"
                class="px-3 py-2 bg-white border border-red-300 rounded hover:bg-red-50 text-xs font-medium text-red-700 transition">
                Clear All
            </button>
        </div>
    </div>

    {{-- Permissions grouped by resource --}}
    <div class="space-y-4">
        @foreach ($this->permissionsByGroup as $groupName => $permissions)
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                {{-- Group header with separate actions --}}
                <div class="w-full px-4 py-3 bg-gray-50 hover:bg-gray-100 flex items-center justify-between transition">
                    <div class="flex items-center space-x-3">
                        {{-- Select all toggle --}}
                        <button type="button" wire:click="toggleGroup('{{ $groupName }}')"
                            class="relative p-0.5 rounded hover:bg-gray-200 transition"
                            title="Toggle all in {{ $groupName }}">
                            @if ($this->isGroupAllSelected($groupName))
                                <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                            @elseif ($this->isGroupPartiallySelected($groupName))
                                <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M3 10a7 7 0 1114 0 7 7 0 01-14 0zm7-3a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v2a1 1 0 001 1h1a1 1 0 100-2v-2a1 1 0 00-1-1H9z"
                                        clip-rule="evenodd" />
                                </svg>
                            @else
                                <input type="checkbox" class="w-5 h-5 border-gray-300 rounded text-blue-600" disabled />
                            @endif
                        </button>
                        <span class="font-medium text-gray-800">{{ $groupName }}</span>
                        <span
                            class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded">{{ count(array_filter($permissions, fn($p) => in_array($p, $this->selectedPermissions))) }}/{{ count($permissions) }}</span>
                    </div>

                    {{-- Chevron indicator --}}
                    <button type="button" wire:click="toggleExpandedGroup('{{ $groupName }}')"
                        class="p-1 rounded hover:bg-gray-200 transition" title="Expand/collapse {{ $groupName }}">
                        <svg class="w-5 h-5 text-gray-600 transition transform {{ $this->isGroupExpanded($groupName) ? 'rotate-180' : '' }}"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                        </svg>
                    </button>
                </div>

                {{-- Group permissions (collapsible) --}}
                @if ($this->isGroupExpanded($groupName))
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 p-4 bg-white">
                        @foreach ($permissions as $permission)
                            <label
                                class="flex items-center space-x-2 cursor-pointer hover:bg-blue-50 p-2 rounded transition">
                                <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission }}"
                                    class="w-4 h-4 border-gray-300 rounded text-blue-600 focus:ring-blue-500" />
                                <span
                                    class="text-sm text-gray-700">{{ $this->formatPermissionLabel($permission) }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Selected count summary --}}
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <p class="text-sm text-blue-800">
            <strong>{{ count($this->selectedPermissions) }} permissions</strong> selected
        </p>
    </div>

    {{-- Action buttons --}}
    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
        <button wire:click="$dispatch('permission-manager-close')"
            class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50 font-medium transition">
            Cancel
        </button>
        <button wire:click="savePermissions"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium transition flex items-center space-x-2"
            wire:loading.attr="disabled" wire:loading.class="opacity-75">
            <span wire:loading.remove>Save Permissions</span>
            <span wire:loading class="flex items-center space-x-1">
                <svg class="animate-spin h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                <span>Saving...</span>
            </span>
        </button>
    </div>
</div>
