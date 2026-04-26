<?php

namespace Database\Seeders;

use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolePermissions = [
            // ADMIN
            ['role' => 'admin', 'section' => 'dashboard', 'permissions' => ['view']],
            ['role' => 'admin', 'section' => 'officers', 'permissions' => ['view', 'create', 'edit']],
            ['role' => 'admin', 'section' => 'members', 'permissions' => ['view', 'create', 'edit']],
            ['role' => 'admin', 'section' => 'tickets', 'permissions' => ['view', 'edit', 'close']],
            ['role' => 'admin', 'section' => 'ledger', 'permissions' => ['view', 'export']],
            ['role' => 'admin', 'section' => 'logs', 'permissions' => ['view', 'export']],
            ['role' => 'admin', 'section' => 'announcements', 'permissions' => ['view', 'create', 'edit', 'delete']],
            ['role' => 'admin', 'section' => 'reports', 'permissions' => ['view', 'export']],
            ['role' => 'admin', 'section' => 'settings', 'permissions' => ['view', 'edit']],

            // MANAGER
            ['role' => 'manager', 'section' => 'dashboard', 'permissions' => ['view']],
            ['role' => 'manager', 'section' => 'officers', 'permissions' => ['view', 'create', 'edit', 'delete']],
            ['role' => 'manager', 'section' => 'members', 'permissions' => ['view', 'create', 'edit', 'delete']],
            ['role' => 'manager', 'section' => 'tickets', 'permissions' => ['view', 'edit', 'close']],
            ['role' => 'manager', 'section' => 'ledger', 'permissions' => ['view', 'export']],
            ['role' => 'manager', 'section' => 'announcements', 'permissions' => ['view', 'create', 'edit', 'delete']],
            ['role' => 'manager', 'section' => 'reports', 'permissions' => ['view', 'export']],
            ['role' => 'manager', 'section' => 'settings', 'permissions' => ['view', 'edit']],

            // MEMBER
            ['role' => 'member', 'section' => 'dashboard', 'permissions' => ['view']],
            ['role' => 'member', 'section' => 'services', 'permissions' => ['view', 'create']],
            ['role' => 'member', 'section' => 'history', 'permissions' => ['view']],
            ['role' => 'member', 'section' => 'announcements', 'permissions' => ['view']],
            ['role' => 'member', 'section' => 'settings', 'permissions' => ['view', 'edit']],

            // LOAN OFFICER
            ['role' => 'loan', 'section' => 'dashboard', 'permissions' => ['view']],
            ['role' => 'loan', 'section' => 'services', 'permissions' => ['view', 'create', 'edit']],
            ['role' => 'loan', 'section' => 'loans', 'permissions' => ['view', 'approve', 'reject']],
            ['role' => 'loan', 'section' => 'members', 'permissions' => ['view']],
            ['role' => 'loan', 'section' => 'messages', 'permissions' => ['view', 'create', 'reply']],
            ['role' => 'loan', 'section' => 'announcements', 'permissions' => ['view']],
            ['role' => 'loan', 'section' => 'reports', 'permissions' => ['view', 'export']],
            ['role' => 'loan', 'section' => 'settings', 'permissions' => ['view']],

            // SECRETARY
            ['role' => 'secretary', 'section' => 'dashboard', 'permissions' => ['view']],
            ['role' => 'secretary', 'section' => 'members', 'permissions' => ['view', 'create', 'edit']],
            ['role' => 'secretary', 'section' => 'ledger', 'permissions' => ['view', 'export']],
            ['role' => 'secretary', 'section' => 'tickets', 'permissions' => ['view', 'edit', 'close']],
            ['role' => 'secretary', 'section' => 'messages', 'permissions' => ['view', 'create', 'reply']],
            ['role' => 'secretary', 'section' => 'announcements', 'permissions' => ['view', 'create', 'edit']],
            ['role' => 'secretary', 'section' => 'reports', 'permissions' => ['view', 'export']],
            ['role' => 'secretary', 'section' => 'settings', 'permissions' => ['view']],

            // TREASURER
            ['role' => 'treasurer', 'section' => 'dashboard', 'permissions' => ['view']],
            ['role' => 'treasurer', 'section' => 'services', 'permissions' => ['view', 'create', 'edit']],
            ['role' => 'treasurer', 'section' => 'members', 'permissions' => ['view']],
            ['role' => 'treasurer', 'section' => 'ledger', 'permissions' => ['view', 'create', 'edit', 'export']],
            ['role' => 'treasurer', 'section' => 'announcements', 'permissions' => ['view']],
            ['role' => 'treasurer', 'section' => 'reports', 'permissions' => ['view', 'export']],
            ['role' => 'treasurer', 'section' => 'settings', 'permissions' => ['view']],
        ];

        foreach ($rolePermissions as $permission) {
            RolePermission::updateOrCreate(
                [
                    'role' => $permission['role'],
                    'section' => $permission['section'],
                ],
                $permission
            );
        }

        $this->command->info('Role permissions seeded successfully!');
    }
}
