<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Withdrawal;
use App\Models\Account;
use App\Models\User;

class WithdrawalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get member users
        $members = User::where('role', 'member')
            ->whereIn('email', [
                'cris.jarabejo@example.com',
                'alyson.penano@example.com',
                'glendo.camarines@example.com'
            ])
            ->get();

        if ($members->count() < 3) {
            $this->command->error('Not enough member users found. Please run previous seeders first.');
            return;
        }

        // Get admin user for recorded_by
        $admin = User::where('role', 'admin')->first();

        // Get all member accounts
        $accounts = Account::whereIn('user_id', $members->pluck('user_id'))->get();

        if ($accounts->count() < 3) {
            $this->command->error('Not enough accounts found. Please run AccountSeeder first.');
            return;
        }

        // Create 4 random withdrawals distributed among the members
        $withdrawalData = [
            ['account' => $accounts[0], 'amount' => rand(500, 2000)],
            ['account' => $accounts[1], 'amount' => rand(500, 2000)],
            ['account' => $accounts[2], 'amount' => rand(500, 2000)],
            ['account' => $accounts[rand(0, 2)], 'amount' => rand(500, 2000)], // Random member gets 4th withdrawal
        ];

        foreach ($withdrawalData as $data) {
            $account = $data['account'];
            $amount = $data['amount'];

            // Check if account has sufficient balance
            if ($account->balance < $amount) {
                $amount = $account->balance * 0.3; // Withdraw only 30% if not enough
            }

            Withdrawal::create([
                'account_id' => $account->account_id,
                'withdrawal_amount' => $amount,
                'recorded_by' => $admin->user_id,
            ]);

            // Update account balance
            $account->balance -= $amount;
            $account->save();

            $this->command->info("Created withdrawal of {$amount} for account {$account->account_id}");
        }
    }
}
