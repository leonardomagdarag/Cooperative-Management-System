<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SpatieRolePermissionSeeder extends Seeder
{
    /**
     * All available permissions in the system, organized by resource.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // === DEFINE ALL PERMISSIONS ===
        $permissions = PermissionCatalog::allPermissions();

        // Create all permissions
        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $allPermissions = Permission::query()->pluck('name')->all();

        User::query()
            ->where('role', 'treasurer')
            ->update(['role' => 'accountant']);

        // === DEFINE ROLES ===
        $officerPresets = PermissionCatalog::rolePresets();
        $officerRoleNames = array_merge(['admin', 'chairman'], PermissionCatalog::officerRoles());
        $rolePermissions = [
            'admin' => $officerPresets['admin'],
            'chairman' => $officerPresets['chairman'],
            'manager' => $officerPresets['manager'],
            'accountant' => $officerPresets['accountant'],
            'loan' => $officerPresets['loan'],
            'developer' => $officerPresets['developer'],

            // Regular members: limited self-service permissions
            'member' => [
                'dashboard.view',
                'services.view',
                'services.create',
                'loans.create',
                'deposits.view',
                'deposits.create',
                'withdrawals.view',
                'withdrawals.create',
                'announcements.view',
                'profile.view',
                'profile.update',
                'notifications.view',
            ],

            // Inactive and terminated have no permissions
            'inactive' => [],
            'terminated' => [],
        ];

        // Bootstrap roles without wiping settings-managed edits on later seeds.
        // Officer roles also pick up newly added preset permissions (e.g. a fresh
        // members.approve) while keeping any settings-driven customizations.
        foreach ($rolePermissions as $roleName => $grantedPermissions) {
            $role = Role::findOrCreate($roleName, 'web');

            if (! $role->permissions()->exists()) {
                $role->syncPermissions($grantedPermissions);

                continue;
            }

            $existingPermissions = $role->permissions()->pluck('name')->all();
            $missingPermissions = array_values(array_diff($grantedPermissions, $existingPermissions));

            if ($missingPermissions !== []) {
                $role->givePermissionTo($missingPermissions);
            }
        }

        // Maker-checker exceptions belong to officer-level overrides, except
        // for manager who has self-approval for financial transactions in preset.
        // Remove stale grants from other built-in roles.
        Role::query()
            ->whereIn('name', array_keys($rolePermissions))
            ->where('name', '!=', 'manager')
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->revokePermissionTo('transactions.approve-own'));

        // System logs are reserved for the administrator and chairman roles.
        // Remove stale grants from manager roles created by older presets.
        Role::query()
            ->where('name', 'manager')
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->revokePermissionTo('logs.view'));

        // === ASSIGN ROLES & PERMISSIONS TO EXISTING USERS ===

        // 1. Keep active members on the member role and outside officer flows.
        User::query()
            ->memberUsers()
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    $user->account_status = 'active';
                    $user->is_officer = false;
                    $user->save();
                    $user->syncRoles(['member']);
                }
            });

        // 2. Preserve the developer role for local/dev accounts. The developer
        // role is a normal officer-level role whose permissions come from the
        // catalog, so officer-scoped guards apply without special-casing.
        User::query()
            ->where('role', 'developer')
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    $user->account_status = 'active';
                    $user->is_officer = true;
                    $user->profile_completed_at = $user->profile_completed_at ?? now();
                    $user->save();
                    $user->syncRoles(['developer']);
                }
            });

        // 3. Resolve officer identities from their existing Spatie roles first,
        // falling back to the legacy role column only when bootstrapping old data.
        User::query()
            ->with('roles')
            ->officerUsers()
            ->chunk(100, function ($users) use ($officerRoleNames) {
                foreach ($users as $user) {
                    $resolvedRole = $user->getRoleNames()->first();

                    if (! is_string($resolvedRole) || ! in_array($resolvedRole, $officerRoleNames, true)) {
                        $legacyRole = $user->getRawOriginal('role');
                        $resolvedRole = is_string($legacyRole) && in_array($legacyRole, $officerRoleNames, true)
                            ? $legacyRole
                            : null;
                    }

                    if (! is_string($resolvedRole) || $resolvedRole === '') {
                        continue;
                    }

                    $user->account_status = 'active';
                    $user->is_officer = true;
                    $user->save();
                    $user->syncRoles([$resolvedRole]);
                    $user->syncPermissions([]);
                }
            });

        // 4. Keep lifecycle status users out of active routes.
        User::query()
            ->where('role', 'terminated')
            ->update(['account_status' => 'terminated', 'is_officer' => false]);

        User::query()
            ->where('role', 'inactive')
            ->update(['account_status' => 'inactive', 'is_officer' => false]);

        Role::query()->where('name', 'treasurer')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
