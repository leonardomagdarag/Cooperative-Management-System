@php
    $officerRoles = \App\Support\PermissionCatalog::officerRoles();
    $editablePermissions = collect($groups)->flatten()->unique()->values()->all();

    $initialRolePermissions = [];
    $rolePresetPermissions = [];
    $permissionLabels = [];

    foreach ($editablePermissions as $permission) {
        $permissionLabels[$permission] = \App\Support\PermissionCatalog::formatLabel($permission);
    }

    foreach ($officerRoles as $role) {
        $initialRolePermissions[$role] = array_values(
            array_intersect($editablePermissions, $rolePermissions[$role] ?? []),
        );
        $rolePresetPermissions[$role] = array_values(array_intersect($editablePermissions, $rolePresets[$role] ?? []));
    }

    $lockedPermissions = ['dashboard.view', 'notifications.view', 'profile.view', 'profile.update', 'settings.view'];
@endphp

<div x-data="{
    activeRole: @js($selectedRole),
    roles: @js($officerRoles),
    roleLabels: @js($roleDisplayNames),
    groups: @js($groups),
    permissionLabels: @js($permissionLabels),
    rolePermissions: @js($initialRolePermissions),
    initialRolePermissions: @js($initialRolePermissions),
    rolePresets: @js($rolePresetPermissions),
    lockedPermissions: @js($lockedPermissions),
    totalEditableCount: @js(count($editablePermissions)),
    pendingRole: null,
    showDiscardModal: false,
    showSaveModal: false,
    isSaving: false,
    saveSummary: { added: [], removed: [] },
    init() {
        if (!this.activeRole && this.roles.length) {
            this.activeRole = this.roles[0];
        }
    },
    normalize(list) {
        return [...new Set((list || []).filter(Boolean))].sort();
    },
    current(role = this.activeRole) {
        return this.normalize(this.rolePermissions[role] || []);
    },
    initial(role = this.activeRole) {
        return this.normalize(this.initialRolePermissions[role] || []);
    },
    preset(role = this.activeRole) {
        return this.normalize(this.rolePresets[role] || []);
    },
    hasChanges(role = this.activeRole) {
        return JSON.stringify(this.current(role)) !== JSON.stringify(this.initial(role));
    },
    permissionLabel(permission) {
        return this.permissionLabels[permission] || permission;
    },
    roleLabel(role) {
        return this.roleLabels[role] || role;
    },
    groupGrantedCount(permissions, role = this.activeRole) {
        return permissions.filter((permission) => this.current(role).includes(permission)).length;
    },
    requestRoleSwitch(role) {
        if (role === this.activeRole) {
            return;
        }

        if (this.hasChanges()) {
            this.pendingRole = role;
            this.showDiscardModal = true;
            return;
        }

        this.switchRole(role);
    },
    switchRole(role) {
        this.activeRole = role;
        this.pendingRole = null;
        this.showDiscardModal = false;
    },
    discardAndSwitch() {
        if (!this.pendingRole) {
            return;
        }

        this.rolePermissions[this.activeRole] = [...this.initial(this.activeRole)];
        this.switchRole(this.pendingRole);
    },
    togglePermission(permission) {
        const currentPermissions = this.current();

        if (currentPermissions.includes(permission)) {
            this.rolePermissions[this.activeRole] = currentPermissions.filter((item) => item !== permission);
            return;
        }

        this.rolePermissions[this.activeRole] = this.normalize([...currentPermissions, permission]);
    },
    restorePreset() {
        this.rolePermissions[this.activeRole] = [...this.preset(this.activeRole)];
    },
    openSaveModal() {
        if (!this.hasChanges() || this.isSaving) {
            return;
        }

        const currentPermissions = this.current();
        const initialPermissions = this.initial();

        this.saveSummary = {
            added: currentPermissions.filter((permission) => !initialPermissions.includes(permission)),
            removed: initialPermissions.filter((permission) => !currentPermissions.includes(permission)),
        };
        this.showSaveModal = true;
    },
    async confirmSave() {
        if (!this.hasChanges() || this.isSaving) {
            return;
        }

        const role = this.activeRole;
        const permissions = this.current();

        this.isSaving = true;
        this.showSaveModal = false;

        try {
            await this.$wire.saveRolePermissions(role, permissions);
            this.initialRolePermissions[role] = [...permissions];
            window.dispatchEvent(new CustomEvent('toast', {
                detail: {
                    message: `Role permissions updated for ${this.roleLabel(role)}.`,
                    type: 'success',
                }
            }));
        } catch (error) {
            console.error('[RolePermissions] saveRolePermissions failed:', error);
            this.showSaveModal = true;
            window.dispatchEvent(new CustomEvent('toast', {
                detail: {
                    message: 'Unable to save role permissions. Please try again.',
                    type: 'error',
                }
            }));
        } finally {
            this.isSaving = false;
        }
    },
}" x-init="init()" class="flex min-h-[calc(100vh-8rem)] flex-col gap-5 pb-28">
    <div class="rounded-xl border border-gray-200 bg-white px-6 py-4 shadow-sm">
        <h2 class="text-xl font-bold text-gray-900">Role Permission Management</h2>
        <p class="mt-0.5 text-sm text-gray-500">
            Configure the editable officer permissions. Dashboard, notifications, profile, and settings stay available
            to all accounts and are not part of this editor.
        </p>
    </div>

    <div class="grid flex-1 gap-5 xl:grid-cols-[240px_minmax(0,1fr)]">
        <div class="space-y-2">
            @foreach ($officerRoles as $role)
                <button type="button" @click.prevent="requestRoleSwitch('{{ $role }}')"
                    class="group w-full rounded-xl border px-4 py-3.5 text-left transition-all"
                    :class="activeRole === '{{ $role }}'
                        ?
                        'border-cyan-300 bg-cyan-50 shadow-sm ring-1 ring-cyan-200' :
                        'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold"
                            :class="activeRole === '{{ $role }}' ? 'text-cyan-900' : 'text-gray-800'">
                            {{ $roleDisplayNames[$role] ?? ucfirst($role) }}
                        </span>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="activeRole === '{{ $role }}' ? 'bg-cyan-100 text-cyan-700' :
                                'bg-gray-100 text-gray-500'"
                            x-text="current('{{ $role }}').length + ' / ' + totalEditableCount">
                        </span>
                    </div>
                </button>
            @endforeach
        </div>

        <div class="space-y-3">
            @foreach ($groups as $groupName => $permissions)
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-5 py-2.5">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $groupName }}
                        </h3>
                        <span class="text-xs text-gray-400"
                            x-text="groupGrantedCount(@js($permissions)) + ' / {{ count($permissions) }}'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-px bg-gray-100 sm:grid-cols-2">
                        @foreach ($permissions as $permission)
                            <div
                                class="flex items-center justify-between gap-3 bg-white px-4 py-3 transition-colors hover:bg-gray-50">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-800">
                                        {{ \App\Support\PermissionCatalog::formatLabel($permission) }}
                                    </p>
                                    <div class="mt-0.5 flex items-center gap-2">
                                        <span class="shrink-0 text-[10px] font-medium text-emerald-600"
                                            x-show="rolePresets[activeRole] && rolePresets[activeRole].includes('{{ $permission }}')">
                                            preset
                                        </span>
                                    </div>
                                </div>

                                <button type="button" @click.prevent="togglePermission('{{ $permission }}')"
                                    role="switch"
                                    :aria-checked="current().includes('{{ $permission }}') ? 'true' : 'false'"
                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-1"
                                    :class="current().includes('{{ $permission }}') ? 'bg-cyan-500' : 'bg-gray-200'">
                                    <span
                                        class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        :class="current().includes('{{ $permission }}') ? 'translate-x-4' : 'translate-x-0'">
                                    </span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div
        class="fixed bottom-0 left-0 right-0 z-30 border-t border-gray-200 bg-white/95 px-6 py-3 shadow-lg backdrop-blur-sm xl:left-64">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
            <button type="button" @click.prevent="$wire.goToSettings()"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back to Settings
            </button>

            <div class="flex items-center gap-2">
                <button type="button" @click.prevent="restorePreset()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-300">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    Restore Preset
                </button>
                <button type="button" @click.prevent="openSaveModal()" :disabled="!hasChanges() || isSaving"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-cyan-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-300 disabled:cursor-not-allowed disabled:bg-cyan-300">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                    <span x-text="isSaving ? 'Saving...' : 'Save Changes'"></span>
                </button>
            </div>
        </div>
    </div>

    <div x-cloak x-show="showDiscardModal" x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 px-4 py-6">
        <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start gap-4">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v4m0 4h.01M10.29 3.86l-8.13 14A1.5 1.5 0 0 0 3.46 20h17.08a1.5 1.5 0 0 0 1.3-2.14l-8.13-14a1.5 1.5 0 0 0-2.6 0Z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-semibold text-gray-900">Discard Changes?</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        You have unsaved changes for <span class="font-medium" x-text="roleLabel(activeRole)"></span>.
                        Switching roles will discard those edits.
                    </p>
                    <p class="mt-1 text-xs text-gray-400">
                        Current draft will be reset before loading <span class="font-medium"
                            x-text="roleLabel(pendingRole)"></span>.
                    </p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click.prevent="showDiscardModal = false; pendingRole = null"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Keep Editing
                </button>
                <button type="button" @click.prevent="discardAndSwitch()"
                    class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                    Discard Changes
                </button>
            </div>
        </div>
    </div>

    <div x-cloak x-show="showSaveModal" x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 px-4 py-6">
        <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start gap-4">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-cyan-700">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-semibold text-gray-900">Review Changes</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        You're about to save permission updates for <span class="font-medium"
                            x-text="roleLabel(activeRole)"></span>.
                    </p>
                    <p class="mt-1 text-xs text-gray-400">
                        Role key: <span class="font-mono" x-text="activeRole"></span>
                    </p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Added</p>
                    <p class="mt-1 text-sm text-emerald-900"
                        x-text="saveSummary.added.length ? saveSummary.added.length + ' permission(s)' : 'No additions' ">
                    </p>
                </div>
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Removed</p>
                    <p class="mt-1 text-sm text-rose-900"
                        x-text="saveSummary.removed.length ? saveSummary.removed.length + ' permission(s)' : 'No removals'">
                    </p>
                </div>
            </div>

            <div class="mt-5 space-y-4">
                <div x-show="saveSummary.added.length" class="rounded-xl border border-gray-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Added permissions</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <template x-for="permission in saveSummary.added" :key="'added-' + permission">
                            <span
                                class="inline-flex max-w-full items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                                <span class="truncate" x-text="permissionLabel(permission)"></span>
                            </span>
                        </template>
                    </div>
                </div>

                <div x-show="saveSummary.removed.length" class="rounded-xl border border-gray-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Removed permissions</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <template x-for="permission in saveSummary.removed" :key="'removed-' + permission">
                            <span
                                class="inline-flex max-w-full items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-medium text-rose-700">
                                <span class="truncate" x-text="permissionLabel(permission)"></span>
                            </span>
                        </template>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click.prevent="showSaveModal = false"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="button" @click.prevent="confirmSave()" :disabled="isSaving"
                    class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:bg-cyan-300">
                    Confirm and Save
                </button>
            </div>
        </div>
    </div>
</div>
