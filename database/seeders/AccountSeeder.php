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
        // Get member users (excluding the first member which is the test account)
        $members = User::where('role', 'member')
            ->whereIn('email', [
                'cris.jarabejo@example.com',
                'alyson.penano@example.com',
                'glendo.camarines@example.com'
            ])
            ->get();

        if ($members->count() < 3) {
            $this->command->error('Not enough member users found. Please run UserSeeder first.');
            return;
        }

        // Create 3 savings accounts for the 3 different users
        foreach ($members as $index => $member) {
            Account::create([
                'user_id' => $member->user_id,
                'account_type' => 'savings',
                'balance' => 0, // Will be updated by deposits
            ]);

            $this->command->info("Created savings account for {$member->email}");
        }
    }
}
