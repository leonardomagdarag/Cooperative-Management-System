<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'logs.view')
            ->where('guard_name', 'web')
            ->value('id');

        $managerRoleId = DB::table('roles')
            ->where('name', 'manager')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId === null || $managerRoleId === null) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->where('role_id', $managerRoleId)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Do not restore Manager log access after this permission change.
    }
};
