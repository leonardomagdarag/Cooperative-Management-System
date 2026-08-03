<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $this->alterUserRoleEnum([
            'developer',
            'admin',
            'manager',
            'member',
            'loan',
            'accountant',
            'secretary',
            'inactive',
            'terminated',
        ]);

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        foreach ($this->secretaryPermissions() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $secretaryRole = Role::findOrCreate('secretary', 'web');
        $secretaryRole->syncPermissions($this->secretaryPermissions());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('roles')) {
            Role::query()->where('name', 'secretary')->delete();
        }

        DB::table('users')
            ->where('role', 'secretary')
            ->update(['role' => 'accountant']);

        $this->alterUserRoleEnum([
            'developer',
            'admin',
            'manager',
            'member',
            'loan',
            'accountant',
            'inactive',
            'terminated',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function alterUserRoleEnum(array $roles): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $values = implode("','", $roles);
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('{$values}') DEFAULT 'member'");
    }

    private function secretaryPermissions(): array
    {
        return [
            'dashboard.view',
            'members.view',
            'members.create',
            'members.update',
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
        ];
    }
};
