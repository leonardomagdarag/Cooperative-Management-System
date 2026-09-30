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
 * an approved, active or paid-off loan has an approval date, a due date and an
 * approving officer.
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        $product = LoanProduct::query()->where('is_active', true)->inRandomOrder()->first()
            ?? LoanProduct::query()->inRandomOrder()->first();

        $status = fake()->randomElement(['pending', 'pending', 'approved', 'active', 'active', 'paid_off']);

        $appliedAt = fake()->dateTimeBetween('-2 years', '-1 month');
        $approvedAt = in_array($status, ['approved', 'active', 'paid_off'], true)
            ? fake()->dateTimeBetween($appliedAt, 'now')
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
            'due_date' => $status === 'paid_off'
                ? fake()->dateTimeBetween($approvedAt, 'now')
                : ($approvedAt ? (clone $approvedAt)->modify("+{$termMonths} months") : null),
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
            'due_date' => (clone ($attributes['approved_at'] ?? now()))->modify('+'.$attributes['term_months'].' months'),
        ]);
    }

    /** A loan that has been released and is being repaid. */
    public function active(): static
    {
        return $this->approved()->state(fn () => ['loan_status' => 'active']);
    }

    /** A loan that has been settled in full. */
    public function paidOff(): static
    {
        return $this->approved()->state(fn (array $attributes) => [
            'loan_status' => 'paid_off',
            'due_date' => fake()->dateTimeBetween($attributes['approved_at'] ?? now(), 'now'),
        ]);
    }
}
