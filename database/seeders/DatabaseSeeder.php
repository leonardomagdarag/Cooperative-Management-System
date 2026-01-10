<?php

namespace Database\Seeders;

use App\Models\User;
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
            UserSeeder::class,
            BeneficiarySeeder::class,
            AccountSeeder::class,
            LoanSeeder::class,
            TransactionSeeder::class, // Handles deposits, withdrawals, and loan payments
            LogSeeder::class,
        ]);
    }
}
