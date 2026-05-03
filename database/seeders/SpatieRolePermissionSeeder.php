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
     * Officer permissions are assigned individually, not via roles.
     * Members use the 'member' role with fixed permissions.
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
            'loans.update',
            'loans.approve',
            'loans.reject',
            'loans.disburse',

            // Transactions
            'transactions.view',
            'transactions.create',
            'transactions.update',
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

        // === DEFINE ROLES ===
        // Only 3 fixed roles: admin, member, and 2 inactive states
        // Officers DON'T get a fixed role; they get individual permissions instead

        $rolePermissions = [
            // Super admin: all permissions
            'admin' => $allPermissions,

            // Officer roles now receive permissions via their Spatie role (RBAC).
            // Permissions are sourced from PermissionCatalog::rolePresets().
            'manager'   => PermissionCatalog::rolePresets()['manager'],
            'secretary' => PermissionCatalog::rolePresets()['secretary'],
            'loan'      => PermissionCatalog::rolePresets()['loan'],
            'treasurer' => PermissionCatalog::rolePresets()['treasurer'],
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

        // Create and sync roles with permissions
        foreach ($rolePermissions as $roleName => $grantedPermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($grantedPermissions);
        }

        // === ASSIGN ROLES & PERMISSIONS TO EXISTING USERS ===

        // 1. Assign 'admin' role to users with role='admin'
        User::query()
            ->where('role', 'admin')
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    $user->account_status = 'active';
                    $user->save();
                    $user->syncRoles(['admin']);
                }
            });

        // 2. Assign 'member' role to users with role='member'
        User::query()
            ->where('role', 'member')
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    $user->account_status = 'active';
                    $user->save();
                    $user->syncRoles(['member']);
                }
            });

        // 3. Assign route roles to officer personas.
        User::query()
            ->whereIn('role', ['manager', 'secretary', 'loan', 'treasurer'])
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    $user->account_status = 'active';
                    $user->save();
                    $user->syncRoles([$user->role]);
                }
            });

        // 4. Keep lifecycle status users out of active routes.
        User::query()
            ->where('role', 'terminated')
            ->update(['account_status' => 'terminated']);

        User::query()
            ->where('role', 'inactive')
            ->update(['account_status' => 'inactive']);

        // === CLEAR DIRECT USER PERMISSIONS FOR OFFICERS ===
        // Permissions are now role-based. Remove any individually assigned
        // permissions from officer users so only role grants apply.
        // Per-officer exceptions use the officer_permission_overrides table.
        User::query()
            ->whereIn('role', array_keys(PermissionCatalog::rolePresets()))
            ->chunk(100, function ($users) {
                foreach ($users as $user) {
                    $user->is_officer = true;
                    $user->save();
                    $user->syncPermissions([]);
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
