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
        $users = User::query()->memberUsers()->get();
        $leoEmail = 'leo.baluyot.magdarag039@gmail.com';

        if ($users->isEmpty()) {
            $this->command->error('No users found. Please run UserSeeder first.');
            return;
        }

        // Only 25% of members get loans (fewer, more realistic), but Leo must always have one.
        $loanTargetCount = min($users->count(), max(5, (int) ($users->count() * 0.25)));
        $leo = $users->firstWhere('email', $leoEmail);

        if ($leo) {
            $usersWithLoans = $users
                ->reject(fn (User $user) => $user->is($leo))
                ->shuffle()
                ->take(max(0, $loanTargetCount - 1))
                ->prepend($leo);
        } else {
            $usersWithLoans = $users->shuffle()->take($loanTargetCount);
        }

        foreach ($usersWithLoans as $user) {
            // Each member gets only 1 loan
            $loanAttributes = [
                'user_id' => $user->id,
                'approved_by' => 1, // Admin user
            ];

            if ($leo && $user->is($leo)) {
                $loanAttributes['loan_status'] = 'active';
            }

            Loan::factory()->create($loanAttributes);
        }

        $totalLoans = Loan::count();
        $this->command->info("Created {$totalLoans} loans.");
    }
}
