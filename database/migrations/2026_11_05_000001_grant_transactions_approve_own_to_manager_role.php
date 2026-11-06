<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'transactions.approve-own')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        $managerRoleId = DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', 'manager')
            ->value('id');

        if ($managerRoleId === null) {
            return;
        }

        DB::table('role_has_permissions')->insertOrIgnore([
            'permission_id' => $permissionId,
            'role_id' => $managerRoleId,
        ]);
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'transactions.approve-own')
            ->where('guard_name', 'web')
            ->value('id');

        $managerRoleId = DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', 'manager')
            ->value('id');

        if ($permissionId !== null && $managerRoleId !== null) {
            DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)
                ->where('role_id', $managerRoleId)
                ->delete();
        }
    }
};
