<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\XenditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyXenditPaymentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 45;

    public int $uniqueFor = 3600;

    public function __construct(public int $transactionId)
    {
        $this->onQueue('payments');
    }

    public function uniqueId(): string
    {
        return (string) $this->transactionId;
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(XenditService $xendit): void
    {
        // Reload eligibility: a callback, manual check or approver may have
        // handled this transaction since it was queued.
        $transaction = Transaction::awaitingXenditVerification()->find($this->transactionId);
        if (! $transaction) {
            return;
        }

        // Verification only. The existing service validates provider details;
        // successful payments still require the normal officer approval.
        $xendit->verifyAndCompletePayment($transaction);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Xendit payment verification exhausted its queue retries.', [
            'transaction_id' => $this->transactionId,
            'exception_class' => $exception ? $exception::class : null,
        ]);
    }
}
