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

        $builtinRoleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['admin', 'chairman', 'manager', 'accountant', 'loan', 'developer', 'member'])
            ->pluck('id');

        if ($builtinRoleIds->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $builtinRoleIds)
            ->delete();
    }

    public function down(): void
    {
        // Do not restore a permission assignment that violates the maker-checker invariant.
    }
};
