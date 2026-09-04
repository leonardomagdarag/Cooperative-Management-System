<?php

namespace App\Services;

use App\Models\BackupRun;
use App\Models\SystemSetting;
use App\Models\User;
use Cron\CronExpression;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Cleanup\CleanupStrategy;
use Throwable;

class BackupService
{
    public const TIER_LOCAL = 'local';

    public const TIER_CLOUD = 'cloud';

    public const SCOPE_DB = 'db';

    public const SCOPE_FILES = 'files';

    public const SCOPE_BOTH = 'both';

    public const DISKS = [
        self::TIER_LOCAL => 'local_backups',
        self::TIER_CLOUD => 'google',
    ];

    public const VALID_SCOPES = [self::SCOPE_DB, self::SCOPE_FILES, self::SCOPE_BOTH];

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly GoogleDriveCredentialService $googleDriveCredentials,
    ) {
        //
    }

    public static function diskFor(string $tier): string
    {
        return self::DISKS[$tier];
    }

    public static function isValidTier(string $tier): bool
    {
        return isset(self::DISKS[$tier]);
    }

    public static function isValidScope(string $scope): bool
    {
        return in_array($scope, self::VALID_SCOPES, true);
    }

    public function isCloudConfigured(): bool
    {
        return $this->googleDriveCredentials->isConfigured();
    }

    /**
     * Run a backup for the given tier with the given scope.
     */
    public function run(string $tier, string $scope, Model|Authenticatable|null $actor = null): BackupRun
    {
        if (! self::isValidTier($tier)) {
            throw new InvalidArgumentException("Unknown backup tier [{$tier}].");
        }
        if (! self::isValidScope($scope)) {
            throw new InvalidArgumentException("Unknown backup scope [{$scope}].");
        }

        $this->applyRuntimeConfiguration($tier);

        $run = BackupRun::create([
            'tier' => $tier,
            'scope' => $scope,
            'status' => 'running',
            'started_at' => now(),
            'triggered_by' => $actor instanceof User ? $actor->id : null,
        ]);

        try {
            // Spatie's own mail notifications would block a run (or fail it) when the
            // SMTP channel is unavailable. Status is surfaced via audit logs + the UI.
            $params = ['--only-to-disk' => self::diskFor($tier), '--disable-notifications' => true, '--no-interaction' => true];

            if ($scope === self::SCOPE_DB) {
                $params['--only-db'] = true;
            } elseif ($scope === self::SCOPE_FILES) {
                $params['--only-files'] = true;
            }

            $exitCode = Artisan::call('backup:run', $params);

            if ($exitCode !== 0) {
                throw new RuntimeException('The backup command exited with a non-success code: '.trim(Artisan::output()));
            }

            $backup = $this->newestBackupOnDisk($tier);

            $run->update([
                'status' => 'success',
                'filename' => $backup['path'] ?? null,
                'size' => $backup['size'] ?? null,
                'finished_at' => now(),
            ]);

            $this->auditLogService->log(
                'backup.created',
                "Backup created on the {$tier} tier (scope: {$scope}).",
                'backup',
                null,
                $actor,
                ['tier' => $tier, 'scope' => $scope, 'file' => $backup['path'] ?? null],
            );

            // Enforce retention immediately after a successful run.
            $this->cleanup($tier, $actor);
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'message' => Str::limit($e->getMessage(), 2000),
                'finished_at' => now(),
            ]);

            $this->auditLogService->log(
                'backup.failed',
                "Backup failed on the {$tier} tier (scope: {$scope}).",
                'backup',
                null,
                $actor,
                ['tier' => $tier, 'scope' => $scope, 'error' => Str::limit($e->getMessage(), 2000)],
            );

            throw $e;
        }

        return $run;
    }

    /**
     * Run the configured automatic backup for a tier if it is due.
     * Returns null when the tier is disabled or not due yet.
     */
    public function runIfDue(string $tier, Model|Authenticatable|null $actor = null): ?BackupRun
    {
        if (! (bool) SystemSetting::get("backup_{$tier}_enabled", $tier === self::TIER_LOCAL)) {
            return null;
        }

        if (! $this->isDue($tier)) {
            return null;
        }

        $scope = (string) SystemSetting::get("backup_{$tier}_scope", self::SCOPE_BOTH);
        if (! self::isValidScope($scope)) {
            $scope = self::SCOPE_BOTH;
        }

        $run = $this->run($tier, $scope, $actor);

        SystemSetting::query()->updateOrCreate(
            ['key' => "backup_{$tier}_last_auto_run"],
            [
                'value' => now()->toDateTimeString(),
                'type' => 'string',
                'group' => 'backup',
                'label' => "Backup last auto run ({$tier})",
                'is_editable' => false,
            ],
        );

        return $run;
    }

    /**
     * Whether the tier's schedule says a run is due now.
     */
    public function isDue(string $tier): bool
    {
        $expression = (string) SystemSetting::get("backup_{$tier}_schedule", $tier === self::TIER_LOCAL ? '0 2 * * *' : '0 3 * * 1');

        try {
            $cron = CronExpression::factory($expression);
        } catch (Throwable) {
            return false;
        }

        $lastRun = SystemSetting::get("backup_{$tier}_last_auto_run");

        if (! $lastRun) {
            return true;
        }

        try {
            $nextRun = $cron->getNextRunDate($lastRun);
        } catch (Throwable) {
            return true;
        }

        return now()->gte($nextRun);
    }

    /**
     * List backups stored on the given tier's disk.
     */
    public function list(string $tier): array
    {
        if (! self::isValidTier($tier)) {
            throw new InvalidArgumentException("Unknown backup tier [{$tier}].");
        }

        if ($tier === self::TIER_CLOUD && ! $this->isCloudConfigured()) {
            return [];
        }

        try {
            $disk = Storage::disk(self::diskFor($tier));
            $base = (string) config('backup.backup.name');
            $files = collect($disk->allFiles($base))
                ->filter(fn (string $path) => str_ends_with($path, '.zip'))
                ->sort()
                ->values();
        } catch (Throwable) {
            return [];
        }

        return $files->map(function (string $path) use ($disk) {
            $size = null;
            $lastModified = null;

            try {
                $size = $disk->size($path);
                $lastModified = $disk->lastModified($path);
            } catch (Throwable) {
                // Metadata may be unavailable for remote drivers.
            }

            return [
                'path' => $path,
                'name' => basename($path),
                'size' => $size,
                'created_at' => $lastModified ? Carbon::createFromTimestamp($lastModified)->toISOString() : null,
            ];
        })->reverse()->values()->all();
    }

    public function newestBackupOnDisk(string $tier): ?array
    {
        return $this->list($tier)[0] ?? null;
    }

    /**
     * Delete a single backup archive from the given tier's disk.
     */
    public function delete(string $tier, string $path, Model|Authenticatable|null $actor = null): void
    {
        $disk = Storage::disk(self::diskFor($tier));
        $base = (string) config('backup.backup.name');

        abort_unless(Str::is("{$base}/*", $path), 422);

        if (! $disk->exists($path)) {
            throw new RuntimeException('Backup file no longer exists.');
        }

        $disk->delete($path);

        $this->auditLogService->log(
            'backup.deleted',
            "Backup archive deleted from the {$tier} tier.",
            'backup',
            null,
            $actor,
            ['tier' => $tier, 'file' => $path],
        );
    }

    /**
     * Stream a backup archive to the client.
     */
    public function download(string $tier, string $path)
    {
        $disk = Storage::disk(self::diskFor($tier));
        $base = (string) config('backup.backup.name');

        abort_unless(Str::is("{$base}/*", $path), 422);

        if (! $disk->exists($path)) {
            abort(404, 'Backup file not found.');
        }

        return $disk->download($path);
    }

    /**
     * Delete old backups on the tier's disk according to its retention policy.
     *
     * Retention = number of days to keep the newest backup. We intentionally
     * skip spatie's backup:clean abstraction here: it resolves its Config from
     * the container's scoped binding, which does not reliably honour our
     * per-tier overrides when invoked in-process. Direct deletion on the tier's
     * disk is deterministic and keeps old archives out of the cloud tier too.
     */
    public function cleanup(string $tier, Model|Authenticatable|null $actor = null): void
    {
        if ($tier === self::TIER_CLOUD && ! $this->isCloudConfigured()) {
            throw new RuntimeException('Google Drive is not configured. Save the credentials from Settings → Backup & Restore or set GOOGLE_DRIVE_* values in .env.');
        }

        $retentionDays = max(1, (int) SystemSetting::get("backup_{$tier}_retention_days", $tier === self::TIER_LOCAL ? 7 : 30));
        $cutoff = now()->subDays($retentionDays);

        $disk = Storage::disk(self::diskFor($tier));
        $base = (string) config('backup.backup.name');

        $deleted = 0;

        try {
            foreach ($disk->allFiles($base) as $path) {
                if (! str_ends_with($path, '.zip')) {
                    continue;
                }

                $lastModified = $disk->lastModified($path);

                if (! is_int($lastModified)) {
                    continue;
                }

                if (Carbon::createFromTimestamp($lastModified)->gte($cutoff)) {
                    continue;
                }

                $disk->delete($path);
                $deleted++;
            }
        } catch (Throwable $e) {
            $this->auditLogService->log(
                'backup.cleanup_failed',
                "Backup cleanup failed on the {$tier} tier.",
                'backup',
                null,
                $actor,
                ['tier' => $tier, 'error' => Str::limit($e->getMessage(), 2000)],
            );

            throw $e;
        }

        $this->auditLogService->log(
            'backup.cleaned',
            "Old backups cleaned up on the {$tier} tier.",
            'backup',
            null,
            $actor,
            ['tier' => $tier, 'deleted' => $deleted, 'retention_days' => $retentionDays],
        );
    }

    /**
     * Restore a backup archive (database, files or both).
     *
     * @param  string  $password  The archive encryption password (BACKUP_ARCHIVE_PASSWORD).
     */
    public function restore(string $tier, string $path, string $scope, string $password, Model|Authenticatable|null $actor = null): void
    {
        if (! self::isValidScope($scope)) {
            throw new InvalidArgumentException("Unknown restore scope [{$scope}].");
        }

        $configuredPassword = config('backup.backup.password');
        $encryptionEnabled = (bool) SystemSetting::get('backup_encryption_enabled', true);

        if ($encryptionEnabled && (! is_string($configuredPassword) || $configuredPassword === '' || ! hash_equals($configuredPassword, $password))) {
            throw new RuntimeException('The archive encryption password does not match BACKUP_ARCHIVE_PASSWORD.');
        }

        if (! $this->fileExists($tier, $path)) {
            throw new RuntimeException('Backup file not found.');
        }

        $this->applyRuntimeConfiguration($tier);

        $restoreSecret = Str::random(32);
        Artisan::call('down', ['--secret' => $restoreSecret]);

        $restoredFiles = false;

        try {
            if (in_array($scope, [self::SCOPE_DB, self::SCOPE_BOTH], true)) {
                $this->restoreDatabase($tier, $path, $password);
            }

            if (in_array($scope, [self::SCOPE_FILES, self::SCOPE_BOTH], true)) {
                $this->restoreFiles($tier, $path, $password);
                $restoredFiles = true;
            }

            $this->clearCaches();
        } catch (Throwable $e) {
            $this->auditLogService->log(
                'backup.restore_failed',
                "Restore failed from the {$tier} tier ({$path}).",
                'backup',
                null,
                $actor,
                ['tier' => $tier, 'file' => $path, 'scope' => $scope, 'error' => Str::limit($e->getMessage(), 2000)],
            );

            throw $e;
        } finally {
            Artisan::call('up');
        }

        $this->auditLogService->log(
            'backup.restored',
            "Database restored from a {$tier} tier backup (scope: {$scope}).",
            'backup',
            null,
            $actor,
            ['tier' => $tier, 'file' => $path, 'scope' => $scope, 'files_restored' => $restoredFiles],
        );
    }

    /**
     * Restore the database portion of an archive using the wnx restore command.
     */
    private function restoreDatabase(string $tier, string $path, string $password): void
    {
        $connection = config('backup.backup.source.databases')[0];

        $exitCode = Artisan::call('backup:restore', [
            '--disk' => self::diskFor($tier),
            '--backup' => $path,
            '--connection' => $connection,
            '--password' => $password,
            '--reset' => true,
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('Database restore failed: '.trim(Artisan::output()));
        }
    }

    /**
     * Restore the user-uploaded "public" files portion of an archive.
     */
    private function restoreFiles(string $tier, string $path, string $password): void
    {
        $exitCode = Artisan::call('backup:restore-files', [
            '--disk' => self::diskFor($tier),
            '--backup' => $path,
            '--password' => $password,
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('File restore failed: '.trim(Artisan::output()));
        }
    }

    private function fileExists(string $tier, string $path): bool
    {
        $base = (string) config('backup.backup.name');

        return Str::is("{$base}/*", $path) && Storage::disk(self::diskFor($tier))->exists($path);
    }

    /**
     * Publish backup settings + tier state for the Settings UI.
     */
    public function settingsPayload(): array
    {
        return [
            'encryption_enabled' => (bool) SystemSetting::get('backup_encryption_enabled', true),
            'mesh' => [
                'local' => [
                    'enabled' => (bool) SystemSetting::get('backup_local_enabled', true),
                    'schedule' => (string) SystemSetting::get('backup_local_schedule', '0 2 * * *'),
                    'scope' => (string) SystemSetting::get('backup_local_scope', self::SCOPE_BOTH),
                    'retention_days' => (int) SystemSetting::get('backup_local_retention_days', 7),
                    'last_auto_run' => SystemSetting::get('backup_local_last_auto_run'),
                ],
                'cloud' => [
                    'enabled' => (bool) SystemSetting::get('backup_cloud_enabled', false),
                    'schedule' => (string) SystemSetting::get('backup_cloud_schedule', '0 3 * * 1'),
                    'scope' => (string) SystemSetting::get('backup_cloud_scope', self::SCOPE_BOTH),
                    'retention_days' => (int) SystemSetting::get('backup_cloud_retention_days', 30),
                    'last_auto_run' => SystemSetting::get('backup_cloud_last_auto_run'),
                    'configured' => $this->isCloudConfigured(),
                ],
            ],
        ];
    }

    /**
     * Persist backup settings from the Settings UI.
     */
    public function updateSettings(array $validated): void
    {
        SystemSetting::set('backup_encryption_enabled', (bool) ($validated['encryption_enabled'] ?? true));

        foreach (self::DISKS as $tier => $disk) {
            $base = "backup_{$tier}";
            SystemSetting::set("{$base}_enabled", (bool) ($validated[$tier]['enabled'] ?? null));
            SystemSetting::set("{$base}_schedule", (string) ($validated[$tier]['schedule'] ?? null));
            SystemSetting::set("{$base}_scope", (string) ($validated[$tier]['scope'] ?? self::SCOPE_BOTH));
            SystemSetting::set("{$base}_retention_days", max(1, (int) ($validated[$tier]['retention_days'] ?? 7)));
        }
    }

    /**
     * Point the backup engine at the tier's disk and retention values for the
     * duration of this process (CLI schedules and API calls alike).
     */
    private function applyRuntimeConfiguration(string $tier): void
    {
        $disk = self::diskFor($tier);

        // Point the "destination" disk list at the tier's disk only so the spawned
        // backup:run command writes to the correct tier.
        config(['backup.backup.destination.disks' => [$disk]]);

        // Spatie resolves its Config as a scoped binding that can snapshot the
        // config before our override is applied. Force a fresh instance so the
        // spawned backup:run honours the current tier's disk.
        app()->forgetScopedInstances();
        $config = Config::fromArray(config('backup'));
        app()->instance(Config::class, $config);
        app()->bind(CleanupStrategy::class, $config->cleanup->strategy);

        $retentionDays = max(1, (int) SystemSetting::get("backup_{$tier}_retention_days", $tier === self::TIER_LOCAL ? 7 : 30));

        config([
            'backup.cleanup.default_strategy.keep_all_backups_for_days' => $retentionDays,
            'backup.cleanup.default_strategy.keep_daily_backups_for_days' => 0,
            'backup.cleanup.default_strategy.keep_weekly_backups_for_weeks' => 0,
            'backup.cleanup.default_strategy.keep_monthly_backups_for_months' => 0,
            'backup.cleanup.default_strategy.keep_yearly_backups_for_years' => 0,
            'backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than' => null,
        ]);

        if ((bool) SystemSetting::get('backup_encryption_enabled', true)) {
            $archivePassword = config('backup.backup.password');

            if (! is_string($archivePassword) || $archivePassword === '') {
                throw new RuntimeException('Backup encryption is enabled but BACKUP_ARCHIVE_PASSWORD is empty. Set it in .env (and keep it safe).');
            }

            config(['backup.backup.encryption' => 'default']);
        } else {
            config([
                'backup.backup.password' => null,
                'backup.backup.encryption' => 'none',
            ]);
        }
    }

    private function clearCaches(): void
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
        } catch (Throwable) {
            // Clearing caches is best-effort after a restore.
        }
    }
}
