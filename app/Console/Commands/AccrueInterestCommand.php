<?php

namespace App\Console\Commands;

use App\Jobs\DisburseInterestJob;
use App\Services\InterestService;
use App\Support\FinancialSettings;
use Illuminate\Console\Command;

class AccrueInterestCommand extends Command
{
    protected $signature = 'interest:accrue
                            {--type= : Only handle this account type (savings|share-capital). Omitting handles both.}';

    protected $description = 'Credit any member interest that is due for the configured account types.';

    public function handle(InterestService $interestService): int
    {
        $types = FinancialSettings::ACCOUNT_TYPES;

        if ($this->option('type')) {
            $requested = (string) $this->option('type');
            if (! in_array($requested, FinancialSettings::ACCOUNT_TYPES, true)) {
                $this->error("Unknown account type [{$requested}]. Expected: savings or share-capital.");

                return self::INVALID;
            }
            $types = [$requested];
        }

        foreach ($types as $type) {
            if (! $interestService->isDue($type)) {
                $this->line("{$type}: not due, skipping.");

                continue;
            }

            try {
                DisburseInterestJob::dispatch($type, null, 'scheduled');
                $this->info("{$type}: interest run queued.");
            } catch (\Throwable $e) {
                $this->info("{$type}: failed to queue ({$e->getMessage()}).");
            }
        }

        return self::SUCCESS;
    }
}
