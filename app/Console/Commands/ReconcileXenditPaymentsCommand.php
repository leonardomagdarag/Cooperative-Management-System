<?php

namespace App\Console\Commands;

use App\Jobs\VerifyXenditPaymentJob;
use App\Models\Transaction;
use Illuminate\Console\Command;

class ReconcileXenditPaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile-xendit';

    protected $description = 'Queue verification of pending, unverified Xendit deposits and loan payments';

    public function handle(): int
    {
        // Do not gate on checkout availability: money already sent must still
        // be reconciled when new online payments are disabled.
        Transaction::awaitingXenditVerification()->select('id')->chunkById(100, function ($transactions) {
            foreach ($transactions as $transaction) {
                VerifyXenditPaymentJob::dispatch($transaction->id);
            }
        });

        $this->info('Pending Xendit payments submitted for queued verification.');

        return self::SUCCESS;
    }
}
