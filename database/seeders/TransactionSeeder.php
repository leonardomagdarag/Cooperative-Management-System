<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\Account;
use App\Models\Loan;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = Account::all();
        $loans = Loan::where('loan_status', 'approved')->get();

        foreach ($accounts as $account) {
            $transactionCount = rand(8, 15); // More transactions per member
            
            // Create deposits with varying amounts and dates
            $depositCount = rand(4, 7);
            for ($i = 0; $i < $depositCount; $i++) {
                Transaction::create([
                    'account_id' => $account->id,
                    'transaction_type' => 'deposit',
                    'amount' => fake()->randomFloat(2, 1000, 10000),
                    'transaction_date' => Carbon::now()->subDays(rand(1, 180)),
                    'note' => fake()->optional(0.4)->randomElement([
                        'Monthly savings deposit',
                        'Salary deposit',
                        'Additional contribution',
                        'Share capital deposit',
                        null
                    ]),
                    'recorded_by' => rand(2, 5), // Random staff member
                ]);
            }

            // Create withdrawals with varying amounts
            $withdrawalCount = rand(2, 5);
            for ($i = 0; $i < $withdrawalCount; $i++) {
                Transaction::create([
                    'account_id' => $account->id,
                    'transaction_type' => 'withdrawal',
                    'amount' => fake()->randomFloat(2, 500, 5000),
                    'transaction_date' => Carbon::now()->subDays(rand(1, 180)),
                    'note' => fake()->optional(0.3)->randomElement([
                        'Emergency withdrawal',
                        'Partial withdrawal',
                        'Regular withdrawal',
                        null
                    ]),
                    'recorded_by' => rand(2, 5),
                ]);
            }
        }

        // Create loan disbursements and payments for approved/active/paid_off loans
        $disbursableLoans = Loan::whereIn('loan_status', ['approved', 'active', 'paid_off'])->get();
        
        foreach ($disbursableLoans as $loan) {
            $account = Account::where('user_id', $loan->user_id)->first();
            if ($account) {
                // Create loan disbursement transaction
                $disbursementDate = $loan->approved_at 
                    ? Carbon::parse($loan->approved_at) 
                    : Carbon::now()->subDays(rand(30, 150));
                
                Transaction::create([
                    'account_id' => $account->id,
                    'loan_id' => $loan->id,
                    'transaction_type' => 'loan_disbursement',
                    'amount' => $loan->loan_amount,
                    'transaction_date' => $disbursementDate,
                    'note' => 'Loan disbursement - ' . $loan->loan_type,
                    'recorded_by' => rand(2, 5),
                ]);

                // Create loan payment transactions
                // More payments for active and paid_off loans
                $paymentCount = match($loan->loan_status) {
                    'paid_off' => rand(8, 12),
                    'active' => rand(4, 8),
                    default => rand(1, 3),
                };
                
                for ($i = 0; $i < $paymentCount; $i++) {
                    $paymentAmount = fake()->randomFloat(2, 500, min($loan->loan_amount / 10, 8000));
                    $paymentDate = $disbursementDate->copy()->addDays(rand(10, 180));
                    
                    Transaction::create([
                        'account_id' => $account->id,
                        'loan_id' => $loan->id,
                        'transaction_type' => 'loan_payment',
                        'amount' => $paymentAmount,
                        'transaction_date' => $paymentDate,
                        'note' => fake()->optional(0.4)->randomElement([
                            'Regular loan payment',
                            'Monthly installment',
                            'Principal + interest payment',
                            'Early payment',
                            null
                        ]),
                        'recorded_by' => rand(2, 5),
                    ]);
                }
            }
        }
    }
}
