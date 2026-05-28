<?php

namespace App\Livewire\Content;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissions extends Component
{
    private const RESERVED_ROLES = ['admin', 'developer', 'member', 'inactive', 'terminated'];

    public string $selectedRole = '';

    public array $rolePermissions = [];

    public array $editableRoles = [];

    public array $newRoleForm = [
        'name' => '',
        'template' => '',
    ];

    public function mount(): void
    {
        abort_unless($this->isAdmin(), 403);

        $this->bootstrapPermissions();
        $this->loadRolePermissions();
    }

    public function selectRole(string $role): void
    {
        if (! in_array($role, $this->editableRoles, true)) {
            return;
        }

        $this->selectedRole = $role;
    }

    public function togglePermission(string $permission): void
    {
        if (! in_array($permission, PermissionCatalog::allPermissions(), true)) {
            return;
        }

        $permissions = $this->rolePermissions[$this->selectedRole] ?? [];

        if (in_array($permission, $permissions, true)) {
            $permissions = array_values(array_diff($permissions, [$permission]));
        } else {
            $permissions[] = $permission;
        }

        sort($permissions);
        $this->rolePermissions[$this->selectedRole] = $permissions;
    }

    public function restorePreset(): void
    {
        $presetPermissions = PermissionCatalog::rolePresets()[$this->selectedRole] ?? null;

        if ($presetPermissions === null) {
            $this->dispatch('toast', [
                'message' => 'No preset is defined for ' . ($this->roleDisplayNames()[$this->selectedRole] ?? ucfirst($this->selectedRole)) . '.',
                'type' => 'info',
            ]);

            return;
        }

        $this->rolePermissions[$this->selectedRole] = $presetPermissions;
        $this->dispatch('toast', [
            'message' => 'Preset restored for ' . ($this->roleDisplayNames()[$this->selectedRole] ?? ucfirst($this->selectedRole)) . '. Save to apply it.',
            'type' => 'info',
        ]);
    }

    public function createRole(): void
    {
        abort_unless($this->isAdmin(), 403);

        $this->bootstrapPermissions();
        $this->refreshEditableRoles();

        $validated = $this->validate([
            'newRoleForm.name' => ['required', 'string', 'max:60'],
            'newRoleForm.template' => ['nullable', 'string', Rule::in(['', ...$this->editableRoles])],
        ], [
            'newRoleForm.name.required' => 'Role name is required.',
            'newRoleForm.template.in' => 'Selected template role is invalid.',
        ]);

        $roleName = $this->normalizeRoleName($validated['newRoleForm']['name']);

        if ($roleName === '') {
            $this->addError('newRoleForm.name', 'Role name must contain letters or numbers.');

            return;
        }

        if (in_array($roleName, self::RESERVED_ROLES, true)) {
            $this->addError('newRoleForm.name', 'That role name is reserved by the system.');

            return;
        }

        if (Role::query()->where('name', $roleName)->where('guard_name', 'web')->exists()) {
            $this->addError('newRoleForm.name', 'That role already exists.');

            return;
        }

        $templateRole = $validated['newRoleForm']['template'] ?: null;
        $assignedPermissions = $templateRole
            ? Role::findByName($templateRole, 'web')->permissions()->pluck('name')->all()
            : [];

        $assignedPermissions = array_values(array_unique(array_merge(
            $assignedPermissions,
            $this->lockedPermissions(),
        )));

        $role = Role::create([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($assignedPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->refreshEditableRoles();
        $this->selectedRole = $roleName;
        $this->rolePermissions[$roleName] = $role->permissions()->pluck('name')->sort()->values()->all();
        $this->newRoleForm = [
            'name' => '',
            'template' => '',
        ];
        $this->resetValidation(['newRoleForm.name', 'newRoleForm.template']);

        $templateLabel = $templateRole
            ? ($this->roleDisplayNames()[$templateRole] ?? ucfirst($templateRole))
            : 'base access';

        $this->dispatch('toast', [
            'message' => 'Role ' . ($this->roleDisplayNames()[$roleName] ?? ucfirst($roleName)) . ' created from ' . $templateLabel . '.',
            'type' => 'success',
        ]);
        $this->dispatch('role-created');
    }

    public function resetCreateRoleForm(): void
    {
        $this->newRoleForm = [
            'name' => '',
            'template' => '',
        ];

        $this->resetValidation(['newRoleForm.name', 'newRoleForm.template']);
    }

    public function renameRole(string $role, string $proposedName): void
    {
        abort_unless($this->isAdmin(), 403);

        $this->refreshEditableRoles();

        if (! $this->canManageRoleIdentity($role, 'rename')) {
            return;
        }

        $newRoleName = $this->normalizeRoleName($proposedName);

        if ($newRoleName === '') {
            $this->dispatch('toast', [
                'message' => 'Role name must contain letters or numbers.',
                'type' => 'error',
            ]);

            return;
        }

        if ($newRoleName === $role) {
            $this->dispatch('toast', [
                'message' => 'Role name is unchanged.',
                'type' => 'info',
            ]);

            return;
        }

        if (in_array($newRoleName, array_merge(self::RESERVED_ROLES, $this->baseRoles()), true)) {
            $this->dispatch('toast', [
                'message' => 'That role name is reserved by the system.',
                'type' => 'error',
            ]);

            return;
        }

        if (Role::query()->where('name', $newRoleName)->where('guard_name', 'web')->exists()) {
            $this->dispatch('toast', [
                'message' => 'That role already exists.',
                'type' => 'error',
            ]);

            return;
        }

        $roleModel = Role::findByName($role, 'web');
        $currentPermissions = $this->rolePermissions[$role] ?? $roleModel->permissions()->pluck('name')->sort()->values()->all();

        $roleModel->update(['name' => $newRoleName]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        unset($this->rolePermissions[$role]);
        $this->rolePermissions[$newRoleName] = $currentPermissions;

        if (($this->newRoleForm['template'] ?? '') === $role) {
            $this->newRoleForm['template'] = $newRoleName;
        }

        $this->refreshEditableRoles();
        $this->selectedRole = $newRoleName;

        $this->dispatch('toast', [
            'message' => 'Role renamed to ' . ($this->roleDisplayNames()[$newRoleName] ?? ucfirst($newRoleName)) . '.',
            'type' => 'success',
        ]);
    }

    public function deleteRole(string $role): void
    {
        abort_unless($this->isAdmin(), 403);

        $this->refreshEditableRoles();

        if (! $this->canManageRoleIdentity($role, 'delete')) {
            return;
        }

        $roleModel = Role::findByName($role, 'web');
        $roleModel->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        unset($this->rolePermissions[$role]);

        if (($this->newRoleForm['template'] ?? '') === $role) {
            $this->newRoleForm['template'] = '';
        }

        $this->refreshEditableRoles();

        $this->dispatch('toast', [
            'message' => 'Role ' . ucwords(str_replace(['-', '_'], ' ', $role)) . ' deleted.',
            'type' => 'success',
        ]);
    }

    public function saveRolePermissions(?string $role = null, ?array $permissions = null): void
    {
        abort_unless($this->isAdmin(), 403);

        // Clear stale cache before bootstrapping so findOrCreate uses fresh DB data.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->bootstrapPermissions();
        $this->refreshEditableRoles();

        $role = $role ?: $this->selectedRole;

        if ($role === '' || ! in_array($role, $this->editableRoles, true)) {
            return;
        }

        $permissions = $permissions ?: ($this->rolePermissions[$role] ?? []);

        $lockedPermissions = $this->lockedPermissions();

        // Allow both user-configurable editable permissions and system-locked ones.
        $allAllowed = array_unique(array_merge(
            PermissionCatalog::allPermissions(),
            $lockedPermissions,
        ));

        $roleModel = Role::findOrCreate($role, 'web');
        $permissions = array_values(array_intersect(
            $allAllowed,
            array_unique(array_merge($permissions, $lockedPermissions))
        ));

        // Clear cache again so syncPermissions can find all bootstrapped permissions.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $roleModel->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->rolePermissions[$role] = $permissions;
        $this->selectedRole = $role;
    }

    public function goToSettings(): void
    {
        $this->dispatch('sectionChanged', 'settings');
    }

    public function render()
    {
        return view('livewire.content.role-permissions', [
            'editableRoles' => $this->editableRoles,
            'groups' => PermissionCatalog::groups(),
            'baseRoles' => array_values(array_intersect($this->editableRoles, $this->baseRoles())),
            'roleUserCounts' => $this->roleUserCounts(),
            'roleDisplayNames' => $this->roleDisplayNames(),
            'roleDescriptions' => $this->roleDescriptions(),
            'rolePresets' => $this->rolePresets(),
        ]);
    }

    private function bootstrapPermissions(): void
    {
        // Bootstrap editable permissions + the system-locked ones that are always assigned.
        $allToBootstrap = array_unique(array_merge(
            PermissionCatalog::allPermissions(),
            $this->lockedPermissions(),
        ));

        foreach ($allToBootstrap as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (array_keys(PermissionCatalog::rolePresets()) as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function loadRolePermissions(): void
    {
        $this->refreshEditableRoles();

        foreach ($this->editableRoles as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $this->rolePermissions[$roleName] = $role->permissions()->pluck('name')->sort()->values()->all();
        }
    }

    private function refreshEditableRoles(): void
    {
        $this->editableRoles = Role::query()
            ->whereNotIn('name', self::RESERVED_ROLES)
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        if ($this->selectedRole === '' || ! in_array($this->selectedRole, $this->editableRoles, true)) {
            $this->selectedRole = $this->editableRoles[0] ?? '';
        }
    }

    private function roleDisplayNames(): array
    {
        $catalog = PermissionCatalog::roleDisplayNames();

        return collect($this->editableRoles)
            ->mapWithKeys(fn(string $role) => [
                $role => $catalog[$role] ?? ucwords(str_replace(['-', '_'], ' ', $role)),
            ])
            ->all();
    }

    private function roleDescriptions(): array
    {
        $catalog = PermissionCatalog::roleDescriptions();

        return collect($this->editableRoles)
            ->mapWithKeys(fn(string $role) => [
                $role => $catalog[$role] ?? 'Custom database role managed through shared permission groups.',
            ])
            ->all();
    }

    private function rolePresets(): array
    {
        $catalog = PermissionCatalog::rolePresets();

        return collect($this->editableRoles)
            ->filter(fn(string $role) => array_key_exists($role, $catalog))
            ->mapWithKeys(fn(string $role) => [$role => $catalog[$role]])
            ->all();
    }

    private function baseRoles(): array
    {
        return array_values(array_unique(array_keys(PermissionCatalog::rolePresets())));
    }

    private function roleUserCounts(): array
    {
        return collect($this->editableRoles)
            ->mapWithKeys(fn(string $role) => [$role => User::query()->role($role)->count()])
            ->all();
    }

    private function isAdmin(): bool
    {
        $user = Auth::user();

        return (bool) ($user && $user->hasRole('admin'));
    }

    private function canManageRoleIdentity(string $role, string $action): bool
    {
        if (! in_array($role, $this->editableRoles, true)) {
            return false;
        }

        if (in_array($role, $this->baseRoles(), true)) {
            $this->dispatch('toast', [
                'message' => 'Base roles can be modified, but they cannot be ' . $action . 'd.',
                'type' => 'error',
            ]);

            return false;
        }

        $assignedUsers = $this->roleUserCounts()[$role] ?? 0;

        if ($assignedUsers > 0) {
            $this->dispatch('toast', [
                'message' => 'Unassign ' . ($this->roleDisplayNames()[$role] ?? ucfirst($role)) . ' from ' . $assignedUsers . ' user(s) before you can ' . $action . ' it.',
                'type' => 'error',
            ]);

            return false;
        }

        return true;
    }

    private function normalizeRoleName(string $name): string
    {
        return Str::of($name)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9\s_-]+/', '')
            ->slug('-')
            ->value();
    }

    private function lockedPermissions(): array
    {
        return [
            'dashboard.view',
            'notifications.view',
            'profile.view',
            'profile.update',
            'settings.view',
        ];
    }
}
