<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Seeder;

class LoanSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'member')->get();

        if ($users->isEmpty()) {
            $this->command->error('No users found. Please run UserSeeder first.');
            return;
        }

        // 40% of members get loans
        $usersWithLoans = $users->random(min($users->count(), (int) ($users->count() * 0.4)));

        foreach ($usersWithLoans as $user) {
            $loanCount = rand(1, 2);

            for ($i = 0; $i < $loanCount; $i++) {
                Loan::factory()->create([
                    'user_id' => $user->id,
                    'approved_by' => 1, // Admin user
                ]);
            }
        }

        $totalLoans = Loan::count();
        $this->command->info("Created {$totalLoans} loans.");
    }
}
