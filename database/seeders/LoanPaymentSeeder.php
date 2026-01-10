<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LoanPayment;
use App\Models\Loan;

class LoanPaymentSeeder extends Seeder
{
    public function run(): void
    {
        $loans = Loan::all();

        if ($loans->isEmpty()) {
            $this->command->error('No loans found. Please run LoanSeeder first.');
            return;
        }

        foreach ($loans as $loan) {
            if ($loan->loan_status === 'paid_off') {
                $totalAmount = $loan->loan_amount * (1 + ($loan->interest_rate / 100));
                $paymentCount = rand(3, 12);
                $paymentAmount = $totalAmount / $paymentCount;

                for ($i = 0; $i < $paymentCount; $i++) {
                    LoanPayment::create([
                        'loan_id' => $loan->loan_id,
                        'payment_amount' => round($paymentAmount, 2),
                    ]);
                }
            } elseif ($loan->loan_status === 'active') {
                $paymentCount = rand(1, 6);
                $totalAmount = $loan->loan_amount * (1 + ($loan->interest_rate / 100));
                $paymentAmount = $totalAmount / rand(12, 36);

                for ($i = 0; $i < $paymentCount; $i++) {
                    LoanPayment::create([
                        'loan_id' => $loan->loan_id,
                        'payment_amount' => round($paymentAmount, 2),
                    ]);
                }
            } elseif ($loan->loan_status === 'defaulted') {
                $paymentCount = rand(0, 2);
                if ($paymentCount > 0) {
                    $totalAmount = $loan->loan_amount * (1 + ($loan->interest_rate / 100));
                    $paymentAmount = $totalAmount / rand(12, 36);

                    for ($i = 0; $i < $paymentCount; $i++) {
                        LoanPayment::create([
                            'loan_id' => $loan->loan_id,
                            'payment_amount' => round($paymentAmount, 2),
                        ]);
                    }
                }
            }
        }

        $totalPayments = LoanPayment::count();
        $this->command->info("Created {$totalPayments} loan payments.");
    }
}
