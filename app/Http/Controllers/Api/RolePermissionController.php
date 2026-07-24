<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    private const RESERVED_ROLES = ['admin', 'developer', 'member', 'inactive', 'terminated'];

    public function index(): JsonResponse
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $this->bootstrapPermissions();

        $editableRoles = Role::query()
            ->whereNotIn('name', self::RESERVED_ROLES)
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        $rolePermissions = [];
        foreach ($editableRoles as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $rolePermissions[$roleName] = $role->permissions()->pluck('name')->sort()->values()->all();
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
            'groups' => PermissionCatalog::groups(),
        ]);
    }

    public function updatePermissions(Request $request, string $role): JsonResponse
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->bootstrapPermissions();

        $lockedPermissions = ['dashboard.view', 'notifications.view', 'profile.view', 'profile.update', 'settings.view'];

        $allAllowed = array_unique(array_merge(
            PermissionCatalog::allPermissions(),
            $lockedPermissions,
        ));

        $roleModel = Role::findOrCreate($role, 'web');
        $permissions = array_values(array_intersect(
            $allAllowed,
            array_unique(array_merge($validated['permissions'], $lockedPermissions))
        ));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $roleModel->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => 'Permissions updated.', 'permissions' => $permissions]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

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
        if (! empty($validated['template']) && Role::query()->where('name', $validated['template'])->exists()) {
            $assignedPermissions = Role::findByName($validated['template'], 'web')->permissions()->pluck('name')->all();
        }

        $lockedPermissions = ['dashboard.view', 'notifications.view', 'profile.view', 'profile.update', 'settings.view'];
        $assignedPermissions = array_values(array_unique(array_merge($assignedPermissions, $lockedPermissions)));

        $role = Role::create(['name' => $roleName, 'guard_name' => 'web']);
        $role->syncPermissions($assignedPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => "Role {$roleName} created.",
            'role' => $roleName,
            'permissions' => $assignedPermissions,
        ], 201);
    }

    public function destroyRole(string $role): JsonResponse
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $baseRoles = array_keys(PermissionCatalog::rolePresets());
        if (in_array($role, $baseRoles, true)) {
            return response()->json(['message' => 'Base roles cannot be deleted.'], 422);
        }

        $userCount = User::query()->role($role)->count();
        if ($userCount > 0) {
            return response()->json(['message' => "Unassign role from {$userCount} user(s) first."], 422);
        }

        Role::findByName($role, 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => "Role {$role} deleted."]);
    }

    public function restorePreset(string $role): JsonResponse
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $preset = PermissionCatalog::rolePresets()[$role] ?? null;
        if ($preset === null) {
            return response()->json(['message' => 'No preset defined for this role.'], 404);
        }

        return response()->json(['permissions' => $preset]);
    }

    private function bootstrapPermissions(): void
    {
        $lockedPermissions = ['dashboard.view', 'notifications.view', 'profile.view', 'profile.update', 'settings.view'];
        $allToBootstrap = array_unique(array_merge(PermissionCatalog::allPermissions(), $lockedPermissions));

        foreach ($allToBootstrap as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (array_keys(PermissionCatalog::rolePresets()) as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function normalizeRoleName(string $name): string
    {
        return Str::of($name)->trim()->lower()->replaceMatches('/[^a-z0-9\s_-]+/', '')->slug('-')->value();
    }
}
