<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        $loanTypes = [
            'Personal Loan',
            'Housing Loan',
            'Business Loan',
            'Emergency Loan',
            'Educational Loan',
            'Medical Loan'
        ];

        $status = fake()->randomElement(['pending', 'approved', 'active', 'active', 'active', 'paid_off']);
        $appliedAt = fake()->dateTimeBetween('-2 years', '-1 month');
        $approvedAt = in_array($status, ['approved', 'active', 'paid_off']) 
            ? fake()->dateTimeBetween($appliedAt, 'now') 
            : null;
        $termMonths = fake()->randomElement([6, 12, 18, 24, 36]);
        $dueDate = $approvedAt 
            ? (clone $approvedAt)->modify("+{$termMonths} months") 
            : null;

        return [
            'user_id' => \App\Models\User::factory(),
            'loan_type' => fake()->randomElement($loanTypes),
            'loan_amount' => fake()->randomFloat(2, 10000, 500000),
            'interest_rate' => fake()->randomFloat(2, 2, 5),
            'term_months' => $termMonths,
            'payment_frequency' => fake()->randomElement(['weekly', 'bi-weekly', 'monthly', 'quarterly']),
            'loan_status' => $status,
            'applied_at' => $appliedAt,
            'approved_at' => $approvedAt,
            'approved_by' => $approvedAt ? 1 : null,
            'due_date' => $status === 'paid_off' ? fake()->dateTimeBetween($appliedAt, 'now') : $dueDate,
        ];
    }
}
