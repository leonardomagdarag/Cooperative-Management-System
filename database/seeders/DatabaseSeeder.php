<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * The order matters and runs in bands:
     *
     *  1. Reference data — roles, Philippine geographic codes, branches and loan
     *     products. Loans validate themselves against the product catalogue, so
     *     products have to exist before any loan is created.
     *  2. The financial book — members, accounts, loans and the ledger. Each
     *     seeder here is self-sufficient and re-runnable, so the order is a
     *     convenience rather than a hard requirement, but the accounts and
     *     loans have to be in place before the ledger is written against them,
     *     and the chairman officer has to exist before the second role pass
     *     resolves him.
     *  3. Everything derived from that book — expenses and member notifications
     *     all describe events that have already happened.
     *  4. CMS content and settings, including the approval chain and the
     *     financial settings the later work reads.
     *  5. Work the desk has already been through — membership, support,
     *     collections, paperwork and interest crediting — and finally the audit
     *     trail, which records a slice of the most recent ledger entries and so
     *     has to run after the last one is written.
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
            ChairmanSeeder::class,               // the fifth officer slot, with the oversight.approve role
            SpatieRolePermissionSeeder::class,  // 2nd pass: backfill roles & permissions to seeded users
            BeneficiarySeeder::class,
            AccountSeeder::class,               // savings and share-capital accounts, membership fee
            LoanSeeder::class,                  // the loan portfolio
            TransactionSeeder::class,           // deposits, withdrawals, disbursements, payments, queue

            // 3. Derived from the book above
            ExpenseSeeder::class,
            NotificationSeeder::class,

            // CMS and settings data
            SiteContentSeeder::class,
            BoardMemberSeeder::class,
            SystemSettingSeeder::class,
            ServiceAvailabilitySeeder::class,
            FinancialSettingSeeder::class,
            AnnouncementSeeder::class,

            // 4. Work the desk has already been through — membership, support,
            //    collections, paperwork and the month's interest crediting.
            //    These all read rows produced above, so they sit last.
            MemberApplicationSeeder::class,     // the membership tray, incl. one still pending
            TicketSeeder::class,                // the member-support inbox
            TrustedDeviceSeeder::class,         // remembered browsers for members and an officer
            OversightRequestSeeder::class,      // two pending countersigns and one declined
            LoanDocumentSeeder::class,          // scanned paperwork on the loan files
            TransactionReceiptSeeder::class,    // printed receipts for counter transactions
            CollectionRunSeeder::class,         // the loan officer's collection routes
            InterestDisbursementSeeder::class,  // month's interest on savings and share capital

            // 5. The audit trail last, so it reads the finished transaction set
            //    — a re-seed then recognises the same entries instead of finding
            //    new ones among the most recent rows.
            LogSeeder::class,                   // audit trail, via AuditLogService
        ]);
    }
}
