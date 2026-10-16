<?php

namespace Database\Seeders;

use App\Models\Account;
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

        $transactions = Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->where('status', 'completed')
            ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_payment'])
            ->latest('transaction_date')
            ->limit(8)
            ->get();

        $created = 0;

        foreach ($transactions as $index => $transaction) {
            $memberId = Account::query()->whereKey($transaction->account_id)->value('user_id');

            if (! $memberId) {
                continue;
            }

            $receipt = TransactionReceipt::firstOrCreate(
                ['transaction_id' => $transaction->id, 'reason' => 'original'],
                [
                    'issued_by' => $accountant->id,
                    'issued_to_member_id' => $memberId,
                    'certified' => true,
                ],
            );

            if ($receipt->wasRecentlyCreated) {
                $created++;
            }

            // One member kept a reprint after losing the original.
            if ($index === 0) {
                $reprint = TransactionReceipt::firstOrCreate(
                    ['transaction_id' => $transaction->id, 'reason' => 'reprint'],
                    [
                        'issued_by' => $accountant->id,
                        'issued_to_member_id' => $memberId,
                        'certified' => false,
                        'note' => 'Reprint issued after the member misplaced the original receipt.',
                    ],
                );

                if ($reprint->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        $this->command?->info('Issued '.$created.' transaction receipts.');
    }
}
