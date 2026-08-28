<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed in correct order due to dependencies
        $this->call([
            SpatieRolePermissionSeeder::class,  // 1st pass: create roles & permissions

            // Core data
            UserSeeder::class,
            SpatieRolePermissionSeeder::class,  // 2nd pass: backfill roles & permissions to seeded users
            BeneficiarySeeder::class,
            AccountSeeder::class,
            LoanSeeder::class,
            TransactionSeeder::class, // Handles deposits, withdrawals, and loan payments
            LogSeeder::class,

            // CMS and settings data
            SiteContentSeeder::class,
            BoardMemberSeeder::class,
            SystemSettingSeeder::class,
            LoanProductSeeder::class,
            RolePermissionSeeder::class,
            AnnouncementSeeder::class,
        ]);
    }
}
