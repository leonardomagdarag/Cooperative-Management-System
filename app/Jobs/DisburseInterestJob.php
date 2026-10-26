<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\InterestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Credits one account type's interest batch.
 *
 * Runs on the `default` queue so the shared-hosting scheduler that drains
 * `default,emails,backups` picks it up. Idempotent per (type, period) via the
 * batch key, so a retry after a transient failure cannot double-credit.
 */
class DisburseInterestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        public string $accountType,
        public ?int $actorId = null,
        public string $trigger = 'scheduled',
        public ?int $approverId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(InterestService $interestService): void
    {
        $interestService->disburse(
            $this->accountType,
            $this->actorId ? User::find($this->actorId) : null,
            $this->trigger,
            $this->approverId ? User::find($this->approverId) : null,
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error("Interest disbursement job failed for {$this->accountType}: ".($exception?->getMessage() ?? 'Unknown error'));
    }
}
