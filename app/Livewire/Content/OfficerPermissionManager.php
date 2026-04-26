<?php

namespace App\Livewire\Content;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;

/**
 * Officer Permission Manager
 * 
 * Allows admins to assign granular permissions to officers (staff).
 * Officers don't have fixed roles; instead, they have individual permission checkboxes.
 * 
 * Usage:
 * - When creating an officer: show permission checkboxes
 * - When editing an officer: show current permissions pre-checked
 */
class OfficerPermissionManager extends Component
{
    public ?int $officerId = null;
    public User $officer;

    // Track selected permissions
    public $selectedPermissions = [];

    // UI state
    public bool $expanded = false;
    public array $expandedGroups = [];

    protected $rules = [];

    public function hydrate(): void
    {
        if (!is_array($this->selectedPermissions)) {
            $this->selectedPermissions = [];
        }
    }

    public function mount(?int $officerId = null): void
    {
        $user = Auth::user();

        // Permission-based gate for managing officer permissions.
        if (!$user || !$user->can('officers.manage-permissions')) {
            abort(403, 'Only admins can manage officer permissions.');
        }

        if ($officerId) {
            $this->officer = User::findOrFail($officerId);

            if (!$this->officer->is_officer) {
                abort(400, 'This user is not an officer.');
            }

            // Load current permissions
            $this->selectedPermissions = $this->officer
                ->getPermissionNames()
                ->toArray();

            // Open first permission group by default for better UX.
            $groupNames = array_keys($this->permissionsByGroup);
            if (!empty($groupNames)) {
                $this->expandedGroups = [$groupNames[0]];
            }
        }
    }

    public function toggleExpandedGroup(string $groupName): void
    {
        if (in_array($groupName, $this->expandedGroups, true)) {
            $this->expandedGroups = array_values(array_filter(
                $this->expandedGroups,
                fn($name) => $name !== $groupName
            ));

            return;
        }

        $this->expandedGroups[] = $groupName;
    }

    public function isGroupExpanded(string $groupName): bool
    {
        return in_array($groupName, $this->expandedGroups, true);
    }

    /**
     * Get permissions organized by resource (group)
     */
    #[Computed]
    public function permissionsByGroup(): array
    {
        $groups = [
            'Dashboard' => ['dashboard.view'],
            'Members' => [
                'members.view',
                'members.create',
                'members.update',
                'members.delete',
                'members.view-full-details',
            ],
            'Services' => [
                'services.view',
                'services.create',
                'services.update',
            ],
            'Loans' => [
                'loans.view',
                'loans.create',
                'loans.update',
                'loans.approve',
                'loans.reject',
                'loans.disburse',
            ],
            'Transactions' => [
                'transactions.view',
                'transactions.create',
                'transactions.update',
                'transactions.void',
                'transactions.export',
            ],
            'Deposits & Withdrawals' => [
                'deposits.view',
                'deposits.create',
                'deposits.approve',
                'deposits.reject',
                'withdrawals.view',
                'withdrawals.create',
                'withdrawals.approve',
                'withdrawals.reject',
            ],
            'Reports' => [
                'reports.view',
                'reports.export',
                'reports.financial',
                'reports.audit',
            ],
            'Settings & Logs' => [
                'settings.view',
                'settings.update',
                'logs.view',
                'logs.export',
            ],
            'Announcements' => [
                'announcements.view',
                'announcements.create',
                'announcements.update',
                'announcements.delete',
            ],
            'Staff Management' => [
                'officers.create',
                'officers.update',
                'officers.delete',
                'officers.manage-permissions',
            ],
            'Notifications & Profile' => [
                'notifications.view',
                'notifications.manage',
                'profile.view',
                'profile.update',
            ],
        ];

        // Convert to array with label formatting
        $result = [];
        foreach ($groups as $groupName => $perms) {
            $result[$groupName] = $perms;
        }

        return $result;
    }

    /**
     * Get human-readable label for permission
     */
    public function formatPermissionLabel(string $permission): string
    {
        $parts = explode('.', $permission);
        if (count($parts) === 2) {
            $resource = ucfirst($parts[0]);
            $action = match ($parts[1]) {
                'view' => 'View',
                'create' => 'Create',
                'update' => 'Edit',
                'delete' => 'Delete',
                'approve' => 'Approve',
                'reject' => 'Reject',
                'disburse' => 'Disburse',
                'void' => 'Void',
                'export' => 'Export',
                'manage-permissions' => 'Manage Permissions',
                'view-full-details' => 'View Full Details',
                default => ucfirst($parts[1]),
            };

            return "{$resource}: {$action}";
        }

        return ucfirst(str_replace('-', ' ', $permission));
    }

    /**
     * Toggle all permissions in a group
     */
    public function toggleGroup(string $groupName): void
    {
        $groupPermissions = $this->permissionsByGroup[$groupName];
        $allSelected = collect($groupPermissions)
            ->every(fn($perm) => in_array($perm, $this->selectedPermissions));

        if ($allSelected) {
            // Uncheck all in group
            $this->selectedPermissions = array_diff(
                $this->selectedPermissions,
                $groupPermissions
            );
        } else {
            // Check all in group
            $this->selectedPermissions = array_unique(
                array_merge($this->selectedPermissions, $groupPermissions)
            );
        }
    }

    /**
     * Toggle individual permission
     */
    public function togglePermission(string $permission): void
    {
        if (in_array($permission, $this->selectedPermissions)) {
            $this->selectedPermissions = array_filter(
                $this->selectedPermissions,
                fn($p) => $p !== $permission
            );
        } else {
            $this->selectedPermissions[] = $permission;
        }
    }

    /**
     * Save permissions to officer
     */
    public function savePermissions(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('officers.manage-permissions')) {
            abort(403, 'You do not have permission to manage officer permissions.');
        }

        // Livewire can send false when checkbox arrays become empty; normalize safely.
        $normalizedPermissions = array_values(array_filter((array) $this->selectedPermissions, fn($perm) => is_string($perm) && $perm !== ''));
        $this->selectedPermissions = $normalizedPermissions;

        $this->officer->syncPermissions($this->selectedPermissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->dispatch('permission-saved', 'Permissions updated for ' . $this->officer->email);
        $this->dispatch('permission-manager-close');
    }

    /**
     * Quick preset: Manager permissions
     */
    public function presetManager(): void
    {
        $this->selectedPermissions = [
            'dashboard.view',
            'members.view',
            'members.create',
            'members.update',
            'members.delete',
            'members.view-full-details',
            'services.view',
            'services.create',
            'services.update',
            'loans.view',
            'loans.create',
            'loans.update',
            'loans.approve',
            'loans.reject',
            'loans.disburse',
            'transactions.view',
            'transactions.create',
            'transactions.update',
            'transactions.export',
            'deposits.view',
            'deposits.create',
            'deposits.approve',
            'withdrawals.view',
            'withdrawals.create',
            'withdrawals.approve',
            'reports.view',
            'reports.export',
            'announcements.view',
            'announcements.create',
            'announcements.update',
            'announcements.delete',
            'settings.view',
            'settings.update',
            'logs.view',
            'logs.export',
            'notifications.view',
            'notifications.manage',
            'profile.view',
            'profile.update',
        ];
    }

    /**
     * Quick preset: Treasurer permissions
     */
    public function presetTreasurer(): void
    {
        $this->selectedPermissions = [
            'dashboard.view',
            'members.view',
            'services.view',
            'services.create',
            'services.update',
            'loans.view',
            'transactions.view',
            'transactions.create',
            'transactions.update',
            'transactions.export',
            'deposits.view',
            'deposits.create',
            'deposits.approve',
            'deposits.reject',
            'withdrawals.view',
            'withdrawals.create',
            'withdrawals.approve',
            'withdrawals.reject',
            'reports.view',
            'reports.export',
            'reports.financial',
            'announcements.view',
            'settings.view',
            'logs.view',
            'notifications.view',
            'profile.view',
            'profile.update',
        ];
    }

    /**
     * Quick preset: Loan Officer permissions
     */
    public function presetLoanOfficer(): void
    {
        $this->selectedPermissions = [
            'dashboard.view',
            'members.view',
            'members.view-full-details',
            'services.view',
            'services.create',
            'services.update',
            'loans.view',
            'loans.create',
            'loans.update',
            'loans.approve',
            'loans.reject',
            'loans.disburse',
            'transactions.view',
            'reports.view',
            'reports.export',
            'announcements.view',
            'settings.view',
            'notifications.view',
            'profile.view',
            'profile.update',
        ];
    }

    /**
     * Quick preset: Secretary permissions
     */
    public function presetSecretary(): void
    {
        $this->selectedPermissions = [
            'dashboard.view',
            'members.view',
            'members.create',
            'members.update',
            'services.view',
            'loans.view',
            'transactions.view',
            'transactions.export',
            'reports.view',
            'reports.export',
            'announcements.view',
            'announcements.create',
            'announcements.update',
            'settings.view',
            'notifications.view',
            'profile.view',
            'profile.update',
        ];
    }

    /**
     * Clear all permissions
     */
    public function clearAll(): void
    {
        $this->selectedPermissions = [];
    }

    /**
     * Check if a group is entirely selected
     */
    public function isGroupAllSelected(string $groupName): bool
    {
        $groupPermissions = $this->permissionsByGroup[$groupName];

        return collect($groupPermissions)
            ->every(fn($perm) => in_array($perm, $this->selectedPermissions));
    }

    /**
     * Check if a group is partially selected
     */
    public function isGroupPartiallySelected(string $groupName): bool
    {
        $groupPermissions = $this->permissionsByGroup[$groupName];
        $selectedInGroup = collect($groupPermissions)
            ->filter(fn($perm) => in_array($perm, $this->selectedPermissions))
            ->count();

        return $selectedInGroup > 0 && $selectedInGroup < count($groupPermissions);
    }

    public function render()
    {
        return view('livewire.content.officer-permission-manager');
    }
}
