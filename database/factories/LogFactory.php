<?php

namespace Database\Factories;

use App\Models\Log;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LogFactory extends Factory
{
    protected $model = Log::class;

    public function definition(): array
    {
        $activities = [
            'User Login',
            'User Logout',
            'Account Created',
            'Account Updated',
            'Loan Applied',
            'Loan Approved',
            'Deposit Recorded',
            'Withdrawal Recorded',
            'Payment Recorded',
            'Profile Updated',
            'Settings Changed',
        ];

        return [
            'user_id' => User::factory(),
            'activity' => fake()->randomElement($activities),
            'description' => fake()->optional(0.6)->sentence(),
            'ip_address' => fake()->ipv4(),
        ];
    }
}
