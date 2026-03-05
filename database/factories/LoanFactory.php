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
        $loanType = fake()->randomElement(['group', 'business', 'special']);

        $status = fake()->randomElement(['pending', 'approved', 'active', 'active', 'active', 'paid_off']);
        $appliedAt = fake()->dateTimeBetween('-2 years', '-1 month');
        $approvedAt = in_array($status, ['approved', 'active', 'paid_off'])
            ? fake()->dateTimeBetween($appliedAt, 'now')
            : null;
        $termMonths = fake()->randomElement([6, 12, 18, 24, 36]);
        $dueDate = $approvedAt
            ? (clone $approvedAt)->modify("+{$termMonths} months")
            : null;

        // Realistic loan amounts based on type (max 50,000)
        $loanAmount = match ($loanType) {
            'special' => fake()->randomFloat(2, 3000, 15000),  // Small emergency loans
            'business' => fake()->randomFloat(2, 10000, 50000), // Business loans
            'group' => fake()->randomFloat(2, 5000, 30000),     // Group loans
            default => fake()->randomFloat(2, 5000, 25000),
        };

        return [
            'user_id' => \App\Models\User::factory(),
            'loan_type' => $loanType,
            'loan_amount' => $loanAmount,
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
