@php
    $editablePermissions = collect($groups)->flatten()->unique()->values()->all();

    $initialRolePermissions = [];
    $rolePresetPermissions = [];
    $permissionLabels = [];

    foreach ($editablePermissions as $permission) {
        $permissionLabels[$permission] = \App\Support\PermissionCatalog::formatLabel($permission);
    }

    foreach ($editableRoles as $role) {
        $initialRolePermissions[$role] = array_values(
            array_intersect($editablePermissions, $rolePermissions[$role] ?? []),
        );

        if (array_key_exists($role, $rolePresets)) {
            $rolePresetPermissions[$role] = array_values(array_intersect($editablePermissions, $rolePresets[$role]));
        }
    }

    $lockedPermissions = ['dashboard.view', 'notifications.view', 'profile.view', 'profile.update', 'settings.view'];
@endphp

<div wire:key="role-permissions-{{ md5(implode('|', $editableRoles)) }}" x-data="{
    activeRole: @js($selectedRole),
    roles: @js($editableRoles),
    baseRoles: @js($baseRoles),
    roleLabels: @js($roleDisplayNames),
    roleDescriptions: @js($roleDescriptions),
    roleUserCounts: @js($roleUserCounts),
    groups: @js($groups),
    permissionLabels: @js($permissionLabels),
    rolePermissions: @js($initialRolePermissions),
    initialRolePermissions: @js($initialRolePermissions),
    rolePresets: @js($rolePresetPermissions),
    lockedPermissions: @js($lockedPermissions),
    totalEditableCount: @js(count($editablePermissions)),
    pendingRole: null,
    renameDraft: '',
    showDiscardModal: false,
    showDeleteRoleModal: false,
    showSaveModal: false,
    isManagingRole: false,
    isSaving: false,
    saveSummary: { added: [], removed: [] },
    showCreateRoleModal: false,
    init() {
        if (!this.activeRole && this.roles.length) {
            this.activeRole = this.roles[0];
        }

        this.syncRoleDraft();
    },
    openCreateRoleModal() {
        this.showCreateRoleModal = true;
        this.$nextTick(() => document.getElementById('new-role-name')?.focus());
    },
    async closeCreateRoleModal() {
        this.showCreateRoleModal = false;
        await this.$wire.resetCreateRoleForm();
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
    hasPreset(role = this.activeRole) {
        return Object.prototype.hasOwnProperty.call(this.rolePresets, role);
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
    roleDescription(role = this.activeRole) {
        return this.roleDescriptions[role] || 'Custom database role managed through shared permission groups.';
    },
    isBaseRole(role = this.activeRole) {
        return this.baseRoles.includes(role);
    },
    assignedUserCount(role = this.activeRole) {
        return this.roleUserCounts[role] || 0;
    },
    canManageRoleIdentity(role = this.activeRole) {
        return !this.isBaseRole(role) && this.assignedUserCount(role) === 0 && !this.hasChanges(role);
    },
    groupGrantedCount(permissions, role = this.activeRole) {
        return permissions.filter((permission) => this.current(role).includes(permission)).length;
    },
    syncRoleDraft(role = this.activeRole) {
        this.renameDraft = this.roleLabel(role);
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
        this.syncRoleDraft(role);
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
        if (!this.hasPreset()) {
            window.dispatchEvent(new CustomEvent('toast', {
                detail: {
                    message: `No preset is defined for ${this.roleLabel(this.activeRole)}.`,
                    type: 'info',
                }
            }));
            return;
        }

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
    async submitRoleRename() {
        if (!this.canManageRoleIdentity() || this.isManagingRole) {
            return;
        }

        const proposedName = this.renameDraft.trim();

        if (!proposedName) {
            window.dispatchEvent(new CustomEvent('toast', {
                detail: {
                    message: 'Role name is required.',
                    type: 'error',
                }
            }));
            return;
        }

        this.isManagingRole = true;

        try {
            await this.$wire.renameRole(this.activeRole, proposedName);
        } catch (error) {
            console.error('[RolePermissions] renameRole failed:', error);
            window.dispatchEvent(new CustomEvent('toast', {
                detail: {
                    message: 'Unable to rename role. Please try again.',
                    type: 'error',
                }
            }));
        } finally {
            this.isManagingRole = false;
        }
    },
    openDeleteRoleModal() {
        if (!this.canManageRoleIdentity() || this.isManagingRole) {
            return;
        }

        this.showDeleteRoleModal = true;
    },
    async confirmDeleteRole() {
        if (!this.canManageRoleIdentity() || this.isManagingRole) {
            return;
        }

        this.isManagingRole = true;
        this.showDeleteRoleModal = false;

        try {
            await this.$wire.deleteRole(this.activeRole);
        } catch (error) {
            console.error('[RolePermissions] deleteRole failed:', error);
            window.dispatchEvent(new CustomEvent('toast', {
                detail: {
                    message: 'Unable to delete role. Please try again.',
                    type: 'error',
                }
            }));
        } finally {
            this.isManagingRole = false;
        }
    },
}" x-init="init()" x-on:role-created.window="closeCreateRoleModal()"
    class="flex min-h-[calc(100vh-8rem)] flex-col gap-5 pb-28">
    <div class="grid gap-5">
        <div class="rounded-xl border border-gray-200 bg-white px-6 py-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Role Permission Management</h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Configure the editable permissions for assignable database roles. Dashboard, notifications, profile, and
                        settings stay available to all managed roles and are not part of this editor.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" @click.prevent="openCreateRoleModal()"
                        class="inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-cyan-700">
                        Add Custom Role
                    </button>
                </div>
            </div>

        </div>
    </div>

    @if ($editableRoles === [])
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-8 text-sm text-amber-800 shadow-sm">
            No editable roles exist yet. Use the create role button above to add the first assignable role.
        </div>
    @else
        <div class="grid flex-1 gap-5 xl:grid-cols-[240px_minmax(0,1fr)]">
            <div class="space-y-2">
                @foreach ($editableRoles as $role)
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
                        <p class="mt-1 text-xs leading-relaxed"
                            :class="activeRole === '{{ $role }}' ? 'text-cyan-700' : 'text-gray-500'">
                            {{ $roleDescriptions[$role] ?? 'Custom database role managed through shared permission groups.' }}
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ in_array($role, $baseRoles, true) ? 'bg-slate-100 text-slate-600' : 'bg-cyan-50 text-cyan-700' }}">
                                {{ in_array($role, $baseRoles, true) ? 'Base role' : 'Custom role' }}
                            </span>
                            @if (($roleUserCounts[$role] ?? 0) > 0)
                                <span
                                    class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700">
                                    {{ $roleUserCounts[$role] }} assigned
                                </span>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>

            <div class="space-y-3">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 bg-gray-50 px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-semibold text-gray-900" x-text="roleLabel(activeRole)">
                                    </h3>
                                    <span x-show="isBaseRole()"
                                        class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                                        Base role
                                    </span>
                                    <span x-show="!isBaseRole()"
                                        class="rounded-full bg-cyan-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-cyan-700">
                                        Custom role
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-600" x-text="roleDescription(activeRole)"></p>
                            </div>

                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                                <span x-text="assignedUserCount()"></span> assigned
                            </span>
                        </div>

                        <p class="mt-3 text-xs text-gray-400">
                            Role key: <span class="font-mono" x-text="activeRole"></span>
                        </p>
                    </div>

                    <div class="space-y-4 px-5 py-4">
                        <template x-if="isBaseRole()">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                                Base roles stay available to the system. You can keep editing their permissions below,
                                but rename and delete are locked.
                            </div>
                        </template>

                        <template x-if="!isBaseRole()">
                            <div class="space-y-4">
                                <div x-show="hasChanges()"
                                    class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                    Save or discard the current permission draft before renaming or deleting this role.
                                </div>

                                <div x-show="assignedUserCount() > 0"
                                    class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                    This role is currently assigned to <span class="font-semibold"
                                        x-text="assignedUserCount()"></span> user(s). Unassign it before renaming or
                                    deleting it.
                                </div>

                                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                                    <form class="space-y-2" @submit.prevent="submitRoleRename()">
                                        <label for="rename-role-name" class="block text-sm font-medium text-gray-700">
                                            Rename role
                                        </label>
                                        <input id="rename-role-name" type="text" x-model="renameDraft"
                                            placeholder="Enter a new role name"
                                            :disabled="!canManageRoleIdentity() || isManagingRole"
                                            class="w-full rounded-xl border-gray-300 focus:border-cyan-500 focus:ring-cyan-500 disabled:cursor-not-allowed disabled:bg-gray-100">
                                        <p class="text-xs text-gray-500">
                                            The saved role key will be normalized to lowercase slug format.
                                        </p>
                                    </form>

                                    <div class="flex items-center gap-2">
                                        <button type="button" @click.prevent="submitRoleRename()"
                                            :disabled="!canManageRoleIdentity() || isManagingRole || !renameDraft.trim()"
                                            class="inline-flex items-center rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-2 text-sm font-medium text-cyan-700 transition hover:bg-cyan-100 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400">
                                            Rename Role
                                        </button>
                                        <button type="button" @click.prevent="openDeleteRoleModal()"
                                            :disabled="!canManageRoleIdentity() || isManagingRole"
                                            class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400">
                                            Delete Role
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                @foreach ($groups as $groupName => $permissions)
                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-5 py-2.5">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                {{ $groupName }}
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
                                                x-show="hasPreset(activeRole) && rolePresets[activeRole].includes('{{ $permission }}')">
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
                                            :class="current().includes('{{ $permission }}') ? 'translate-x-4' :
                                                'translate-x-0'">
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
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back to Settings
                </button>

                <div class="flex items-center gap-2">
                    <button type="button" @click.prevent="restorePreset()" :disabled="!hasPreset()"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-300 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400">
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
    @endif

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

    <div x-cloak x-show="showCreateRoleModal" x-transition.opacity @keydown.escape.window="if (showCreateRoleModal) closeCreateRoleModal()"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 px-4 py-6">
        <div class="absolute inset-0" @click.prevent="closeCreateRoleModal()"></div>
        <div class="relative w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Create Custom Role</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Add a new assignable database role and optionally clone permissions from an existing one.
                    </p>
                </div>
                <button type="button" @click.prevent="closeCreateRoleModal()"
                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700"
                    aria-label="Close create role modal">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="createRole" class="mt-6 space-y-4">
                <div>
                    <label for="new-role-name" class="mb-1 block text-sm font-medium text-gray-700">Role name</label>
                    <input id="new-role-name" type="text" wire:model.live="newRoleForm.name"
                        placeholder="e.g. auditor or branch-supervisor"
                        class="w-full rounded-xl border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
                    <p class="mt-1 text-xs text-gray-500">
                        The name is saved as a lowercase slug, such as <span class="font-medium">branch-supervisor</span>.
                    </p>
                    @error('newRoleForm.name')
                        <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="new-role-template" class="mb-1 block text-sm font-medium text-gray-700">Start with
                        permissions from</label>
                    <select id="new-role-template" wire:model.live="newRoleForm.template"
                        class="w-full rounded-xl border-gray-300 focus:border-cyan-500 focus:ring-cyan-500">
                        <option value="">Blank role with base dashboard/profile access</option>
                        @foreach ($editableRoles as $role)
                            <option value="{{ $role }}">{{ $roleDisplayNames[$role] ?? ucfirst($role) }}</option>
                        @endforeach
                    </select>
                    @error('newRoleForm.template')
                        <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-xl border border-cyan-100 bg-cyan-50 px-4 py-3 text-sm text-cyan-800">
                    New roles can be assigned immediately in Officers after creation.
                </div>

                <div class="flex justify-end gap-3 border-t border-gray-200 pt-4">
                    <button type="button" @click.prevent="closeCreateRoleModal()"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:bg-cyan-300"
                        wire:loading.attr="disabled" wire:target="createRole">
                        <span wire:loading.remove wire:target="createRole">Create Role</span>
                        <span wire:loading wire:target="createRole">Creating...</span>
                    </button>
                </div>
            </form>
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

    <div x-cloak x-show="showDeleteRoleModal" x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 px-4 py-6">
        <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start gap-4">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6 18 18 6M6 6l12 12M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-semibold text-gray-900">Delete Custom Role?</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        This will permanently remove <span class="font-medium" x-text="roleLabel(activeRole)"></span>
                        from the available assignable roles.
                    </p>
                    <p class="mt-1 text-xs text-gray-400">
                        Current key: <span class="font-mono" x-text="activeRole"></span>
                    </p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click.prevent="showDeleteRoleModal = false"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="button" @click.prevent="confirmDeleteRole()" :disabled="isManagingRole"
                    class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-rose-300">
                    Confirm Delete
                </button>
            </div>
        </div>
    </div>
</div>
