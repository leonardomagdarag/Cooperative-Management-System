<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_LOAN_PERMISSIONS = [
        'dashboard.view',
        'members.view',
        'services.view',
        'services.create',
        'loans.view',
        'loans.create',
        'deposits.create',
        'settings.view',
        'branches.bypass',
        'tickets.view',
        'tickets.reply',
        'notifications.view',
        'profile.view',
        'profile.update',
        'collections.view-own',
        'collections.create',
        'collections.submit',
    ];

    public function up(): void
    {
        Schema::create('loan_role_migration_user_backup', function ($table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('previous_role')->nullable();
            $table->boolean('had_role_assignment')->default(false);
        });

        $role = DB::table('roles')->where('name', 'loan')->where('guard_name', 'web')->first();
        Schema::create('loan_role_migration_state', function ($table) {
            $table->boolean('role_existed');
        });
        DB::table('loan_role_migration_state')->insert(['role_existed' => (bool) $role]);
        $userIds = $role
            ? DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', 'App\\Models\\User')
                ->pluck('model_id')
                ->merge(DB::table('users')->where('role', 'loan')->pluck('id'))
                ->unique()
            : DB::table('users')->where('role', 'loan')->pluck('id');

        foreach ($userIds as $userId) {
            DB::table('loan_role_migration_user_backup')->insert([
                'user_id' => $userId,
                'previous_role' => DB::table('users')->where('id', $userId)->value('role'),
                'had_role_assignment' => $role && DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->where('model_type', 'App\\Models\\User')
                    ->where('model_id', $userId)
                    ->exists(),
            ]);
        }

        if ($userIds->isNotEmpty()) {
            DB::table('users')->whereIn('id', $userIds)->update(['role' => 'unassigned_officer']);
        }

        if ($role) {
            DB::table('model_has_roles')->where('role_id', $role->id)->delete();
            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('id', $role->id)->delete();
        }
    }

    public function down(): void
    {
        $userBackups = DB::table('loan_role_migration_user_backup')->get();
        $hadLoanRole = (bool) DB::table('loan_role_migration_state')->value('role_existed');

        if (! $hadLoanRole) {
            foreach ($userBackups as $backup) {
                DB::table('users')->where('id', $backup->user_id)->update(['role' => $backup->previous_role]);
            }

            Schema::dropIfExists('loan_role_migration_user_backup');
            Schema::dropIfExists('loan_role_migration_state');

            return;
        }

        $role = DB::table('roles')->where('name', 'loan')->where('guard_name', 'web')->first();
        $roleId = $role?->id ?? DB::table('roles')->insertGetId([
            'name' => 'loan',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (self::LEGACY_LOAN_PERMISSIONS as $name) {
            $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            if ($permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        foreach ($userBackups as $backup) {
            DB::table('users')->where('id', $backup->user_id)->update(['role' => $backup->previous_role]);

            if ($backup->had_role_assignment) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $roleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $backup->user_id,
                ]);
            }
        }

        Schema::dropIfExists('loan_role_migration_user_backup');
        Schema::dropIfExists('loan_role_migration_state');
    }
};
