<?php

namespace App\Livewire\Content;

use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role Permission Manager
 *
 * Accessible from Settings (admin only). Admins can choose any officer role and
 * edit the Spatie role-level permissions that all officers with that role inherit.
 */
class RolePermissionManager extends Component
{
    public string $selectedRole = 'manager';

    public array $selectedPermissions = [];

    public array $expandedGroups = [];

    public const EDITABLE_ROLES = ['manager', 'secretary', 'loan', 'treasurer'];

    public function mount(): void
    {
        abort_unless(Auth::check() && Auth::user()->hasRole('admin'), 403);
        $this->loadRolePermissions();
    }

    public function updatedSelectedRole(): void
    {
        $this->loadRolePermissions();
        $this->expandedGroups = [];
        $this->resetValidation();
    }

    public function loadRolePermissions(): void
    {
        $role = Role::findByName($this->selectedRole, 'web');
        $this->selectedPermissions = $role
            ? $role->permissions->pluck('name')->all()
            : [];
    }

    public function saveRolePermissions(): void
    {
        abort_unless(Auth::check() && Auth::user()->hasRole('admin'), 403);

        $role = Role::findByName($this->selectedRole, 'web');
        if (! $role) {
            $this->dispatch('toast', ['message' => 'Role not found.', 'type' => 'error']);
            return;
        }

        $normalized = array_values(array_filter(
            (array) $this->selectedPermissions,
            fn($p) => is_string($p) && $p !== ''
        ));

        $role->syncPermissions($normalized);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->dispatch('toast', [
            'message' => ucfirst($this->selectedRole) . ' role permissions saved.',
            'type' => 'success',
        ]);
    }

    public function applyPreset(): void
    {
        abort_unless(Auth::check() && Auth::user()->hasRole('admin'), 403);
        $this->selectedPermissions = PermissionCatalog::rolePresets()[$this->selectedRole] ?? [];
    }

    public function clearAll(): void
    {
        $this->selectedPermissions = [];
    }

    public function toggleGroup(string $groupName): void
    {
        $groupPermissions = PermissionCatalog::groups()[$groupName] ?? [];
        $allSelected = collect($groupPermissions)
            ->every(fn($perm) => in_array($perm, $this->selectedPermissions, true));

        if ($allSelected) {
            $this->selectedPermissions = array_values(
                array_diff($this->selectedPermissions, $groupPermissions)
            );
        } else {
            $this->selectedPermissions = array_values(
                array_unique(array_merge($this->selectedPermissions, $groupPermissions))
            );
        }
    }

    public function toggleExpandedGroup(string $groupName): void
    {
        if (in_array($groupName, $this->expandedGroups, true)) {
            $this->expandedGroups = array_values(
                array_filter($this->expandedGroups, fn($n) => $n !== $groupName)
            );
        } else {
            $this->expandedGroups[] = $groupName;
        }
    }

    #[Computed]
    public function isGroupAllSelected(): \Closure
    {
        return function (string $groupName): bool {
            $perms = PermissionCatalog::groups()[$groupName] ?? [];
            return collect($perms)->every(fn($p) => in_array($p, $this->selectedPermissions, true));
        };
    }

    #[Computed]
    public function isGroupPartiallySelected(): \Closure
    {
        return function (string $groupName): bool {
            $perms = PermissionCatalog::groups()[$groupName] ?? [];
            $count = collect($perms)->filter(fn($p) => in_array($p, $this->selectedPermissions, true))->count();
            return $count > 0 && $count < count($perms);
        };
    }

    public function goToSettings(): void
    {
        $this->dispatch('sectionChanged', 'settings');
    }

    public function render()
    {
        return view('livewire.content.role-permission-manager', [
            'permissionsByGroup' => PermissionCatalog::groups(),
            'roleLabels' => [
                'manager'   => 'Manager (Chairman)',
                'secretary' => 'Secretary',
                'loan'      => 'Loan Officer',
                'treasurer' => 'Treasurer',
            ],
        ]);
    }
}
