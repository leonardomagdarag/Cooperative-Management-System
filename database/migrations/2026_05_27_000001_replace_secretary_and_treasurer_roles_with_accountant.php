<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyOfficerPersonalDataIds = DB::table('users')
            ->whereIn('role', ['secretary', 'treasurer'])
            ->whereNotNull('personal_data_id')
            ->pluck('personal_data_id')
            ->all();

        $this->alterPersonalDataTitleEnum([
            'Chairman',
            'Vice Chairman',
            'Secretary',
            'Treasurer',
            'Loan Officer',
            'Accountant',
            'Member',
        ]);

        if ($legacyOfficerPersonalDataIds !== []) {
            DB::table('personal_data')
                ->whereIn('id', $legacyOfficerPersonalDataIds)
                ->whereIn('title', ['Secretary', 'Treasurer'])
                ->update(['title' => 'Accountant']);
        }

        $this->alterUserRoleEnum([
            'developer',
            'admin',
            'manager',
            'member',
            'loan',
            'treasurer',
            'secretary',
            'accountant',
            'inactive',
            'terminated',
        ]);

        DB::table('users')
            ->whereIn('role', ['secretary', 'treasurer'])
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
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'accountant')
            ->update(['role' => 'treasurer']);

        $this->alterUserRoleEnum([
            'developer',
            'admin',
            'manager',
            'member',
            'loan',
            'treasurer',
            'secretary',
            'inactive',
            'terminated',
        ]);

        DB::table('personal_data')
            ->where('title', 'Accountant')
            ->update(['title' => 'Treasurer']);

        $this->alterPersonalDataTitleEnum([
            'Chairman',
            'Vice Chairman',
            'Secretary',
            'Treasurer',
            'Loan Officer',
            'Member',
        ]);
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

    private function alterPersonalDataTitleEnum(array $titles): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $values = implode("','", $titles);
        DB::statement("ALTER TABLE personal_data MODIFY COLUMN title ENUM('{$values}') DEFAULT 'Member'");
    }
};
