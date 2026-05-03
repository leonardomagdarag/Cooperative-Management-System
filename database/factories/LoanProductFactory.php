<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoanProduct>
 */
class LoanProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code'                       => fake()->unique()->slug(2),
            'name'                       => fake()->words(3, true),
            'description'                => fake()->sentence(),
            'base_type'                  => fake()->randomElement(['group', 'business', 'special']),
            'icon'                       => 'wallet',
            'interest_rate'              => fake()->randomFloat(2, 0.5, 10),
            'max_amount'                 => fake()->numberBetween(1000, 100000),
            'max_term'                   => fake()->numberBetween(1, 60),
            'is_active'                  => true,
            'requires_no_existing_loan'  => false,
            'minimum_share_capital'      => 0,
            'minimum_membership_years'   => 0,
            'required_co_makers'         => 0,
            'requires_interview'         => false,
            'requires_special_eligibility' => false,
            'sort_order'                 => fake()->numberBetween(1, 99),
        ];
    }
}
