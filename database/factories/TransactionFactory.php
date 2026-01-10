<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Account;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'loan_id' => null,
            'transaction_type' => fake()->randomElement(['deposit', 'withdrawal', 'loan_payment']),
            'amount' => fake()->randomFloat(2, 500, 10000),
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'note' => fake()->optional(0.3)->sentence(),
            'recorded_by' => 1,
        ];
    }

    public function deposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'deposit',
            'loan_id' => null,
        ]);
    }

    public function withdrawal(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'withdrawal',
            'loan_id' => null,
        ]);
    }

    public function loanPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => 'loan_payment',
            'loan_id' => Loan::factory(),
        ]);
    }
}
