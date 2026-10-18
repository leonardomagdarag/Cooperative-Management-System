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
            'developer' => [],

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

        // Retire the former broad admin defaults while preserving any unrelated
        // custom permissions an operator deliberately added to the role.
        $formerAdminOperationalPermissions = [
            'members.view', 'members.view-loans', 'members.view-ids', 'members.view-activity', 'members.view-financial',
            'services.view', 'loans.view', 'transactions.view', 'transactions.view-all', 'transactions.export',
            'deposits.view', 'withdrawals.view', 'announcements.create', 'announcements.update', 'announcements.delete',
            'reports.financial', 'branches.view', 'branches.create', 'branches.update', 'branches.delete', 'branches.bypass',
            'oversight.view', 'collections.view-all', 'collections.workflow.update', 'financial_settings.view',
            'financial_settings.update', 'interest.view', 'interest.run',
        ];
        Role::findByName('admin', 'web')->revokePermissionTo($formerAdminOperationalPermissions);

        // Branch-level staff are scoped by their assigned branch. Main-branch
        // officers receive their cross-branch access from BranchScope, while
        // manager roles retain the explicit bypass permission.
        foreach (['accountant', 'loan'] as $branchScopedRole) {
            Role::findByName($branchScopedRole, 'web')->revokePermissionTo('branches.bypass');
        }

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

        // 2. Preserve the developer role for local/dev accounts.
        User::query()
            ->where('role', 'developer')
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    $user->account_status = 'active';
                    $user->is_officer = false;
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
