<?php

namespace Database\Seeders;

use App\Models\User;
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

            // Officer personas use direct user permissions but still need roles for route middleware.
            'manager' => [],
            'secretary' => [],
            'loan' => [],
            'treasurer' => [],
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

        // === INITIALIZE OFFICERS WITH PERMISSIONS (not roles) ===
        // Find all existing officers and assign permissions based on their old role
        $officerRolePermissions = [
            'manager' => [
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
                'officers.create',
                'officers.update',
                'officers.delete',
                'officers.manage-permissions',
                'notifications.view',
                'notifications.manage',
                'profile.view',
                'profile.update',
            ],
            'treasurer' => [
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
                'notifications.view',
                'profile.view',
                'profile.update',
            ],
            'loan' => [
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
            ],
            'secretary' => [
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
            ],
        ];

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

        // 3. Assign route roles to officer personas and developer.
        User::query()
            ->whereIn('role', ['manager', 'secretary', 'loan', 'treasurer', 'developer'])
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

        // 5. Assign permissions to existing officers based on their old role
        User::query()
            ->whereIn('role', array_keys($officerRolePermissions))
            ->chunk(100, function ($users) use ($officerRolePermissions) {
                foreach ($users as $user) {
                    $user->is_officer = true;
                    $user->save();

                    if (isset($officerRolePermissions[$user->role])) {
                        $user->syncPermissions($officerRolePermissions[$user->role]);
                    }
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
