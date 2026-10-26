<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Loans that obey the product catalogue.
 *
 * The factory never invents a term or a rate: both are read from the seeded
 * loan product, so a factory-made loan is as valid as a real application. Only
 * approved loans have approval dates; active and paid-off loans also have a
 * disbursement date, which anchors their repayment schedule.
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function configure(): static
    {
        return $this->afterMaking(function (Loan $loan) {
            if (in_array($loan->loan_status, ['active', 'paid_off'], true) && ! $loan->disbursed_at) {
                $loan->disbursed_at = $loan->approved_at ?? now();
                $loan->due_date ??= $loan->disbursed_at->copy()->addMonths((int) ($loan->term_months ?? 12));
            }
        });
    }

    public function definition(): array
    {
        $product = LoanProduct::query()->where('is_active', true)->inRandomOrder()->first()
            ?? LoanProduct::query()->inRandomOrder()->first();

        $status = fake()->randomElement(['pending', 'pending', 'approved', 'active', 'active', 'paid_off']);

        $appliedAt = fake()->dateTimeBetween('-2 years', '-1 month');
        $approvedAt = in_array($status, ['approved', 'active', 'paid_off'], true)
            ? fake()->dateTimeBetween($appliedAt, 'now')
            : null;
        $disbursedAt = in_array($status, ['active', 'paid_off'], true)
            ? fake()->dateTimeBetween($approvedAt, 'now')
            : null;

        $termMonths = $product?->max_term ?? 12;

        $loanAmount = $product
            ? fake()->randomFloat(2, 1000, (float) $product->max_amount)
            : fake()->randomFloat(2, 5000, 50000);

        return [
            'user_id' => User::factory(),
            'branch_id' => null,
            'loan_type' => $product?->code ?? 'business',
            'loan_product' => $product?->code ?? 'business',
            'loan_amount' => $loanAmount,
            'interest_rate' => $product?->interest_rate ?? 3.00,
            'term_months' => $termMonths,
            'payment_frequency' => 'monthly',
            'loan_status' => $status,
            'review_status' => $status === 'pending' ? 'under_review' : 'endorsed_for_approval',
            'applied_at' => $appliedAt,
            'approved_at' => $approvedAt,
            'disbursed_at' => $disbursedAt,
            'due_date' => $disbursedAt ? (clone $disbursedAt)->modify("+{$termMonths} months") : null,
        ];
    }

    /** A loan that is still being reviewed. */
    public function pending(): static
    {
        return $this->state(fn () => [
            'loan_status' => 'pending',
            'review_status' => 'under_review',
            'approved_at' => null,
            'due_date' => null,
        ]);
    }

    /** A loan that has been approved but not yet released. */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'loan_status' => 'approved',
            'review_status' => 'endorsed_for_approval',
            'approved_at' => $attributes['approved_at'] ?? now(),
            'disbursed_at' => null,
            'due_date' => null,
        ]);
    }

    /** A loan that has been released and is being repaid. */
    public function active(): static
    {
        return $this->approved()->state(fn (array $attributes) => [
            'loan_status' => 'active',
            'disbursed_at' => $attributes['disbursed_at'] ?? now(),
            'due_date' => ($attributes['disbursed_at'] ?? now())->modify('+'.($attributes['term_months'] ?? 12).' months'),
        ]);
    }

    /** A loan that has been settled in full. */
    public function paidOff(): static
    {
        return $this->approved()->state(fn (array $attributes) => [
            'loan_status' => 'paid_off',
            'disbursed_at' => $attributes['disbursed_at'] ?? now()->subMonths(12),
            'due_date' => fake()->dateTimeBetween($attributes['disbursed_at'] ?? now()->subMonths(12), 'now'),
        ]);
    }
}
