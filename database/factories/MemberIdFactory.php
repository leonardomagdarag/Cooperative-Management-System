<?php

namespace Database\Factories;

use App\Models\MemberId;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberIdFactory extends Factory
{
    protected $model = MemberId::class;

    public function definition(): array
    {
        return [
            'national_id' => fake()->optional(0.7)->numerify('####-####-####-####'),
            'tin' => fake()->optional(0.8)->numerify('###-###-###-###'),
            'sss' => fake()->optional(0.8)->numerify('##-#######-#'),
            'philhealth' => fake()->optional(0.7)->numerify('##-#########-#'),
            'barangay_id' => fake()->optional(0.6)->bothify('BRG-####-###'),
        ];
    }
}
