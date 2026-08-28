<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = Account::all();
        $loans = Loan::where('loan_status', 'approved')->get();

        foreach ($accounts as $account) {
            // Create membership payment fee based on account creation date
            $membershipFee = 500.00; // Standard membership fee
            Transaction::create([
                'account_id' => $account->id,
                'transaction_type' => 'fee',
                'amount' => $membershipFee,
                'transaction_date' => $account->created_at,
                'note' => 'Membership payment fee',
                'recorded_by' => rand(2, 5), // Random staff member
                'status' => 'completed',
            ]);

            // Create realistic deposit history (3-6 deposits)
            $depositCount = rand(3, 6);
            for ($i = 0; $i < $depositCount; $i++) {
                $depositAmount = $account->account_type === 'share-capital'
                    ? fake()->randomFloat(2, 500, 3000)   // Smaller share capital deposits
                    : fake()->randomFloat(2, 1000, 8000);  // Regular savings

                Transaction::create([
                    'account_id' => $account->id,
                    'transaction_type' => 'deposit',
                    'amount' => $depositAmount,
                    'transaction_date' => Carbon::now()->subDays(rand(10, 360)),
                    'note' => fake()->optional(0.5)->randomElement([
                        'Monthly savings deposit',
                        'Salary deposit',
                        'Share capital contribution',
                        null,
                    ]),
                    'recorded_by' => rand(2, 5), // Random staff member
                    'status' => 'completed',
                ]);
            }

            // Create fewer, realistic withdrawals (1-3)
            $withdrawalCount = rand(1, 3);
            for ($i = 0; $i < $withdrawalCount; $i++) {
                $withdrawalAmount = fake()->randomFloat(2, 500, min($account->balance * 0.3, 5000));

                Transaction::create([
                    'account_id' => $account->id,
                    'transaction_type' => 'withdrawal',
                    'amount' => $withdrawalAmount,
                    'transaction_date' => Carbon::now()->subDays(rand(5, 300)),
                    'note' => fake()->optional(0.4)->randomElement([
                        'Emergency withdrawal',
                        'Partial withdrawal',
                        null,
                    ]),
                    'recorded_by' => rand(2, 5),
                    'status' => 'completed',
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
                    'note' => 'Loan disbursement - '.ucfirst($loan->loan_type).' loan',
                    'recorded_by' => rand(2, 5),
                    'status' => 'completed',
                ]);

                // Create loan payment transactions based on loan status
                $paymentCount = match ($loan->loan_status) {
                    'paid_off' => $loan->term_months,  // Full term paid
                    'active' => rand(2, (int) ($loan->term_months * 0.5)), // Partial payments
                    default => rand(0, 1), // Approved but minimal/no payments yet
                };

                // Calculate monthly payment for realistic amounts
                $monthlyPayment = $loan->loan_amount / $loan->term_months;

                for ($i = 0; $i < $paymentCount; $i++) {
                    // Payment should be close to monthly amount
                    $paymentAmount = $monthlyPayment + fake()->randomFloat(2, -200, 500);
                    $paymentDate = $disbursementDate->copy()->addMonths($i + 1)->addDays(rand(-5, 5));

                    Transaction::create([
                        'account_id' => $account->id,
                        'loan_id' => $loan->id,
                        'transaction_type' => 'loan_payment',
                        'amount' => max(500, $paymentAmount), // Minimum 500
                        'transaction_date' => $paymentDate,
                        'note' => fake()->optional(0.5)->randomElement([
                            'Monthly loan payment',
                            'Regular installment',
                            'Principal + interest',
                            null,
                        ]),
                        'recorded_by' => rand(2, 5),
                        'status' => 'completed',
                    ]);
                }
            }
        }
    }
}
