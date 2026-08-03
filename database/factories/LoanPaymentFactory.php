<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\LoanPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanPaymentFactory extends Factory
{
    protected $model = LoanPayment::class;

    public function definition(): array
    {
        return [
            'loan_id' => Loan::factory(),
            'payment_amount' => fake()->randomFloat(2, 1000, 20000),
            'recorded_by' => 1,
        ];
    }
}
