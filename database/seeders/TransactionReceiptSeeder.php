<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\TransactionReceipt;
use App\Models\User;
use Database\Seeders\Support\MemberRoster;
use Illuminate\Database\Seeder;

/**
 * Counters give a printed receipt for every over-the-counter transaction.
 *
 * The most recent member transactions get an original receipt, and one of them
 * also has a reprint on file, which is what happens when a member loses the
 * first copy. Transactions already carrying a receipt (for example the ones a
 * collection run posted) are skipped, so nothing is issued twice.
 */
class TransactionReceiptSeeder extends Seeder
{
    public function run(): void
    {
        $accountant = User::query()->where('user_number', 'USR000004')->first();

        if (! $accountant) {
            return;
        }

        $memberIds = User::query()
            ->whereIn('user_number', collect(MemberRoster::all())->pluck('user_number'))
            ->pluck('id');

        $accountIds = Account::query()->whereIn('user_id', $memberIds)->pluck('id');
        $loanIds = Loan::query()->whereIn('user_id', $memberIds)->pluck('id');

        $transactions = Transaction::query()
            ->where(function ($query) use ($accountIds, $loanIds) {
                $query->whereIn('account_id', $accountIds)
                    ->orWhereIn('loan_id', $loanIds);
            })
            ->where('status', 'completed')
            ->whereNull('reversal_of_id')
            ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_payment', 'loan_disbursement'])
            ->latest('transaction_date')
            ->limit(28)
            ->get();

        $created = 0;

        foreach ($transactions as $index => $transaction) {
            $memberId = $transaction->account_id
                ? Account::query()->whereKey($transaction->account_id)->value('user_id')
                : Loan::query()->whereKey($transaction->loan_id)->value('user_id');

            if (! $memberId) {
                continue;
            }

            $issuedAt = $transaction->transaction_date ?? $transaction->created_at ?? now();

            $receipt = TransactionReceipt::withoutTimestamps(function () use ($transaction, $accountant, $memberId, $issuedAt) {
                $existing = TransactionReceipt::query()
                    ->where('transaction_id', $transaction->id)
                    ->where('reason', 'original')
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $model = new TransactionReceipt([
                    'transaction_id' => $transaction->id,
                    'reason' => 'original',
                    'issued_by' => $accountant->id,
                    'issued_to_member_id' => $memberId,
                    'certified' => true,
                ]);
                $model->created_at = $issuedAt;
                $model->updated_at = $issuedAt;
                $model->save();

                return $model;
            });

            if ($receipt->wasRecentlyCreated) {
                $created++;
            }

            // One member kept a reprint after losing the original.
            if ($index === 0) {
                $reprintAt = $issuedAt->copy()->addDays(2);
                $reprint = TransactionReceipt::withoutTimestamps(function () use ($transaction, $accountant, $memberId, $reprintAt) {
                    $existing = TransactionReceipt::query()
                        ->where('transaction_id', $transaction->id)
                        ->where('reason', 'reprint')
                        ->first();

                    if ($existing) {
                        return $existing;
                    }

                    $model = new TransactionReceipt([
                        'transaction_id' => $transaction->id,
                        'reason' => 'reprint',
                        'issued_by' => $accountant->id,
                        'issued_to_member_id' => $memberId,
                        'certified' => false,
                        'note' => 'Reprint issued after the member misplaced the original receipt.',
                    ]);
                    $model->created_at = $reprintAt;
                    $model->updated_at = $reprintAt;
                    $model->save();

                    return $model;
                });

                if ($reprint->wasRecentlyCreated) {
                    $created++;
                }
            }

            // Another member requested a certified replacement receipt for their records.
            if ($index === 3) {
                $reissueAt = $issuedAt->copy()->addDays(5);
                $reissue = TransactionReceipt::withoutTimestamps(function () use ($transaction, $accountant, $memberId, $reissueAt) {
                    $existing = TransactionReceipt::query()
                        ->where('transaction_id', $transaction->id)
                        ->where('reason', 'reissue')
                        ->first();

                    if ($existing) {
                        return $existing;
                    }

                    $model = new TransactionReceipt([
                        'transaction_id' => $transaction->id,
                        'reason' => 'reissue',
                        'issued_by' => $accountant->id,
                        'issued_to_member_id' => $memberId,
                        'certified' => true,
                        'note' => 'Certified replacement issued for barangay livelihood grant liquidation.',
                    ]);
                    $model->created_at = $reissueAt;
                    $model->updated_at = $reissueAt;
                    $model->save();

                    return $model;
                });

                if ($reissue->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        $this->command?->info('Issued '.$created.' transaction receipts.');
    }
}
