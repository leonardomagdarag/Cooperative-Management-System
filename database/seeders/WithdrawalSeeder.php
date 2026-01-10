<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Withdrawal;
use App\Models\Account;
use App\Models\User;

class WithdrawalSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = Account::all();

        if ($accounts->isEmpty()) {
            $this->command->error('No accounts found. Please run AccountSeeder first.');
            return;
        }

        $recordedBy = User::where('role', 'admin')->first()->user_id ?? 'USR_0001';
        $accountsToWithdraw = $accounts->random(min($accounts->count(), (int)($accounts->count() * 0.6)));

        foreach ($accountsToWithdraw as $account) {
            $withdrawalCount = rand(1, 3);

            for ($i = 0; $i < $withdrawalCount; $i++) {
                Withdrawal::create([
                    'account_id' => $account->account_id,
                    'withdrawal_amount' => rand(500, 30000),
                    'recorded_by' => $recordedBy,
                ]);
            }
        }

        $totalWithdrawals = Withdrawal::count();
        $this->command->info("Created {$totalWithdrawals} withdrawals.");
    }
}
