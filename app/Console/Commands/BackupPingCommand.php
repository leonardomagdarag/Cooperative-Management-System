<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupPingCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:ping
                            {--tier= : Only handle this tier (hourly|daily|offsite). Omitting handles every enabled tier.}';

    /**
     * The console command description.
     */
    protected $description = 'Run any scheduled backups that are due. Used by cron and the /backup/ping fallback.';

    public function handle(BackupService $backupService): int
    {
        $tiers = BackupService::VALID_TIERS;

        if ($this->option('tier')) {
            $tiers = [$this->option('tier')];
        }

        $handled = [];

        foreach ($tiers as $tier) {
            if (! BackupService::isValidTier($tier)) {
                $this->error("Unknown backup tier [{$tier}]. Expected: hourly, daily or offsite.");

                return self::INVALID;
            }

            if (! $backupService->isEnabled($tier)) {
                $handled[] = "{$tier}: disabled, skipping.";

                continue;
            }

            if (! $backupService->isDue($tier)) {
                $this->line("{$tier}: not due yet, skipping.");

                continue;
            }

            try {
                $run = $backupService->runIfDue($tier);
                $status = $run?->status ?? 'skipped';
                $handled[] = "{$tier}: {$status}";
            } catch (\Throwable $e) {
                $handled[] = "{$tier}: failed ({$e->getMessage()})";
            }
        }

        foreach ($handled as $line) {
            $this->info($line);
        }

        return self::SUCCESS;
    }
}
