<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Migrate existing secretary users to accountant in the users table
        DB::table('users')
            ->where('role', 'secretary')
            ->update(['role' => 'accountant']);

        // Clear permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. We also need to update their Spatie roles if they exist.
        // It's possible the role 'secretary' exists in the roles table.
        $secretaryRole = Role::where('name', 'secretary')->first();
        $accountantRole = Role::where('name', 'accountant')->first();

        if ($secretaryRole) {
            // Find all users who have the secretary role
            $usersWithSecretaryRole = User::role('secretary')->get();

            foreach ($usersWithSecretaryRole as $user) {
                // Remove secretary role
                $user->removeRole('secretary');
                // Assign accountant role if it exists (which it should, but if it doesn't the seeder will create it)
                if ($accountantRole) {
                    $user->assignRole('accountant');
                }
            }
        }

        // Now we run the seeder to populate new roles/permissions.
        // This satisfies the requirement of running the seeder before removing the Gate bypass.
        Artisan::call('db:seed', [
            '--class' => 'SpatieRolePermissionSeeder',
            '--force' => true, // Force in production
        ]);

        // At this point we should probably also make sure that people who used to depend on
        // 'primary_role === admin' but don't have the admin spatie role get it if their DB column says admin.
        // The seeder handles assigning roles based on legacy 'role' column if they don't have it.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a data migration, difficult to reverse safely.
        // We could revert accountant back to secretary if they have that role, but that's ambiguous.
    }
};
