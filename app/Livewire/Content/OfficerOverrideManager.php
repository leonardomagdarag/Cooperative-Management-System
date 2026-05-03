<?php

namespace App\Livewire\Content;

use App\Models\OfficerPermissionOverride;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

/**
 * Officer Override Manager
 *
 * Allows admins to set per-officer permission exceptions that layer on top of
 * the role's baseline permissions.  Each permission can be:
 *   - inherit  → use whatever the role grants (no stored override)
 *   - allow    → explicitly granted even if the role does not include it
 *   - disallow → explicitly denied even if the role includes it
 *
 * Disallow overrides always win over role grants.
 */
class OfficerOverrideManager extends Component
{
    public ?int $officerId = null;

    public User $officer;

    /** @var array<string, 'inherit'|'allow'|'disallow'> */
    public array $overrides = [];

    public array $expandedGroups = [];

    public function hydrate(): void
    {
        if (! is_array($this->overrides)) {
            $this->overrides = [];
        }
    }

    public function mount(?int $officerId = null): void
    {
        $user = Auth::user();
        if (! $user || ! $user->can('officers.manage-permissions')) {
            abort(403, 'You do not have permission to manage officer permissions.');
        }

        if ($officerId) {
            $this->officer = User::findOrFail($officerId);

            if (! $this->officer->is_officer) {
                abort(400, 'This user is not an officer.');
            }

            $this->loadOverrides();

            // Expand first group by default
            $groupNames = array_keys(PermissionCatalog::groups());
            if (! empty($groupNames)) {
                $this->expandedGroups = [$groupNames[0]];
            }
        }
    }

    private function loadOverrides(): void
    {
        // Seed every permission as 'inherit'
        $this->overrides = array_fill_keys(PermissionCatalog::all(), 'inherit');

        // Apply stored overrides
        OfficerPermissionOverride::where('user_id', $this->officer->id)
            ->get()
            ->each(function (OfficerPermissionOverride $row) {
                $this->overrides[$row->permission] = $row->effect;
            });
    }

    /** Permissions the officer's role currently grants. */
    #[Computed]
    public function rolePermissions(): array
    {
        return $this->officer->getPermissionsViaRoles()->pluck('name')->all();
    }

    public function setOverride(string $permission, string $value): void
    {
        if (! in_array($value, ['inherit', 'allow', 'disallow'], true)) {
            return;
        }

        $this->overrides[$permission] = $value;
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

    public function clearAllOverrides(): void
    {
        $this->overrides = array_fill_keys(PermissionCatalog::all(), 'inherit');
    }

    public function saveOverrides(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->can('officers.manage-permissions')) {
            abort(403);
        }

        // Guard: only admin/manager may be explicitly allowed loans.approve
        $officerRole = $this->officer->getRoleNames()->first();
        if (
            ! in_array($officerRole, ['admin', 'manager'], true) &&
            ($this->overrides['loans.approve'] ?? 'inherit') === 'allow'
        ) {
            $this->dispatch('error', message: 'Final loan approval can only be granted to admin or manager accounts.');
            return;
        }

        foreach ($this->overrides as $permission => $value) {
            if ($value === 'inherit') {
                OfficerPermissionOverride::where('user_id', $this->officer->id)
                    ->where('permission', $permission)
                    ->delete();
            } else {
                OfficerPermissionOverride::updateOrCreate(
                    ['user_id' => $this->officer->id, 'permission' => $permission],
                    ['effect' => $value]
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        OfficerPermissionOverride::clearCache();

        $this->dispatch('permission-override-saved', message: 'Overrides saved for ' . $this->officer->email);
        $this->dispatch('permission-override-close');
    }

    public function render()
    {
        return view('livewire.content.officer-override-manager', [
            'permissionsByGroup' => PermissionCatalog::groups(),
        ]);
    }
}
