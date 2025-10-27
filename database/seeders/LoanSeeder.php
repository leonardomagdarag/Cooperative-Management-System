<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Loan;
use App\Models\Account;
use App\Models\User;

class LoanSeeder extends Seeder
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

        // Get admin user for approved_by
        $admin = User::where('role', 'admin')->first();

        // Get all member accounts
        $accounts = Account::whereIn('user_id', $members->pluck('user_id'))->get();

        if ($accounts->count() < 3) {
            $this->command->error('Not enough accounts found. Please run AccountSeeder first.');
            return;
        }

        $loanTypes = ['Personal', 'Emergency', 'Business', 'Educational'];
        $frequencies = ['weekly', 'monthly', 'quarterly'];

        // Create 4 loans (2 paid_off and 2 active)
        $loanConfigs = [
            [
                'account' => $accounts[0],
                'status' => 'paid_off',
                'amount' => rand(10000, 30000),
                'interest' => 5.5,
                'type' => $loanTypes[array_rand($loanTypes)],
                'frequency' => 'monthly',
                'approved_days_ago' => rand(180, 365),
            ],
            [
                'account' => $accounts[1],
                'status' => 'paid_off',
                'amount' => rand(10000, 30000),
                'interest' => 6.0,
                'type' => $loanTypes[array_rand($loanTypes)],
                'frequency' => 'monthly',
                'approved_days_ago' => rand(180, 365),
            ],
            [
                'account' => $accounts[2],
                'status' => 'active',
                'amount' => rand(15000, 50000),
                'interest' => 7.0,
                'type' => $loanTypes[array_rand($loanTypes)],
                'frequency' => 'monthly',
                'approved_days_ago' => rand(30, 120),
            ],
            [
                'account' => $accounts[rand(0, 2)],
                'status' => 'active',
                'amount' => rand(15000, 50000),
                'interest' => 6.5,
                'type' => $loanTypes[array_rand($loanTypes)],
                'frequency' => 'monthly',
                'approved_days_ago' => rand(30, 120),
            ],
        ];

        foreach ($loanConfigs as $config) {
            $approvedDate = now()->subDays($config['approved_days_ago']);
            $dueDate = (clone $approvedDate)->addMonths(12); // 12 month loan term

            $loan = Loan::create([
                'account_id' => $config['account']->account_id,
                'loan_type' => $config['type'],
                'loan_amount' => $config['amount'],
                'interest_rate' => $config['interest'],
                'payment_frequency' => $config['frequency'],
                'loan_status' => $config['status'],
                'approved_at' => $approvedDate,
                'approved_by' => $admin->user_id,
                'due_date' => $dueDate,
            ]);

            $this->command->info("Created {$config['status']} loan of {$config['amount']} for account {$config['account']->account_id}");
        }
    }
}
