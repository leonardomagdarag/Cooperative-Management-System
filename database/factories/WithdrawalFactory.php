<?php

namespace Database\Factories;

use App\Models\Withdrawal;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class WithdrawalFactory extends Factory
{
    protected $model = Withdrawal::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'withdrawal_amount' => fake()->randomFloat(2, 500, 30000),
            'recorded_by' => 1,
        ];
    }
}
