<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupRunTierCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:run-tier
                            {tier : The backup tier to run (local|cloud)}
                            {--scope= : Override the scope (db|files|both). Defaults to the tier setting.}
                            {--user= : User ID that triggered the run (for audit attribution).}';

    /**
     * The console command description.
     */
    protected $description = 'Run a backup for a single tier (local or cloud) with the configured scope';

    public function handle(BackupService $backupService): int
    {
        $tier = $this->argument('tier');

        if (! BackupService::isValidTier($tier)) {
            $this->error("Unknown backup tier [{$tier}]. Expected: local or cloud.");

            return self::INVALID;
        }

        $scope = $this->option('scope') ?? (string) SystemSetting::get("backup_{$tier}_scope", BackupService::SCOPE_BOTH);

        if (! BackupService::isValidScope($scope)) {
            $this->error("Unknown backup scope [{$scope}]. Expected: db, files or both.");

            return self::INVALID;
        }

        $actor = null;
        if ($this->option('user')) {
            $actor = User::find((int) $this->option('user'));
        }

        $this->info("Running {$tier} backup (scope: {$scope})…");

        try {
            $run = $backupService->run($tier, $scope, $actor);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup finished: {$run->status}.");

        return $run->status === 'success' ? self::SUCCESS : self::FAILURE;
    }
}
