<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;
use App\Models\User;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all member users
        $members = User::where('role', 'member')->get();

        if ($members->isEmpty()) {
            $this->command->error('No member users found. Please run UserSeeder first.');
            return;
        }

        // Create accounts for each member
        // 70% get savings only, 20% get both, 10% get share-capital only
        foreach ($members as $member) {
            $rand = rand(1, 100);

            if ($rand <= 70) {
                // Savings only
                Account::factory()->create([
                    'user_id' => $member->id,
                    'account_type' => 'savings',
                    'balance' => rand(5000, 100000),
                ]);
            } elseif ($rand <= 90) {
                // Both accounts
                Account::factory()->create([
                    'user_id' => $member->id,
                    'account_type' => 'savings',
                    'balance' => rand(5000, 100000),
                ]);
                Account::factory()->create([
                    'user_id' => $member->id,
                    'account_type' => 'share-capital',
                    'balance' => rand(5000, 50000),
                ]);
                Account::create([
                    'user_id' => $member->user_id,
                ]);
            } else {
                // Share-capital only
                Account::factory()->create([
                    'user_id' => $member->id,
                    'account_type' => 'share-capital',
                    'balance' => rand(5000, 50000),
                ]);
            }
        }

        $this->command->info('Created accounts for ' . $members->count() . ' members.');
    }
}
