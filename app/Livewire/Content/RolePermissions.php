<?php

namespace App\Livewire\Content;

use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissions extends Component
{
    public string $selectedRole = 'manager';

    public array $rolePermissions = [];

    public function mount(): void
    {
        abort_unless($this->isAdmin(), 403);

        $this->selectedRole = PermissionCatalog::officerRoles()[0];
        $this->bootstrapPermissions();
        $this->loadRolePermissions();
    }

    public function selectRole(string $role): void
    {
        if (! in_array($role, PermissionCatalog::officerRoles(), true)) {
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
        $this->rolePermissions[$this->selectedRole] = PermissionCatalog::rolePresets()[$this->selectedRole] ?? [];
        $this->dispatch('toast', [
            'message' => 'Preset restored for ' . (PermissionCatalog::roleDisplayNames()[$this->selectedRole] ?? ucfirst($this->selectedRole)) . '. Save to apply it.',
            'type' => 'info',
        ]);
    }

    public function saveRolePermissions(?string $role = null, ?array $permissions = null): void
    {
        abort_unless($this->isAdmin(), 403);

        // Clear stale cache before bootstrapping so findOrCreate uses fresh DB data.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->bootstrapPermissions();

        $role = $role ?: $this->selectedRole;
        $permissions = $permissions ?: ($this->rolePermissions[$role] ?? []);

        $lockedPermissions = [
            'dashboard.view',
            'notifications.view',
            'profile.view',
            'profile.update',
            'settings.view',
        ];

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
            'groups' => PermissionCatalog::groups(),
            'roleDisplayNames' => PermissionCatalog::roleDisplayNames(),
            'roleDescriptions' => PermissionCatalog::roleDescriptions(),
            'rolePresets' => PermissionCatalog::rolePresets(),
        ]);
    }

    private function bootstrapPermissions(): void
    {
        // Bootstrap editable permissions + the system-locked ones that are always assigned.
        $allToBootstrap = array_unique(array_merge(
            PermissionCatalog::allPermissions(),
            ['dashboard.view', 'notifications.view', 'profile.view', 'profile.update', 'settings.view'],
        ));

        foreach ($allToBootstrap as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (PermissionCatalog::officerRoles() as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function loadRolePermissions(): void
    {
        foreach (PermissionCatalog::officerRoles() as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $this->rolePermissions[$roleName] = $role->permissions()->pluck('name')->sort()->values()->all();
        }
    }

    private function isAdmin(): bool
    {
        $user = Auth::user();

        return (bool) ($user && $user->hasRole('admin'));
    }
}
