<?php

namespace Database\Factories;

use App\Models\Deposit;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepositFactory extends Factory
{
    protected $model = Deposit::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'deposit_amount' => fake()->randomFloat(2, 500, 50000),
            'deposit_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'recorded_by' => 1,
        ];
    }
}
