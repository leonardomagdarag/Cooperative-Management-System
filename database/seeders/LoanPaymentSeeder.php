<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LoanPayment;
use App\Models\Loan;

class LoanPaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all loans
        $loans = Loan::all();

        if ($loans->count() === 0) {
            $this->command->error('No loans found. Please run LoanSeeder first.');
            return;
        }

        foreach ($loans as $loan) {
            // Calculate monthly payment amount (simple calculation)
            $monthlyPayment = ($loan->loan_amount * (1 + ($loan->interest_rate / 100))) / 12;

            if ($loan->loan_status === 'paid_off') {
                // Create 12 payments to fully pay off the loan
                for ($i = 1; $i <= 12; $i++) {
                    LoanPayment::create([
                        'loan_id' => $loan->loan_id,
                        'payment_amount' => round($monthlyPayment, 2),
                    ]);
                }
                $this->command->info("Created 12 payments for paid-off loan {$loan->loan_id}");
            } else {
                // Active loan - create random number of payments (3-6 payments made so far)
                $paymentsMade = rand(3, 6);
                for ($i = 1; $i <= $paymentsMade; $i++) {
                    LoanPayment::create([
                        'loan_id' => $loan->loan_id,
                        'payment_amount' => round($monthlyPayment, 2),
                    ]);
                }
                $this->command->info("Created {$paymentsMade} payments for active loan {$loan->loan_id}");
            }
        }
    }
}
