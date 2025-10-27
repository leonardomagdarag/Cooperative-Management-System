<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Deposit;
use App\Models\Account;
use App\Models\User;

class DepositSeeder extends Seeder
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
            $this->command->error('Not enough member users found. Please run UserSeeder and AccountSeeder first.');
            return;
        }

        // Get admin user for recorded_by
        $admin = User::where('role', 'admin')->first();

        // Create 2 deposits for each account
        foreach ($members as $member) {
            $account = Account::where('user_id', $member->user_id)->first();

            if (!$account) {
                $this->command->error("No account found for {$member->email}");
                continue;
            }

            // First deposit
            $deposit1Amount = rand(5000, 15000);
            Deposit::create([
                'account_id' => $account->account_id,
                'deposit_amount' => $deposit1Amount,
                'deposit_date' => now()->subDays(rand(30, 90)),
                'recorded_by' => $admin->user_id,
            ]);

            // Second deposit
            $deposit2Amount = rand(3000, 10000);
            Deposit::create([
                'account_id' => $account->account_id,
                'deposit_amount' => $deposit2Amount,
                'deposit_date' => now()->subDays(rand(1, 29)),
                'recorded_by' => $admin->user_id,
            ]);

            // Update account balance
            $account->balance = $deposit1Amount + $deposit2Amount;
            $account->save();

            $this->command->info("Created 2 deposits for {$member->email} (Total: {$account->balance})");
        }
    }
}
