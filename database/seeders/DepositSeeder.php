<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Database\Seeder;

class DepositSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = Account::all();

        if ($accounts->isEmpty()) {
            $this->command->error('No accounts found. Please run AccountSeeder first.');

            return;
        }

        $recordedBy = User::role('admin')->value('id')
            ?? User::query()->where('role', 'admin')->value('id')
            ?? 1;

        foreach ($accounts as $account) {
            $depositCount = rand(2, 5);

            for ($i = 0; $i < $depositCount; $i++) {
                Deposit::create([
                    'account_id' => $account->account_id,
                    'deposit_amount' => rand(1000, 50000),
                    'deposit_date' => now()->subDays(rand(1, 365)),
                    'recorded_by' => $recordedBy,
                ]);
            }
        }

        $totalDeposits = Deposit::count();
        $this->command->info("Created {$totalDeposits} deposits.");
    }
}
