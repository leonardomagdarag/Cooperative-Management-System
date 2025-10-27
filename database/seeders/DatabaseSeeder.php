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
        // User::factory(10)->create();

        // Seed in correct order due to dependencies
        $this->call([
            UserSeeder::class,
            AccountSeeder::class,
            DepositSeeder::class,
            WithdrawalSeeder::class,
            LoanSeeder::class,
            LoanPaymentSeeder::class,
        ]);
    }
}
