<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * The order matters and runs in three bands:
     *
     *  1. Reference data — roles, Philippine geographic codes, branches and loan
     *     products. Loans validate themselves against the product catalogue, so
     *     products have to exist before any loan is created.
     *  2. The financial book — members, accounts, loans and the ledger. Each
     *     seeder here is self-sufficient and re-runnable, so the order is a
     *     convenience rather than a hard requirement, but the accounts and
     *     loans must be in place before the ledger is written against them.
     *  3. Everything derived from that book — expenses, audit trail and member
     *     notifications all describe events that have already happened.
     */
    public function run(): void
    {
        $this->call([
            // 1. Reference data
            SpatieRolePermissionSeeder::class,  // 1st pass: create roles & permissions
            PSGCSeeder::class,                  // Philippine geographic codes for the address selector
            BranchSeeder::class,                // users, accounts and loans are tied to a branch
            LoanProductSeeder::class,           // loan eligibility is judged against the product catalogue

            // 2. The financial book
            UserSeeder::class,
            SpatieRolePermissionSeeder::class,  // 2nd pass: backfill roles & permissions to seeded users
            BeneficiarySeeder::class,
            AccountSeeder::class,               // savings and share-capital accounts, membership fee
            LoanSeeder::class,                  // the loan portfolio
            TransactionSeeder::class,           // deposits, withdrawals, disbursements, payments, queue

            // 3. Derived from the book above
            ExpenseSeeder::class,
            LogSeeder::class,                   // audit trail, via AuditLogService
            NotificationSeeder::class,

            // CMS and settings data
            SiteContentSeeder::class,
            BoardMemberSeeder::class,
            SystemSettingSeeder::class,
            ServiceAvailabilitySeeder::class,
            AnnouncementSeeder::class,
        ]);
    }
}
