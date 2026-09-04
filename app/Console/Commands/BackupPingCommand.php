<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupPingCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:ping
                            {--tier= : Only handle this tier (local|cloud). Omitting handles every enabled tier.}';

    /**
     * The console command description.
     */
    protected $description = 'Run any scheduled backups that are due. Used by cron and the /backup/ping fallback.';

    public function handle(BackupService $backupService): int
    {
        $tiers = ['local', 'cloud'];

        if ($this->option('tier')) {
            $tiers = [$this->option('tier')];
        }

        $handled = [];

        foreach ($tiers as $tier) {
            if (! BackupService::isValidTier($tier)) {
                $this->error("Unknown backup tier [{$tier}]. Expected: local or cloud.");

                return self::INVALID;
            }

            if (! (bool) SystemSetting::get("backup_{$tier}_enabled", $tier === 'local')) {
                $this->line("{$tier}: disabled, skipping.");

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

        return $handled === [] ? self::SUCCESS : self::SUCCESS;
    }
}
