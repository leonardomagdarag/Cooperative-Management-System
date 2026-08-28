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
        $permissions = [
            // Dashboard
            'dashboard.view',

            // Members
            'members.view',
            'members.create',
            'members.update',
            'members.delete',
            'members.view-full-details',

            // Services
            'services.view',
            'services.create',
            'services.update',

            // Loans
            'loans.view',
            'loans.create',
            'loans.encode',
            'loans.update',
            'loans.approve',
            'loans.reject',
            'loans.disburse',

            // Transactions
            'transactions.view',
            'transactions.view-all',
            'transactions.create',
            'transactions.update',
            'transactions.approve',
            'transactions.void',
            'transactions.export',

            // Deposits & Withdrawals
            'deposits.view',
            'deposits.create',
            'deposits.approve',
            'deposits.reject',
            'withdrawals.view',
            'withdrawals.create',
            'withdrawals.approve',
            'withdrawals.reject',

            // Reports
            'reports.view',
            'reports.export',
            'reports.financial',
            'reports.audit',

            // Announcements
            'announcements.view',
            'announcements.create',
            'announcements.update',
            'announcements.delete',

            // Settings
            'settings.view',
            'settings.update',

            // Audit Logs
            'logs.view',
            'logs.export',

            // Officer/Staff Management
            'officers.view',
            'officers.create',
            'officers.update',
            'officers.delete',
            'officers.manage-permissions',

            // Notifications
            'notifications.view',
            'notifications.manage',

            // Profile
            'profile.view',
            'profile.update',
        ];

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
        $officerRoleNames = array_merge(['admin'], PermissionCatalog::officerRoles());
        $rolePermissions = [
            // Super admin: all permissions
            'admin' => $allPermissions,

            // Officer roles receive their default permissions via Spatie roles.
            'manager' => $officerPresets['manager'],
            'accountant' => $officerPresets['accountant'],
            'loan' => $officerPresets['loan'],
            'secretary' => $officerPresets['secretary'],
            'developer' => [],

            // Regular members: limited self-service permissions
            'member' => [
                'dashboard.view',
                'services.view',
                'services.create',
                'loans.view',
                'loans.create',
                'transactions.view',
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
        foreach ($rolePermissions as $roleName => $grantedPermissions) {
            $role = Role::findOrCreate($roleName, 'web');

            if (! $role->permissions()->exists()) {
                $role->syncPermissions($grantedPermissions);

                continue;
            }

            if (! in_array($roleName, PermissionCatalog::officerRoles(), true)) {
                $existingPermissions = $role->permissions()->pluck('name')->all();
                $missingPermissions = array_values(array_diff($grantedPermissions, $existingPermissions));

                if ($missingPermissions !== []) {
                    $role->givePermissionTo($missingPermissions);
                }
            }
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
