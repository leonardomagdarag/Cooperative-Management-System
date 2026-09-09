<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupCleanTierCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:clean-tier
                            {tier : The backup tier to clean (local|cloud)}
                            {--user= : User ID that triggered the cleanup (for audit attribution).}';

    /**
     * The console command description.
     */
    protected $description = 'Delete old backups on a single tier according to its retention policy';

    public function handle(BackupService $backupService): int
    {
        $tier = $this->argument('tier');

        if (! BackupService::isValidTier($tier)) {
            $this->error("Unknown backup tier [{$tier}]. Expected: local or cloud.");

            return self::INVALID;
        }

        $actor = null;
        if ($this->option('user')) {
            $actor = User::find((int) $this->option('user'));
        }

        $this->info("Cleaning up old backups on the {$tier} tier…");

        try {
            $backupService->cleanup($tier, $actor);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Cleanup finished.');

        return self::SUCCESS;
    }
}
