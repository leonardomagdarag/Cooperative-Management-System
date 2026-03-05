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
        // All members get both savings and share capital accounts
        foreach ($members as $member) {
            // Create savings account
            Account::factory()->create([
                'user_id' => $member->id,
                'account_type' => 'savings',
                'balance' => rand(1000, 25000),
            ]);

            // Create share capital account
            Account::factory()->create([
                'user_id' => $member->id,
                'account_type' => 'share-capital',
                'balance' => rand(500, 5000),
            ]);
        }

        $this->command->info('Created savings and share capital accounts for ' . $members->count() . ' members.');
    }
}
