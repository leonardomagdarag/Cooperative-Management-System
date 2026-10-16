<?php

namespace Database\Seeders;

use App\Models\CollectionRun;
use App\Models\CollectionRunApproval;
use App\Models\CollectionRunItem;
use App\Models\Loan;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\TransactionReceipt;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The loan officer's collection routes.
 *
 * One route has been walked, approved through both stages and posted to the
 * ledger; a second is still sitting at the accountant's desk waiting for the
 * first signature. Items are written straight to the tables instead of going
 * through CollectionRunService, because the service logs and would re-log on
 * every seed; the posted transactions still leave the ledger in the same shape
 * the service produces, with a null account so a collection payment moves the
 * loan's running balance without touching savings.
 *
 * If any run already exists the seeder leaves the desk alone, so re-seeding does
 * not pile up routes or re-post payments.
 */
class CollectionRunSeeder extends Seeder
{
    public function run(): void
    {
        if (CollectionRun::query()->exists()) {
            return;
        }

        $officer = User::query()->where('user_number', 'USR000005')->first();
        $reviewer = User::query()->where('user_number', 'USR000004')->first();
        $approver = User::query()->where('user_number', 'USR000003')->first();
        $branchId = $officer?->branch_id;

        if (! $officer || ! $reviewer || ! $approver || ! $branchId) {
            return;
        }

        $loans = Loan::query()
            ->where('branch_id', $branchId)
            ->where('loan_status', 'active')
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($loans->isEmpty()) {
            return;
        }

        $stages = SystemSetting::get('collections.approval_stages', [
            ['key' => 'review', 'label' => 'Accountant Review', 'permission' => 'collections.review'],
            ['key' => 'approve', 'label' => 'Manager Approval', 'permission' => 'collections.approve'],
        ]);

        $this->completedRun($officer, $reviewer, $approver, $branchId, $loans, $stages);
        $this->pendingRun($officer, $branchId, $loans, $stages);

        $this->command?->info('Recorded 2 collection runs.');
    }

    /**
     * A route that has been walked, approved and posted.
     */
    private function completedRun(User $officer, User $reviewer, User $approver, int $branchId, $loans, array $stages): void
    {
        $collectionDate = now()->subDays(5)->startOfDay();
        $runNumber = 'CR-'.$collectionDate->format('Ymd').'-'.$officer->id.'-SEED1';

        if (CollectionRun::query()->where('run_number', $runNumber)->exists()) {
            return;
        }

        $run = CollectionRun::create([
            'run_number' => $runNumber,
            'loan_officer_id' => $officer->id,
            'collection_date' => $collectionDate->toDateString(),
            'branch_id' => $branchId,
            'status' => 'approved',
            'current_stage' => count($stages) - 1,
            'stages_snapshot' => $stages,
            'is_late' => false,
            'submitted_at' => $collectionDate->copy()->setTime(17, 10),
            'decided_at' => $collectionDate->copy()->addDay()->setTime(9, 5),
        ]);

        $total = 0.0;
        $count = 0;

        foreach ($loans as $loan) {
            $amount = round(min((float) $loan->monthlyPayment(), (float) $loan->remainingBalance()), 2);

            if ($amount <= 0) {
                continue;
            }

            $postedAt = $collectionDate->copy()->setTime(14, 30);

            $transaction = Transaction::create([
                'account_id' => null,
                'branch_id' => $branchId,
                'loan_id' => $loan->id,
                'transaction_type' => 'loan_payment',
                'transaction_mode' => 'counter',
                'amount' => $amount,
                'status' => 'completed',
                'transaction_date' => $postedAt,
                'note' => "Collection Run {$run->run_number} Payment",
                'recorded_by' => $officer->id,
                'approved_by' => $approver->id,
                'approved_at' => $postedAt,
            ]);

            CollectionRunItem::create([
                'collection_run_id' => $run->id,
                'member_id' => $loan->user_id,
                'recorded_by' => $officer->id,
                'loan_id' => $loan->id,
                'account_id' => null,
                'amount' => $amount,
                'note' => 'House visit — collected on the route.',
                'visited' => true,
                'outcome' => 'applied',
                'transaction_id' => $transaction->id,
            ]);

            TransactionReceipt::firstOrCreate(
                ['transaction_id' => $transaction->id, 'reason' => 'original'],
                [
                    'issued_by' => $approver->id,
                    'issued_to_member_id' => $loan->user_id,
                    'certified' => false,
                ],
            );

            $total += $amount;
            $count++;
        }

        $run->update(['total_amount' => round($total, 2), 'item_count' => $count]);

        CollectionRunApproval::create([
            'collection_run_id' => $run->id,
            'stage_index' => 0,
            'stage_key' => $stages[0]['key'],
            'stage_label' => $stages[0]['label'],
            'decided_by' => $reviewer->id,
            'decided_at' => $collectionDate->copy()->addDay()->setTime(8, 30),
            'action' => 'approved',
        ]);

        CollectionRunApproval::create([
            'collection_run_id' => $run->id,
            'stage_index' => 1,
            'stage_key' => $stages[1]['key'],
            'stage_label' => $stages[1]['label'],
            'decided_by' => $approver->id,
            'decided_at' => $collectionDate->copy()->addDay()->setTime(9, 5),
            'action' => 'approved',
        ]);
    }

    /**
     * A route that is still waiting at the first approval stage.
     */
    private function pendingRun(User $officer, int $branchId, $loans, array $stages): void
    {
        $collectionDate = now()->subDays(2)->startOfDay();
        $runNumber = 'CR-'.$collectionDate->format('Ymd').'-'.$officer->id.'-SEED2';

        if (CollectionRun::query()->where('run_number', $runNumber)->exists()) {
            return;
        }

        $run = CollectionRun::create([
            'run_number' => $runNumber,
            'loan_officer_id' => $officer->id,
            'collection_date' => $collectionDate->toDateString(),
            'branch_id' => $branchId,
            'status' => 'submitted',
            'current_stage' => 0,
            'stages_snapshot' => $stages,
            'is_late' => false,
            'submitted_at' => $collectionDate->copy()->setTime(16, 45),
        ]);

        $total = 0.0;
        $count = 0;

        foreach ($loans as $loan) {
            $amount = round(min((float) $loan->monthlyPayment(), (float) $loan->remainingBalance()), 2);

            if ($amount <= 0) {
                continue;
            }

            CollectionRunItem::create([
                'collection_run_id' => $run->id,
                'member_id' => $loan->user_id,
                'recorded_by' => $officer->id,
                'loan_id' => $loan->id,
                'account_id' => null,
                'amount' => $amount,
                'note' => 'Scheduled for the next route.',
                'visited' => true,
                'outcome' => 'pending',
            ]);

            $total += $amount;
            $count++;
        }

        $run->update(['total_amount' => round($total, 2), 'item_count' => $count]);
    }
}
