<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_type' => fake()->randomElement(['savings', 'share-capital']),
            'balance' => fake()->randomFloat(2, 1000, 500000),
        ];
    }

    public function savings(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'savings',
        ]);
    }

    public function shareCapital(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'share-capital',
            'balance' => fake()->randomFloat(2, 5000, 50000),
        ]);
    }
}
