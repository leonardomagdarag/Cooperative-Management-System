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

        // Only 25% of members get loans (fewer, more realistic)
        $usersWithLoans = $users->random(min($users->count(), max(5, (int) ($users->count() * 0.25))));

        foreach ($usersWithLoans as $user) {
            // Each member gets only 1 loan
            Loan::factory()->create([
                'user_id' => $user->id,
                'approved_by' => 1, // Admin user
            ]);
        }

        $totalLoans = Loan::count();
        $this->command->info("Created {$totalLoans} loans.");
    }
}
