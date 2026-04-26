<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class LeoLoanDueTestSeeder extends Seeder
{
    /**
     * Seed a deterministic loan record for testing loan due notifications.
     */
    public function run(): void
    {
        $leo = User::query()
            ->where('email', 'leo.baluyot.magdarag039@gmail.com')
            ->first();

        if (!$leo) {
            $this->command?->error('Leo test user not found. Run UserSeeder first.');
            return;
        }

        $approverId = User::query()
            ->where('email', 'admin@example.com')
            ->value('id');

        $loanNumber = 'LEODUE' . Carbon::now()->format('ymdHis');

        // Keep this idempotent for repeated test runs.
        $existing = Loan::query()
            ->where('user_id', $leo->id)
            ->where('loan_status', 'approved')
            ->whereDate('due_date', Carbon::today()->addDay())
            ->where('loan_type', 'business')
            ->first();

        if ($existing) {
            $this->command?->info("Existing test loan found: {$existing->loan_number}");
            return;
        }

        $loan = Loan::query()->create([
            'loan_number' => $loanNumber,
            'user_id' => $leo->id,
            'loan_type' => 'business',
            'loan_amount' => 12000,
            'interest_rate' => 3.00,
            'term_months' => 6,
            'payment_frequency' => 'monthly',
            'loan_status' => 'approved',
            'applied_at' => Carbon::now(),
            'approved_at' => Carbon::now(),
            'approved_by' => $approverId,
            'due_date' => Carbon::today()->addDay(),
        ]);

        $this->command?->info("Created Leo loan due test record: {$loan->loan_number}");
    }
}
