<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * A manual fixture for exercising the due-reminder command.
 *
 * This is deliberately NOT part of DatabaseSeeder: it gives the member a second
 * loan, which would break the seeded portfolio that the rest of the suite and
 * the feature tests rely on. Run it by hand when working on
 * loans:check-due-notifications:
 *
 *     php artisan db:seed --class=LeoLoanDueTestSeeder
 *
 * It creates a real business loan, under the terms of the seeded business
 * product, that falls due tomorrow, so the command has something to remind
 * about.
 */
class LeoLoanDueTestSeeder extends Seeder
{
    /** A fixed number keeps repeated runs from piling up records. */
    private const LOAN_NUMBER = 'LOAN900001';

    public function run(): void
    {
        $leo = User::query()->where('email', 'leo.baluyot.magdarag039@gmail.com')->first();

        if (! $leo) {
            $this->command?->error('Leo test user not found. Run UserSeeder first.');

            return;
        }

        // A soft-deleted row still holds the unique loan number, so look
        // through the trash before deciding the fixture is absent.
        $existing = Loan::withTrashed()->where('loan_number', self::LOAN_NUMBER)->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $this->command?->info("Restored the existing test loan: {$existing->loan_number}");
            } else {
                $this->command?->info("Existing test loan found: {$existing->loan_number}");
            }

            return;
        }

        $product = LoanProduct::query()->where('code', 'business')->first();

        if (! $product) {
            $this->command?->error('Business loan product not found. Run LoanProductSeeder first.');

            return;
        }

        $approverId = User::query()->where('email', 'admin@example.com')->value('id');
        $officerId = User::query()->where('email', 'loan@example.com')->value('id');

        $dueDate = Carbon::today()->addDay();

        $loan = Loan::query()->create([
            'loan_number' => self::LOAN_NUMBER,
            'user_id' => $leo->id,
            'branch_id' => $leo->branch_id,
            'loan_type' => $product->code,
            'loan_product' => $product->code,
            'loan_amount' => 12000,
            'interest_rate' => $product->interest_rate,
            'term_months' => $product->max_term,
            'payment_frequency' => 'monthly',
            'loan_status' => 'approved',
            'review_status' => 'endorsed_for_approval',
            'reviewed_by' => $officerId,
            'reviewed_at' => Carbon::now()->subDays(20),
            'applied_at' => Carbon::now()->subDays(18),
            'approved_at' => Carbon::now()->subDay(),
            'approved_by' => $approverId,
            'due_date' => $dueDate,
            'employment_id' => $leo->id,
            'interview_status' => 'completed',
            'interview_officer_id' => $officerId,
        ]);

        $this->command?->info("Created Leo loan due test record: {$loan->loan_number}, due {$dueDate->toDateString()}");
    }
}
