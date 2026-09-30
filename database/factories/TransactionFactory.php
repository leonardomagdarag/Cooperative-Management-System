<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Ledger entries.
 *
 * A factory entry has to look like something the counter actually posted: it
 * carries a number, a mode, a status consistent with that mode, and a branch and
 * a recorder. A counter entry is completed, while an online or bank transfer
 * entry is still pending until someone approves it. A payment can only be
 * booked against a released loan, so the default type is a deposit or a
 * withdrawal and loanPayment() supplies the loan.
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $mode = fake()->randomElement(['counter', 'counter', 'counter', 'online', 'bank_transfer']);

        $date = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'transaction_number' => 'TXN'.$date->format('ymd').fake()->unique()->numerify('######'),
            'transaction_type' => fake()->randomElement(['deposit', 'deposit', 'withdrawal']),
            'transaction_mode' => $mode,
            'status' => $mode === 'counter' ? 'completed' : 'pending',
            'account_id' => Account::factory(),
            'loan_id' => null,
            'amount' => fake()->randomFloat(2, 200, 10000),
            'transaction_date' => $date,
            'note' => fake()->optional(0.3)->sentence(),
            'recorded_by' => User::factory(),
            'branch_id' => fn (array $attributes) => Account::query()
                ->with('user:id,branch_id')
                ->find($attributes['account_id'])?->user?->branch_id,
        ];
    }

    public function deposit(): static
    {
        return $this->state(fn () => [
            'transaction_type' => 'deposit',
            'loan_id' => null,
        ]);
    }

    public function withdrawal(): static
    {
        return $this->state(fn () => [
            'transaction_type' => 'withdrawal',
            'loan_id' => null,
        ]);
    }

    /**
     * A payment against a loan. The loan has to be one that was actually
     * released, because an approved or pending loan cannot carry payments.
     */
    public function loanPayment(): static
    {
        return $this->state(fn () => [
            'transaction_type' => 'loan_payment',
            'loan_id' => Loan::factory()->active(),
        ]);
    }

    /** An entry booked over the counter, already completed. */
    public function atCounter(): static
    {
        return $this->state(fn () => [
            'transaction_mode' => 'counter',
            'status' => 'completed',
        ]);
    }

    /** An entry still waiting for an officer to approve it. */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_mode' => $attributes['transaction_mode'] === 'counter' ? 'online' : $attributes['transaction_mode'],
            'status' => 'pending',
        ]);
    }
}
