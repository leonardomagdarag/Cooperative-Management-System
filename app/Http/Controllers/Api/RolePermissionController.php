<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CountersignService;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    private const RESERVED_ROLES = ['admin', 'chairman', 'developer', 'member', 'inactive', 'terminated', 'loan', 'unassigned_officer'];

    private const LOCKED_PERMISSIONS = [
        'dashboard.view',
        'notifications.view',
        'profile.view',
        'profile.update',
        'settings.view',
    ];

    public function index(): JsonResponse
    {
        abort_unless($this->isAdmin(Auth::user()), 403);

        $this->bootstrapPermissions();

        $editableRoles = Role::query()
            ->whereNotIn('name', array_values(array_diff(self::RESERVED_ROLES, ['admin'])))
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        $rolePermissions = [];
        foreach ($editableRoles as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $rolePermissions[$roleName] = $role->permissions()->where('name', '!=', 'oversight.approve')->pluck('name')->sort()->values()->all();
        }

        $roleUserCounts = collect($editableRoles)
            ->mapWithKeys(fn (string $role) => [$role => User::query()->role($role)->count()])
            ->all();

        $catalogDisplayNames = PermissionCatalog::roleDisplayNames();
        $roleDisplayNames = collect($editableRoles)
            ->mapWithKeys(fn (string $role) => [$role => $catalogDisplayNames[$role] ?? ucwords(str_replace(['-', '_'], ' ', $role))])
            ->all();

        $catalogDescriptions = PermissionCatalog::roleDescriptions();
        $roleDescriptions = collect($editableRoles)
            ->mapWithKeys(fn (string $role) => [$role => $catalogDescriptions[$role] ?? 'Custom role']);

        $baseRoles = array_values(array_unique(array_keys(PermissionCatalog::rolePresets())));
        $catalogPresets = PermissionCatalog::rolePresets();
        $rolePresets = collect($editableRoles)
            ->filter(fn (string $role) => array_key_exists($role, $catalogPresets))
            ->mapWithKeys(fn (string $role) => [$role => $catalogPresets[$role]]);

        return response()->json([
            'roles' => $editableRoles,
            'role_permissions' => $rolePermissions,
            'role_user_counts' => $roleUserCounts,
            'role_display_names' => $roleDisplayNames,
            'role_descriptions' => $roleDescriptions,
            'role_presets' => $rolePresets,
            'base_roles' => array_values(array_intersect($editableRoles, $baseRoles)),
            'groups' => $this->editablePermissionGroups(),
        ]);
    }

    public function updatePermissions(Request $request, string $role): JsonResponse
    {
        abort_unless($this->isAdmin(Auth::user()), 403);

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->bootstrapPermissions();

        $lockedPermissions = self::LOCKED_PERMISSIONS;
        $baseRoleRequiredPermissions = [
            'manager' => ['members.view', 'loans.view', 'services.view'],
            'accountant' => ['transactions.view', 'services.view', 'reports.view'],
        ];
        $roleSpecificLocked = $baseRoleRequiredPermissions[$role] ?? [];
        $allLocked = array_unique(array_merge($lockedPermissions, $roleSpecificLocked));

        $allAllowed = array_unique(array_merge(
            PermissionCatalog::allPermissions(),
            $allLocked,
        ));

        abort_unless(Role::query()->where('name', $role)->where('guard_name', 'web')->exists(), 404, 'Role not found.');
        abort_if(in_array($role, array_diff(self::RESERVED_ROLES, ['admin']), true), 422, 'System roles cannot be edited here.');
        $permissions = array_values(array_intersect(
            $allAllowed,
            array_unique(array_merge($validated['permissions'], $allLocked))
        ));
        $permissions = array_values(array_diff($permissions, ['oversight.approve']));

        // Enforce countersign
        $countersign = app(CountersignService::class);
        $pending = $countersign->request('permissions.update', [
            'role' => $role,
            'permissions' => $permissions,
        ], Auth::user());

        return response()->json([
            'message' => 'Permission update requested and requires countersign approval.',
            'request_id' => $pending->id,
            'status' => 'pending',
        ], 202);
    }

    public function storeRole(Request $request): JsonResponse
    {
        abort_unless($this->isAdmin(Auth::user()), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'template' => ['nullable', 'string'],
        ]);

        $roleName = $this->normalizeRoleName($validated['name']);

        if ($roleName === '') {
            return response()->json(['message' => 'Role name must contain letters or numbers.'], 422);
        }

        if (in_array($roleName, self::RESERVED_ROLES, true)) {
            return response()->json(['message' => 'That role name is reserved.'], 422);
        }

        if (Role::query()->where('name', $roleName)->where('guard_name', 'web')->exists()) {
            return response()->json(['message' => 'That role already exists.'], 422);
        }

        $assignedPermissions = [];
        if (! empty($validated['template'])) {
            abort_if(in_array($validated['template'], self::RESERVED_ROLES, true), 422, 'System roles cannot be used as templates.');
            $template = Role::query()->where('name', $validated['template'])->where('guard_name', 'web')->firstOrFail();
            $assignedPermissions = $template->permissions()->pluck('name')->all();
        }

        $lockedPermissions = self::LOCKED_PERMISSIONS;
        $assignedPermissions = array_values(array_unique(array_merge($assignedPermissions, $lockedPermissions)));
        $assignedPermissions = array_values(array_diff($assignedPermissions, ['oversight.approve']));

        $pending = app(CountersignService::class)->request('role.create', [
            'role' => $roleName,
            'permissions' => $assignedPermissions,
        ], Auth::user());

        return response()->json([
            'message' => "Role {$roleName} creation is awaiting chairman approval.",
            'role' => $roleName,
            'permissions' => $assignedPermissions,
            'request_id' => $pending->id,
            'status' => 'pending',
        ], 202);
    }

    public function destroyRole(string $role): JsonResponse
    {
        abort_unless($this->isAdmin(Auth::user()), 403);

        $baseRoles = array_keys(PermissionCatalog::rolePresets());
        if (in_array($role, $baseRoles, true)) {
            return response()->json(['message' => 'Base roles cannot be deleted.'], 422);
        }

        $userCount = User::withTrashed()->role($role)->count();
        if ($userCount > 0) {
            return response()->json(['message' => "Unassign role from {$userCount} user(s) first."], 422);
        }

        $roleModel = Role::query()->where('name', $role)->where('guard_name', 'web')->firstOrFail();
        $pending = app(CountersignService::class)->request('role.delete', ['role' => $roleModel->name], Auth::user());

        return response()->json(['message' => "Role {$role} deletion is awaiting chairman approval.", 'request_id' => $pending->id, 'status' => 'pending'], 202);
    }

    public function restorePreset(string $role): JsonResponse
    {
        abort_unless($this->isAdmin(Auth::user()), 403);

        $preset = PermissionCatalog::rolePresets()[$role] ?? null;
        if ($preset === null) {
            return response()->json(['message' => 'No preset defined for this role.'], 404);
        }

        return response()->json(['permissions' => $preset]);
    }

    public function renameRole(Request $request, string $role): JsonResponse
    {
        abort_unless($this->isAdmin(Auth::user()), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $baseRoles = array_keys(PermissionCatalog::rolePresets());
        if (in_array($role, $baseRoles, true)) {
            return response()->json(['message' => 'Base roles cannot be renamed.'], 422);
        }

        $userCount = User::withTrashed()->role($role)->count();
        if ($userCount > 0) {
            return response()->json(['message' => "Unassign role from {$userCount} user(s) first."], 422);
        }

        $newName = $this->normalizeRoleName($validated['name']);
        if ($newName === '' || $newName === $role) {
            return response()->json(['message' => 'Role name must be a new valid name.'], 422);
        }

        if (in_array($newName, self::RESERVED_ROLES, true)) {
            return response()->json(['message' => 'That role name is reserved.'], 422);
        }

        if (Role::query()->where('name', $newName)->where('guard_name', 'web')->exists()) {
            return response()->json(['message' => 'That role already exists.'], 422);
        }

        $roleModel = Role::query()->where('name', $role)->where('guard_name', 'web')->firstOrFail();
        $pending = app(CountersignService::class)->request('role.rename', [
            'role' => $roleModel->name,
            'new_name' => $newName,
        ], Auth::user());

        return response()->json(['message' => 'Role rename is awaiting chairman approval.', 'role' => $newName, 'request_id' => $pending->id, 'status' => 'pending'], 202);
    }

    private function isAdmin(?User $user): bool
    {
        return (bool) ($user && $user->can('officers.manage-permissions'));
    }

    private function bootstrapPermissions(): void
    {
        $lockedPermissions = self::LOCKED_PERMISSIONS;
        $allToBootstrap = array_unique(array_merge(PermissionCatalog::allPermissions(), $lockedPermissions));

        foreach ($allToBootstrap as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (array_keys(PermissionCatalog::rolePresets()) as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function editablePermissionGroups(): array
    {
        return collect(PermissionCatalog::groups())
            ->map(fn (array $permissions) => array_values(array_diff($permissions, ['oversight.approve'])))
            ->all();
    }

    private function normalizeRoleName(string $name): string
    {
        return Str::of($name)->trim()->lower()->replaceMatches('/[^a-z0-9\s_-]+/', '')->slug('-')->value();
    }
}
