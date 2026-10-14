<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CollectionRun;
use App\Models\CollectionRunItem;
use App\Models\Loan;
use App\Models\Notification;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\TransactionReceipt;
use App\Models\User;
use App\Support\BranchScope;
use App\Support\PermissionCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CollectionRunService
{
    public function createRun(User $officer, string $date, ?int $branchId = null): CollectionRun
    {
        return DB::transaction(function () use ($officer, $date, $branchId) {
            // Check if there is an existing draft run for this date
            $existing = CollectionRun::where('loan_officer_id', $officer->id)
                ->where('collection_date', $date)
                ->where('branch_id', $branchId ?? $officer->branch_id)
                ->where('status', 'draft')
                ->first();

            if ($existing) {
                return $existing;
            }

            // run_number could be CR-YYYYMMDD-USERID-RANDOM
            $runNumber = 'CR-'.Carbon::parse($date)->format('Ymd').'-'.$officer->id.'-'.strtoupper(Str::random(4));

            $run = CollectionRun::create([
                'run_number' => $runNumber,
                'loan_officer_id' => $officer->id,
                'collection_date' => $date,
                'branch_id' => $branchId ?? $officer->branch_id,
                'status' => 'draft',
            ]);

            app(AuditLogService::class)->log(
                'collections.run_created',
                'Collection run created.',
                'collection_run',
                $run,
                $officer,
                ['run_number' => $run->run_number],
            );

            return $run;
        });
    }

    public function addItem(CollectionRun $run, array $data): CollectionRunItem
    {
        return DB::transaction(function () use ($run, $data) {
            $run = CollectionRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            abort_unless($run->status === 'draft', 409, 'Cannot add items to a non-draft run.');
            $accountId = $data['account_id'] ?? null;
            $this->validateItemReferences($run, $data['member_id'], $data['loan_id'], $accountId, (float) $data['amount']);

            $item = $run->items()->create([
                'member_id' => $data['member_id'],
                'recorded_by' => $run->loan_officer_id,
                'loan_id' => $data['loan_id'],
                'account_id' => $accountId,
                'amount' => $data['amount'],
                'note' => $data['note'] ?? null,
                'visited' => $data['visited'] ?? true,
                'outcome' => 'pending',
            ]);

            $this->recalculateRun($run);

            return $item;
        });
    }

    public function removeItem(CollectionRunItem $item): void
    {
        DB::transaction(function () use ($item) {
            $run = CollectionRun::whereKey($item->collection_run_id)->lockForUpdate()->firstOrFail();
            abort_unless($run->status === 'draft', 409, 'Cannot remove items from a non-draft run.');
            $lockedItem = CollectionRunItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $lockedItem->delete();
            $this->recalculateRun($run);
        });
    }

    public function updateItem(CollectionRunItem $item, array $data): CollectionRunItem
    {
        return DB::transaction(function () use ($item, $data) {
            $runId = CollectionRunItem::whereKey($item->id)->value('collection_run_id');
            $run = CollectionRun::whereKey($runId)->lockForUpdate()->firstOrFail();
            $lockedItem = CollectionRunItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            abort_unless($run->status === 'draft', 409, 'Only draft run items can be changed.');
            $this->validateItemReferences($run, $lockedItem->member_id, $lockedItem->loan_id, $lockedItem->account_id, (float) $data['amount']);
            $lockedItem->update([
                'amount' => $data['amount'],
                'note' => $data['note'] ?? null,
                'visited' => $data['visited'] ?? false,
            ]);
            $this->recalculateRun($run);

            return $lockedItem->fresh();
        });
    }

    private function recalculateRun(CollectionRun $run): void
    {
        $aggregates = $run->items()->selectRaw('COUNT(*) as count, SUM(amount) as total')->first();
        $run->update([
            'item_count' => $aggregates->count ?? 0,
            'total_amount' => $aggregates->total ?? 0,
        ]);
    }

    public function submitRun(CollectionRun $run, User $submittedBy, ?string $lateReason = null): CollectionRun
    {
        return DB::transaction(function () use ($run, $submittedBy, $lateReason) {
            $run = CollectionRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            abort_unless($run->status === 'draft', 409, 'Only draft runs can be submitted.');
            abort_if($run->items()->count() === 0, 422, 'Cannot submit an empty run.');

            // Validate all items
            $items = $run->items()->with(['loan', 'account'])->get();
            $loanTotals = [];
            foreach ($items as $item) {
                $this->validateItemReferences($run, $item->member_id, $item->loan_id, $item->account_id, (float) $item->amount);
                $loanTotals[$item->loan_id] = ($loanTotals[$item->loan_id] ?? 0) + (float) $item->amount;
            }
            foreach ($loanTotals as $loanId => $amount) {
                abort_if($amount > (float) Loan::findOrFail($loanId)->remainingBalance() + 0.01, 422, "Loan {$loanId} does not have enough remaining balance for this run.");
            }

            $stagesSnapshot = SystemSetting::get('collections.approval_stages', []);
            $this->validateStages($stagesSnapshot);

            // Check if late
            $graceMinutes = (int) (SystemSetting::where('key', 'collections.submit_grace_minutes')->value('value') ?? 0);
            $cutoff = Carbon::parse($run->collection_date)->endOfDay()->addMinutes($graceMinutes);
            $isLate = now()->isAfter($cutoff);

            if ($isLate) {
                // If it's late, the officer cannot submit it themselves without oversight.
                // The requirements state: "Past the window the officer cannot self-submit. A manager or chairman may file the run on the officer's behalf, which requires a written reason..."
                if ($submittedBy->id === $run->loan_officer_id) {
                    abort(422, 'Submission deadline has passed. A manager must submit this run on your behalf.');
                }
                abort_unless(! empty($lateReason), 422, 'A written reason is required for a late submission.');
                abort_unless($submittedBy->can('collections.approve') || $submittedBy->can('oversight.approve'), 403, 'You do not have permission to submit a late run.');

                app(AuditLogService::class)->log(
                    'collections.late_submission',
                    "Late run {$run->run_number} submitted by manager. Reason: {$lateReason}",
                    'collection_run',
                    $run,
                    $submittedBy,
                );
            }

            abort_if($submittedBy->id !== $run->loan_officer_id && ! $isLate, 403, 'Only the run officer may submit on time.');

            $run->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'stages_snapshot' => $stagesSnapshot,
                'current_stage' => 0,
                'is_late' => $isLate,
            ]);

            return $run;
        });
    }

    public function approveStage(CollectionRun $run, User $decider, string $action, ?string $note = null): CollectionRun
    {
        abort_unless(in_array($run->status, ['submitted', 'in_progress']), 422, 'Run is not pending approval.');
        abort_if($action === 'rejected' && empty($note), 422, 'A reason is required when rejecting.');

        $decision = DB::transaction(function () use ($run, $decider, $action, $note) {
            $run = CollectionRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($run->status, ['submitted', 'in_progress']), 409, 'Run is no longer pending approval.');
            $stages = $run->stages_snapshot ?? [];
            $currentStageIndex = (int) $run->current_stage;
            $stage = $stages[$currentStageIndex] ?? null;
            abort_unless($stage, 422, 'Invalid stage configuration.');
            abort_unless($decider->can($stage['permission']), 403, 'You do not have the required permission for this stage.');
            abort_if((int) $decider->id === (int) $run->loan_officer_id, 403, 'You cannot approve your own collection run.');
            abort_if($run->items()->where('recorded_by', $decider->id)->exists(), 403, 'You cannot decide a run containing an item you recorded.');
            $priorDecision = $run->approvals()->where('stage_index', $currentStageIndex)->first();
            if ($priorDecision) {
                abort_unless($priorDecision->action === 'approved' && $currentStageIndex === count($stages) - 1, 409, 'This stage has already been decided.');

                return ['run' => $run, 'finalize' => true, 'final_approver_id' => $priorDecision->decided_by];
            }
            $run->approvals()->create([
                'stage_index' => $currentStageIndex,
                'stage_key' => $stage['key'],
                'stage_label' => $stage['label'],
                'decided_by' => $decider->id,
                'decided_at' => now(),
                'action' => $action,
                'note' => $note,
            ]);

            if ($action === 'rejected') {
                $run->update([
                    'status' => 'rejected',
                    'rejection_reason' => $note,
                    'decided_at' => now(),
                ]);

                return ['run' => $run, 'finalize' => false];
            }

            // Action is approved
            $nextStageIndex = $currentStageIndex + 1;
            if ($nextStageIndex >= count($stages)) {
                $run->update(['status' => 'in_progress']);

                return ['run' => $run->fresh(), 'finalize' => true, 'final_approver_id' => $decider->id];
            }

            // Move to next stage
            $run->update([
                'status' => 'in_progress',
                'current_stage' => $nextStageIndex,
            ]);

            return ['run' => $run, 'finalize' => false];
        });

        return $decision['finalize']
            ? $this->finalizeRun($decision['run'], User::withTrashed()->findOrFail($decision['final_approver_id']))
            : $decision['run'];
    }

    private function finalizeRun(CollectionRun $run, User $finalApprover): CollectionRun
    {
        $run = CollectionRun::findOrFail($run->id);
        abort_unless($run->status === 'in_progress', 422, 'Run is not ready for finalization.');

        foreach ($run->items as $item) {
            if ($item->outcome !== 'pending') {
                continue; // Already processed
            }

            try {
                DB::transaction(function () use ($run, $item, $finalApprover) {
                    $lockedItem = CollectionRunItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
                    if ($lockedItem->outcome !== 'pending') {
                        return;
                    }
                    $loan = Loan::whereKey($lockedItem->loan_id)->lockForUpdate()->firstOrFail();
                    abort_if($loan->loan_status === 'paid_off', 422, 'Loan is already paid off.');
                    abort_if((float) $lockedItem->amount > (float) $loan->remainingBalance() + 0.01, 422, 'Payment exceeds the remaining loan balance.');
                    $transaction = Transaction::create([
                        'account_id' => $lockedItem->account_id,
                        'amount' => $lockedItem->amount,
                        'transaction_type' => 'loan_payment',
                        'status' => 'completed',
                        'transaction_mode' => 'counter',
                        'note' => "Collection Run {$run->run_number} Payment",
                        'recorded_by' => $lockedItem->recorded_by,
                        'approved_by' => $finalApprover->id,
                        'approved_at' => now(),
                        'branch_id' => $run->branch_id,
                        'loan_id' => $lockedItem->loan_id,
                    ]);
                    $loan->refresh();
                    if ($loan->remainingBalance() <= 0 && $loan->loan_status !== 'paid_off') {
                        $loan->update(['loan_status' => 'paid_off']);
                    }
                    $lockedItem->update([
                        'outcome' => 'applied',
                        'transaction_id' => $transaction->id,
                    ]);
                    TransactionReceipt::firstOrCreate([
                        'transaction_id' => $transaction->id,
                        'reason' => 'original',
                    ], [
                        'issued_by' => $finalApprover->id,
                        'issued_to_member_id' => $lockedItem->member_id,
                        'certified' => false,
                    ]);
                    app(AuditLogService::class)->log('collections.payment_applied', 'Collection item posted and original receipt issued.', 'transaction', $transaction, $finalApprover);
                });

            } catch (\Exception $e) {
                DB::transaction(function () use ($item, $e) {
                    $lockedItem = CollectionRunItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
                    if ($lockedItem->outcome === 'pending') {
                        $lockedItem->update(['outcome' => 'failed', 'failure_reason' => $e->getMessage()]);
                    }
                });
            }
        }

        return DB::transaction(function () use ($run) {
            $lockedRun = CollectionRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            if ($lockedRun->items()->where('outcome', 'pending')->exists()) {
                return $lockedRun;
            }
            $hasFailures = $lockedRun->items()->where('outcome', 'failed')->exists();
            $lockedRun->update([
                'status' => $hasFailures ? 'partially_applied' : 'approved',
                'decided_at' => now(),
            ]);

            return $lockedRun->fresh();
        });
    }

    public function retryFailedItems(CollectionRun $run, User $actor, string $reason): CollectionRun
    {
        $run = DB::transaction(function () use ($run, $actor, $reason) {
            $run = CollectionRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            abort_unless($run->status === 'partially_applied', 409, 'Only partially applied runs can be retried.');
            abort_unless($actor->can('collections.approve'), 403, 'The final approval permission is required to retry collection items.');
            abort_if((int) $actor->id === (int) $run->loan_officer_id, 403, 'The run officer cannot retry their own collection items.');
            abort_if($run->items()->where('recorded_by', $actor->id)->exists(), 403, 'You cannot decide a run containing an item you recorded.');

            $failedItems = $run->items()->where('outcome', 'failed')->lockForUpdate()->get();
            abort_if($failedItems->isEmpty(), 422, 'No failed items to retry.');
            abort_if(trim($reason) === '', 422, 'A reason is required to retry failed items.');

            foreach ($failedItems as $item) {
                $item->update(['outcome' => 'pending', 'failure_reason' => null]);
            }

            $run->update(['status' => 'in_progress']);
            app(AuditLogService::class)->log(
                'collections.failed_items_retried',
                "Retrying {$failedItems->count()} failed collection item(s). Reason: {$reason}",
                'collection_run',
                $run,
                $actor,
                ['item_ids' => $failedItems->modelKeys()]
            );

            return $run->fresh();
        });

        return $this->finalizeRun($run, $actor);
    }

    private function validateItemReferences(CollectionRun $run, int $memberId, int $loanId, ?int $accountId, float $amount): void
    {
        $loan = Loan::findOrFail($loanId);
        abort_unless((int) $loan->user_id === $memberId, 422, 'The loan must belong to the selected member.');
        abort_unless((int) $loan->branch_id === (int) $run->branch_id, 422, 'Loan must belong to the collection run branch.');
        if ($accountId !== null) {
            $account = Account::findOrFail($accountId);
            abort_unless((int) $account->user_id === $memberId && $account->account_type === 'savings', 422, 'The account must be a savings account belonging to the selected member.');
        }
        abort_unless(in_array($loan->loan_status, ['approved', 'active'], true), 422, 'Loan is not open for payment.');
        abort_unless($amount > 0 && $amount <= (float) $loan->remainingBalance() + 0.01, 422, 'Payment amount exceeds the loan balance.');

        // A loan already recorded in another run for the same date is off the
        // route list; block it here too so a stale client cannot double-collect.
        $conflict = CollectionRun::query()
            ->whereKeyNot($run->id)
            ->whereDate('collection_date', $run->collection_date)
            ->whereIn('status', ['submitted', 'in_progress', 'partially_applied'])
            ->whereHas('items', fn ($query) => $query->where('loan_id', $loanId))
            ->first(['id', 'run_number']);

        if ($conflict !== null) {
            abort(409, "Loan {$loan->loan_number} is already recorded in run {$conflict->run_number} for the same date.");
        }
    }

    public function validateStages(mixed $stages): void
    {
        abort_unless(is_array($stages) && count($stages) > 0, 422, 'Approval stages must be a non-empty list.');
        $permissions = PermissionCatalog::allPermissions();
        $keys = [];
        foreach ($stages as $stage) {
            abort_unless(is_array($stage) && isset($stage['key'], $stage['label'], $stage['permission']), 422, 'Each approval stage needs a key, label, and permission.');
            abort_unless(is_string($stage['key']) && $stage['key'] !== '' && ! in_array($stage['key'], $keys, true), 422, 'Approval stage keys must be unique.');
            abort_unless(in_array($stage['permission'], $permissions, true), 422, 'Approval stage permission is invalid.');
            $keys[] = $stage['key'];
        }
        abort_unless(end($stages)['permission'] === 'collections.approve', 422, 'The final approval stage must require collections.approve.');
    }

    public function voidOrphanedRun(CollectionRun $run, User $manager, string $reason): CollectionRun
    {
        return DB::transaction(function () use ($run, $manager, $reason) {
            $run = CollectionRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            abort_unless($run->status === 'draft' && $run->orphaned_at !== null, 409, 'Only orphaned draft runs can be voided.');
            abort_unless($run->loanOfficer()->whereNotNull('deleted_at')->exists(), 409, 'The run officer is active.');
            abort_unless($manager->can('collections.approve') || $manager->can('oversight.approve'), 403);
            abort_if(trim($reason) === '', 422, 'A reason is required to void an orphaned run.');

            $run->update([
                'status' => 'voided',
                'voided_reason' => $reason,
                'decided_at' => now(),
            ]);

            app(AuditLogService::class)->log(
                'collections.orphaned_voided',
                "Orphaned run {$run->run_number} voided by manager. Reason: {$reason}",
                'collection_run',
                $run,
                $manager,
            );

            return $run;
        });
    }

    public function reassignOrphanedRun(CollectionRun $run, User $manager, User $newOfficer, string $reason): CollectionRun
    {
        return DB::transaction(function () use ($run, $manager, $newOfficer, $reason) {
            $run = CollectionRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
            abort_unless($run->status === 'draft' && $run->orphaned_at !== null, 409, 'Only orphaned draft runs can be reassigned.');
            abort_unless($run->loanOfficer()->whereNotNull('deleted_at')->exists(), 409, 'The run officer is active.');
            abort_unless($manager->can('collections.approve') || $manager->can('oversight.approve'), 403);
            abort_unless($newOfficer->trashed() === false && $newOfficer->can('collections.create'), 422, 'Choose an active loan officer.');
            abort_unless((int) $newOfficer->branch_id === (int) $run->branch_id || BranchScope::isUnrestricted($newOfficer), 422, 'The replacement officer must cover this branch.');
            abort_if(trim($reason) === '', 422, 'A reason is required to reassign an orphaned run.');

            $formerOfficerId = $run->loan_officer_id;
            $run->update([
                'loan_officer_id' => $newOfficer->id,
                'orphaned_at' => null,
            ]);

            app(AuditLogService::class)->log(
                'collections.orphaned_reassigned',
                "Orphaned run {$run->run_number} reassigned. Reason: {$reason}",
                'collection_run',
                $run,
                $manager,
                ['former_officer_id' => $formerOfficerId, 'new_officer_id' => $newOfficer->id]
            );

            return $run->fresh();
        });
    }

    public function createReplacementRun(CollectionRun $rejectedRun, User $manager, User $officer): CollectionRun
    {
        return DB::transaction(function () use ($rejectedRun, $manager, $officer) {
            $rejectedRun = CollectionRun::whereKey($rejectedRun->id)->lockForUpdate()->firstOrFail();
            abort_unless($rejectedRun->status === 'rejected', 409, 'Only rejected runs can have a replacement.');
            abort_unless($manager->can('collections.approve') || $manager->can('oversight.approve'), 403);
            abort_unless(! $officer->trashed() && $officer->can('collections.create'), 422, 'Choose an active loan officer.');
            abort_unless((int) $officer->branch_id === (int) $rejectedRun->branch_id || BranchScope::isUnrestricted($officer), 422, 'The replacement officer must cover this branch.');
            abort_if($rejectedRun->resubmission()->exists(), 409, 'A replacement run already exists.');

            $replacement = CollectionRun::create([
                'run_number' => 'CR-'.now()->format('Ymd').'-'.$officer->id.'-'.strtoupper(Str::random(4)),
                'loan_officer_id' => $officer->id,
                'collection_date' => $rejectedRun->collection_date,
                'branch_id' => $rejectedRun->branch_id,
                'status' => 'draft',
                'resubmission_of_id' => $rejectedRun->id,
            ]);

            foreach ($rejectedRun->items as $item) {
                $replacement->items()->create([
                    'member_id' => $item->member_id,
                    'recorded_by' => $item->recorded_by,
                    'loan_id' => $item->loan_id,
                    'account_id' => $item->account_id,
                    'amount' => $item->amount,
                    'note' => $item->note,
                    'visited' => $item->visited,
                    'outcome' => 'pending',
                ]);
            }
            $this->recalculateRun($replacement);

            app(AuditLogService::class)->log(
                'collections.replacement_created',
                "Replacement run {$replacement->run_number} created for rejected run {$rejectedRun->run_number}.",
                'collection_run',
                $replacement,
                $manager,
                ['rejected_run_id' => $rejectedRun->id, 'assigned_officer_id' => $officer->id]
            );

            return $replacement->fresh();
        });
    }

    public function markDraftRunsOrphaned(User $officer): int
    {
        if (! $officer->can('collections.create')) {
            return 0;
        }

        return DB::transaction(function () use ($officer) {
            $runs = CollectionRun::where('loan_officer_id', $officer->id)
                ->where('status', 'draft')
                ->whereNull('orphaned_at')
                ->lockForUpdate()
                ->get();

            foreach ($runs as $run) {
                $run->update(['orphaned_at' => now()]);
                app(AuditLogService::class)->log(
                    'collections.officer_deactivated',
                    "Draft run {$run->run_number} needs reassignment or voiding because its officer was deactivated.",
                    'collection_run',
                    $run,
                    $officer,
                    ['officer_deleted_at' => now()]
                );
            }

            if ($runs->isNotEmpty()) {
                $recipients = User::query()->where('account_status', 'active')->get()
                    ->filter(fn (User $user) => $user->id !== $officer->id && $user->can('collections.view-all'));

                foreach ($recipients as $recipient) {
                    Notification::create([
                        'user_id' => $recipient->id,
                        'type' => 'general',
                        'title' => 'Collection runs need attention',
                        'message' => $runs->count().' draft collection run(s) need reassignment or voiding after an officer was deactivated.',
                        'related_id' => $runs->first()->id,
                        'related_type' => CollectionRun::class,
                        'action_url' => '/dashboard/collections',
                        'priority' => 'high',
                    ]);
                }
            }

            return $runs->count();
        });
    }
}
