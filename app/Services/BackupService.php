<?php

namespace App\Services;

use App\Jobs\RestoreBackupJob;
use App\Jobs\RunBackupJob;
use App\Models\BackupRun;
use App\Models\RestoreRun;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\MaintenanceState;
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
    public const TIER_HOURLY = 'hourly';

    public const TIER_DAILY = 'daily';

    public const TIER_OFFSITE = 'offsite';

    public const SCOPE_DB = 'db';

    public const SCOPE_FILES = 'files';

    public const SCOPE_BOTH = 'both';

    /**
     * Where each backup job stores its archives.
     *
     * Both hourly and daily live on the shared on-server disk; the off-site
     * job writes to the Backblaze B2 S3-compatible disk. Because hourly and
     * daily share a disk, each tier writes with its own filename prefix so
     * listing and cleanup always know which job an archive belongs to.
     */
    public const DISKS = [
        self::TIER_HOURLY => 'local_backups',
        self::TIER_DAILY => 'local_backups',
        self::TIER_OFFSITE => 'b2',
    ];

    /**
     * Fixed schedules. Daily jobs are anchored at 03:00; hourly runs on the
     * hour. These are deliberate system defaults — no longer user-editable.
     */
    public const SCHEDULES = [
        self::TIER_HOURLY => '0 * * * *',
        self::TIER_DAILY => '0 3 * * *',
        self::TIER_OFFSITE => '0 3 * * *',
    ];

    /**
     * Per-tier archive filename prefix. Keeps archives distinguishable on
     * shared disks and lets each tier's retention act only on its own files.
     */
    public const FILENAME_PREFIXES = [
        self::TIER_HOURLY => 'backup-hourly-',
        self::TIER_DAILY => 'backup-daily-',
        self::TIER_OFFSITE => 'backup-offsite-',
    ];

    /**
     * How many of the newest hourly archives to retain at any time.
     */
    public const HOURLY_KEEP = 3;

    /**
     * How many days of archives to retain for the daily and off-site jobs.
     */
    public const DAILY_RETENTION_DAYS = 30;

    public const OFFSITE_RETENTION_DAYS = 7;

    public const VALID_SCOPES = [self::SCOPE_DB, self::SCOPE_FILES, self::SCOPE_BOTH];

    public const VALID_TIERS = [self::TIER_HOURLY, self::TIER_DAILY, self::TIER_OFFSITE];

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
        //
    }

    public static function diskFor(string $tier): string
    {
        return self::DISKS[$tier];
    }

    public static function isValidTier(string $tier): bool
    {
        return in_array($tier, self::VALID_TIERS, true);
    }

    public static function isValidScope(string $scope): bool
    {
        return in_array($scope, self::VALID_SCOPES, true);
    }

    public static function prefixFor(string $tier): string
    {
        return self::FILENAME_PREFIXES[$tier];
    }

    /** Both spellings are readers during the archive compatibility window. */
    public static function prefixesFor(string $tier): array
    {
        return $tier === self::TIER_OFFSITE
            ? [self::prefixFor($tier), 'backup-offsight-']
            : [self::prefixFor($tier)];
    }

    public static function scheduleFor(string $tier): string
    {
        return self::SCHEDULES[$tier];
    }

    /**
     * Whether the Backblaze B2 off-site tier has its credentials/endpoint
     * configured (from .env, via the filesystems "b2" disk).
     */
    public function isOffsiteConfigured(): bool
    {
        $disk = config('filesystems.disks.b2');

        return (bool) ($disk['key'] ?? null)
            && (bool) ($disk['secret'] ?? null)
            && (bool) ($disk['bucket'] ?? null)
            && (bool) ($disk['endpoint'] ?? null);
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

        $run = BackupRun::create([
            'tier' => $tier,
            'scope' => $scope,
            'status' => 'running',
            'started_at' => now(),
            'triggered_by' => $actor instanceof User ? $actor->id : null,
        ]);

        $this->executeRun($run, $actor);

        return $run->fresh();
    }

    /** Queue a run after persisting its observable state. */
    public function queueRun(string $tier, string $scope, ?User $actor = null): BackupRun
    {
        if (! self::isValidTier($tier) || ! self::isValidScope($scope)) {
            throw new InvalidArgumentException('Invalid backup tier or scope.');
        }
        if (BackupRun::query()->where('tier', $tier)->whereIn('status', ['queued', 'running'])->exists()) {
            throw new RuntimeException("A {$tier} backup is already queued or running.");
        }
        if (RestoreRun::query()->whereIn('status', ['queued', 'running'])->exists()) {
            throw new RuntimeException('A restore operation is already queued or running.');
        }
        $run = BackupRun::create(['tier' => $tier, 'scope' => $scope, 'status' => 'queued', 'triggered_by' => $actor?->id]);
        RunBackupJob::dispatch($run->id);

        return $run;
    }

    public function queueRestore(string $tier, string $path, string $scope, string $password, User $actor): RestoreRun
    {
        $this->validateRestoreRequest($tier, $path, $scope, $password);
        if (BackupRun::query()->whereIn('status', ['queued', 'running'])->exists() || RestoreRun::query()->whereIn('status', ['queued', 'running'])->exists()) {
            throw new RuntimeException('A backup or restore operation is already queued or running.');
        }
        $run = RestoreRun::create(['tier' => $tier, 'path' => $path, 'scope' => $scope, 'status' => 'queued', 'triggered_by' => $actor->id]);
        RestoreBackupJob::dispatch($run->id, $password);

        return $run;
    }

    /** Execute the already-created run. Kept separate so HTTP only enqueues. */
    public function executeRun(BackupRun $run, Model|Authenticatable|null $actor = null): void
    {
        $tier = $run->tier;
        $scope = $run->scope;
        $this->applyRuntimeConfiguration($tier, (string) $run->id);
        try {
            // Spatie's own mail notifications would block a run (or fail it) when the
            // SMTP channel is unavailable. Status is surfaced via audit logs + the UI.
            //
            // --config=backup forces the spawned command to rebuild its Config from
            // the (per-tier narrowed) backup config repository instead of trusting the
            // container's scoped binding, which can snapshot a stale full-disk list.
            if ($tier === self::TIER_OFFSITE) {
                $backup = $this->replicateDailyArchive();
            } else {
                $params = ['--only-to-disk' => self::diskFor($tier), '--disable-notifications' => true, '--no-interaction' => true, '--config' => 'backup'];

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
            }

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
    }

    /**
     * Run a tier's automatic backup if it is due.
     * Returns null when not due yet.
     */
    public function runIfDue(string $tier, Model|Authenticatable|null $actor = null): ?BackupRun
    {
        if (! self::isValidTier($tier)) {
            throw new InvalidArgumentException("Unknown backup tier [{$tier}].");
        }

        if (! $this->isEnabled($tier) || ! $this->isDue($tier)) {
            return null;
        }

        $run = $this->queueRun($tier, self::SCOPE_BOTH, $actor instanceof User ? $actor : null);

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
     * Whether the tier's fixed schedule says a run is due now.
     */
    public function isDue(string $tier): bool
    {
        if (! self::isValidTier($tier)) {
            return false;
        }

        $expression = self::scheduleFor($tier);

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

    public function isEnabled(string $tier): bool
    {
        return $tier !== self::TIER_HOURLY || (bool) SystemSetting::get('backup_hourly_enabled', false);
    }

    /**
     * List backups stored on the given tier's disk (its own prefix only).
     */
    public function list(string $tier): array
    {
        if (! self::isValidTier($tier)) {
            throw new InvalidArgumentException("Unknown backup tier [{$tier}].");
        }

        if ($tier === self::TIER_OFFSITE && ! $this->isOffsiteConfigured()) {
            return [];
        }

        try {
            $disk = Storage::disk(self::diskFor($tier));
            $base = (string) config('backup.backup.name');
            $prefix = self::prefixFor($tier);
            $files = collect($disk->allFiles($base))
                ->filter(fn (string $path) => Str::startsWith(basename($path), self::prefixesFor($tier)) && str_ends_with($path, '.zip'))
                ->sort()
                ->values();
        } catch (Throwable) {
            return [];
        }

        return $files->map(function (string $path) use ($disk) {
            $size = null;
            $lastModified = null;

            try {
                // Some drivers (e.g. a local disk with 'throw' => false) return
                // false instead of throwing when metadata is unavailable. Keep
                // only genuine numeric sizes so the API never emits a boolean.
                $rawSize = $disk->size($path);
                $size = is_numeric($rawSize) ? (int) $rawSize : null;
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
     * Delete old backups on the tier's disk according to its fixed policy.
     *
     * - hourly:  keep the 3 newest archives, delete the rest.
     * - daily / off-site: keep archives newer than RETENTION_DAYS, delete older.
     *
     * We intentionally skip spatie's backup:clean abstraction here: it resolves
     * its Config from the container's scoped binding, which does not reliably
     * honour our per-tier overrides when invoked in-process. Direct deletion on
     * the tier's disk is deterministic and keeps old archives out of the
     * shared on-server disk too.
     */
    public function cleanup(string $tier, Model|Authenticatable|null $actor = null): void
    {
        if ($tier === self::TIER_OFFSITE && ! $this->isOffsiteConfigured()) {
            throw new RuntimeException('Backblaze B2 is not configured. Set the AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET and AWS_ENDPOINT values in .env.');
        }

        $disk = Storage::disk(self::diskFor($tier));
        $base = (string) config('backup.backup.name');

        $deleted = 0;

        try {
            $archives = collect($disk->allFiles($base))
                ->filter(fn (string $path) => Str::startsWith(basename($path), self::prefixesFor($tier)) && str_ends_with($path, '.zip'))
                ->values();

            if ($tier === self::TIER_HOURLY) {
                // Keep the newest HOURLY_KEEP archives; oldest filenames sort first.
                $toDelete = $archives->sort()->take(max(0, $archives->count() - self::HOURLY_KEEP));
            } else {
                $retentionDays = $tier === self::TIER_DAILY ? self::DAILY_RETENTION_DAYS : self::OFFSITE_RETENTION_DAYS;
                $cutoff = now()->subDays($retentionDays);
                $toDelete = $archives->filter(function (string $path) use ($disk, $cutoff) {
                    $lastModified = $disk->lastModified($path);

                    return is_int($lastModified) && Carbon::createFromTimestamp($lastModified)->lt($cutoff);
                });
            }

            foreach ($toDelete as $path) {
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
            ['tier' => $tier, 'deleted' => $deleted],
        );
    }

    /**
     * Restore a backup archive (database, files or both).
     *
     * @param  string  $password  The archive encryption password (BACKUP_ARCHIVE_PASSWORD).
     */
    public function restore(string $tier, string $path, string $scope, string $password, Model|Authenticatable|null $actor = null): void
    {
        $this->validateRestoreRequest($tier, $path, $scope, $password);
        $this->applyRuntimeConfiguration($tier);

        $restoreSecret = Str::random(32);
        $restoredFiles = false;
        $wentDown = false;

        // The notice is recorded before going down and cleared after coming back
        // up, so the maintenance page can explain itself. A crash in between
        // leaves the file behind; MaintenanceState treats an entry older than its
        // staleness window as debris and falls back to generic copy.
        MaintenanceState::enter(MaintenanceState::REASON_RESTORE);

        try {
            Artisan::call('down', ['--secret' => $restoreSecret]);
            $wentDown = true;

            if (in_array($scope, [self::SCOPE_DB, self::SCOPE_BOTH], true)) {
                $this->restoreDatabase($tier, $path, $password);
            }

            if (in_array($scope, [self::SCOPE_FILES, self::SCOPE_BOTH], true)) {
                $this->restoreFiles($tier, $path, $password);
                $restoredFiles = true;
            }

            $this->clearCaches();
        } catch (Throwable $e) {
            $this->auditLogService->log('backup.restore_failed', "Restore failed from the {$tier} tier ({$path}).", 'backup', null, $actor, ['tier' => $tier, 'file' => $path, 'scope' => $scope, 'error' => Str::limit($e->getMessage(), 2000)]);
            throw $e;
        } finally {
            // Nested finally: a failing `up` must not strand the notice, or the
            // site comes back up still claiming to be restoring. Being left down
            // is the worse failure and is allowed to surface.
            try {
                if ($wentDown) {
                    Artisan::call('up');
                }
            } finally {
                MaintenanceState::leave();
            }
        }

        $this->auditLogService->log('backup.restored', "Database restored from a {$tier} tier backup (scope: {$scope}).", 'backup', null, $actor, ['tier' => $tier, 'file' => $path, 'scope' => $scope, 'files_restored' => $restoredFiles]);
    }

    private function validateRestoreRequest(string $tier, string $path, string $scope, string $password): void
    {
        if (! self::isValidScope($scope)) {
            throw new InvalidArgumentException("Unknown restore scope [{$scope}].");
        }

        $configuredPassword = config('backup.backup.password');
        if (! is_string($configuredPassword) || $configuredPassword === '' || ! hash_equals($configuredPassword, $password)) {
            throw new RuntimeException('The archive encryption password does not match BACKUP_ARCHIVE_PASSWORD.');
        }

        if (! $this->fileExists($tier, $path)) {
            throw new RuntimeException('Backup file not found.');
        }
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
     * Current connectivity + config status for the off-site (B2) tier.
     */
    public function offsiteStatus(): array
    {
        $configured = $this->isOffsiteConfigured();

        if (! $configured) {
            return [
                'configured' => false,
                'reachable' => null,
                'checked_at' => null,
            ];
        }

        $reachable = false;
        $checkedAt = now();

        try {
            $base = (string) config('backup.backup.name');

            // A light probe: listing the archive folder fails fast when the
            // bucket/endpoint/credentials are invalid or unreachable. The disk
            // must NOT be forgotten first — forgetDisk drops the custom factory
            // (e.g. a test fake) and forces a real remote re-resolve.
            Storage::disk(self::diskFor(self::TIER_OFFSITE))->allFiles($base);
            $reachable = true;
        } catch (Throwable) {
            // Unreachable — status surfaces in the UI as "not reachable".
        }

        return [
            'configured' => true,
            'reachable' => $reachable,
            'checked_at' => $checkedAt->toISOString(),
        ];
    }

    /**
     * Publish backup settings + tier state for the Settings UI.
     */
    public function settingsPayload(): array
    {
        $tiers = [];

        foreach (self::VALID_TIERS as $tier) {
            $tiers[$tier] = [
                'disk' => self::diskFor($tier),
                'schedule' => self::scheduleFor($tier),
                'scope' => self::SCOPE_BOTH,
                'last_auto_run' => SystemSetting::get("backup_{$tier}_last_auto_run"),
            ];

            if ($tier === self::TIER_HOURLY) {
                $tiers[$tier]['retention'] = ['keep' => self::HOURLY_KEEP];
                $tiers[$tier]['enabled'] = $this->isEnabled($tier);
            } else {
                $tiers[$tier]['retention'] = ['days' => $tier === self::TIER_DAILY ? self::DAILY_RETENTION_DAYS : self::OFFSITE_RETENTION_DAYS];
            }
        }

        return [
            'tiers' => $tiers,
            'offsite' => $this->offsiteStatus(),
        ];
    }

    /**
     * Persist the encryption toggle from the Settings UI.
     */
    public function updateHourlyEnabled(bool $enabled): void
    {
        SystemSetting::set('backup_hourly_enabled', $enabled);
    }

    /**
     * Point the backup engine at the tier's disk, filename prefix and retention
     * for the duration of this process (CLI schedules and API calls alike).
     */
    private function applyRuntimeConfiguration(string $tier, ?string $runId = null): void
    {
        $disk = self::diskFor($tier);

        // Point the "destination" disk list at the tier's disk only so the
        // spawned backup:run command writes to the correct tier, using the
        // tier's own prefix so shared disks stay distinguishable.
        config(['backup.backup.destination.disks' => [$disk]]);
        config(['backup.backup.destination.filename_prefix' => self::prefixFor($tier)]);
        // Operations never share a working directory, including after a worker restart.
        if ($runId !== null) {
            config(['backup.backup.temporary_directory' => storage_path('app/backup-temp/'.Str::uuid().'-'.$runId)]);
        }

        // Spatie resolves its Config as a scoped binding that can snapshot the
        // config before our override is applied. Force a fresh instance so the
        // spawned backup:run honours the current tier's disk.
        app()->forgetScopedInstances();
        $config = Config::fromArray(config('backup'));
        app()->instance(Config::class, $config);
        app()->bind(CleanupStrategy::class, $config->cleanup->strategy);

        // Our own cleanup() enforces the fixed per-tier retention after each
        // successful run; spatie's strategy is disabled to avoid it getting a
        // foot in the door.
        config([
            'backup.cleanup.default_strategy.keep_all_backups_for_days' => 3650,
            'backup.cleanup.default_strategy.keep_daily_backups_for_days' => 0,
            'backup.cleanup.default_strategy.keep_weekly_backups_for_weeks' => 0,
            'backup.cleanup.default_strategy.keep_monthly_backups_for_months' => 0,
            'backup.cleanup.default_strategy.keep_yearly_backups_for_years' => 0,
            'backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than' => null,
        ]);

        $archivePassword = config('backup.backup.password');
        if (! is_string($archivePassword) || $archivePassword === '') {
            throw new RuntimeException('Backup encryption is required but BACKUP_ARCHIVE_PASSWORD is empty. Set it in .env (and keep it safe).');
        }
        config(['backup.backup.encryption' => 'default']);
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

    /** Copy the verified local daily archive; B2 is a replica, never a second dump. */
    private function replicateDailyArchive(): array
    {
        if (! $this->isOffsiteConfigured()) {
            throw new RuntimeException('Backblaze B2 is not configured. Set the AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET and AWS_ENDPOINT values in .env.');
        }
        $daily = $this->newestBackupOnDisk(self::TIER_DAILY);
        if (! $daily) {
            throw new RuntimeException('No verified daily archive is available to replicate off-site.');
        }
        $source = Storage::disk(self::diskFor(self::TIER_DAILY));
        $destination = Storage::disk(self::diskFor(self::TIER_OFFSITE));
        $path = dirname($daily['path']).'/'.Str::replaceFirst('backup-daily-', self::prefixFor(self::TIER_OFFSITE), basename($daily['path']));
        $stream = $source->readStream($daily['path']);
        if ($stream === false || ! $destination->writeStream($path, $stream)) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new RuntimeException('Could not upload the verified daily archive to Backblaze B2.');
        }
        fclose($stream);

        return ['path' => $path, 'size' => $daily['size']];
    }

    /** Translate known native failures into an actionable, safe operator message. */
    public function operatorMessage(Throwable $e): string
    {
        $message = $e->getMessage();
        if (str_contains($message, '10106') || str_contains(strtolower($message), 'winsock')) {
            return 'mysqldump could not initialize a Windows TCP socket (10106). Restart the database stack; if it persists, repair or restart Windows networking before retrying.';
        }
        if (str_contains(strtolower($message), 'mysqldump') && str_contains(strtolower($message), 'not found')) {
            return 'mysqldump is unavailable. Verify DB_DUMP_BINARY_PATH points to its directory and the worker can read it.';
        }

        return Str::limit($message, 2000);
    }
}
