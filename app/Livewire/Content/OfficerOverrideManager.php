<?php

namespace App\Livewire\Content;

use App\Models\OfficerPermissionOverride;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

class OfficerOverrideManager extends Component
{
    public int $officerId;

    public string $search = '';

    public array $overrides = [];

    /** Snapshot of the last-persisted state — used for dirty detection. */
    public array $savedOverrides = [];

    public bool $showOnlyOverrides = false;

    public bool $confirmingClearAll = false;

    protected $listeners = ['do-save' => 'save'];

    public function mount(int $officerId): void
    {
        $this->authorizeAccess();
        $this->officerId = $officerId;
        $this->loadOverrides();
    }

    public function setPermissionState(string $permission, string $state): void
    {
        if (! in_array($permission, PermissionCatalog::allPermissions(), true)) {
            return;
        }

        if (! in_array($state, ['inherit', OfficerPermissionOverride::EFFECT_ALLOW, OfficerPermissionOverride::EFFECT_DISALLOW], true)) {
            return;
        }

        if ($state === 'inherit') {
            unset($this->overrides[$permission]);
            $this->broadcastDirty();

            return;
        }

        $this->overrides[$permission] = $state;
        $this->broadcastDirty();
    }

    public function save(): void
    {
        $this->authorizeAccess();

        $officer = $this->getOfficer();
        $payload = collect($this->overrides)
            ->filter(fn($effect, $permission) => in_array($permission, PermissionCatalog::allPermissions(), true)
                && in_array($effect, [OfficerPermissionOverride::EFFECT_ALLOW, OfficerPermissionOverride::EFFECT_DISALLOW], true))
            ->map(fn(string $effect, string $permission) => [
                'user_id' => $officer->id,
                'permission' => $permission,
                'effect' => $effect,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($officer, $payload): void {
            OfficerPermissionOverride::query()
                ->where('user_id', $officer->id)
                ->delete();

            if ($payload !== []) {
                OfficerPermissionOverride::query()->insert($payload);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        OfficerPermissionOverride::forgetAllCachedEffects($officer->id);
        $this->loadOverrides();

        $this->dispatch('permission-override-saved', message: 'Officer permission overrides saved.');
        $this->broadcastDirty();
    }

    public function clearAll(): void
    {
        $this->overrides = [];
    }

    public function confirmClearAll(): void
    {
        $this->confirmingClearAll = true;
    }

    public function doClearAll(): void
    {
        $this->overrides = [];
        $this->confirmingClearAll = false;
        $this->broadcastDirty();
    }

    public function cancelClearAll(): void
    {
        $this->confirmingClearAll = false;
    }

    public function render()
    {
        $officer = $this->getOfficer();
        $rolePermissions = $officer->getPermissionsViaRoles()->pluck('name')->all();
        $search = strtolower(trim($this->search));

        $groups = collect(PermissionCatalog::groups())
            ->map(function (array $permissions) use ($search): array {
                if ($search !== '') {
                    $permissions = array_values(array_filter($permissions, function (string $permission) use ($search): bool {
                        return str_contains(strtolower($permission), $search)
                            || str_contains(strtolower(PermissionCatalog::formatLabel($permission)), $search);
                    }));
                }

                if ($this->showOnlyOverrides) {
                    $permissions = array_values(array_filter(
                        $permissions,
                        fn(string $p): bool => array_key_exists($p, $this->overrides)
                    ));
                }

                return $permissions;
            })
            ->filter(fn(array $permissions): bool => $permissions !== [])
            ->all();

        return view('livewire.content.officer-override-manager', [
            'groups' => $groups,
            'officer' => $officer,
            'rolePermissions' => $rolePermissions,
            'roleName' => $officer->getRoleNames()->first(),
            'allowCount' => collect($this->overrides)->filter(
                fn(string $effect): bool => $effect === OfficerPermissionOverride::EFFECT_ALLOW
            )->count(),
            'denyCount' => collect($this->overrides)->filter(
                fn(string $effect): bool => $effect === OfficerPermissionOverride::EFFECT_DISALLOW
            )->count(),
            'isDirty' => $this->overrides !== $this->savedOverrides,
            'totalOverrides' => count($this->overrides),
        ]);
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::check() && Auth::user()->can('officers.manage-permissions'), 403);
    }

    private function getOfficer(): User
    {
        $officer = User::query()
            ->with(['personalData', 'permissionOverrides'])
            ->findOrFail($this->officerId);

        abort_unless($officer->is_officer, 404);

        return $officer;
    }

    private function loadOverrides(): void
    {
        $loaded = $this->getOfficer()
            ->permissionOverrides
            ->pluck('effect', 'permission')
            ->all();

        $this->overrides = $loaded;
        $this->savedOverrides = $loaded;
    }

    private function broadcastDirty(): void
    {
        $this->dispatch('permission-dirty', dirty: $this->overrides !== $this->savedOverrides);
    }
}
