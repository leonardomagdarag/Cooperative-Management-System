<?php

namespace App\Services;

use App\Jobs\RestoreBackupJob;
use App\Jobs\RunBackupJob;
use App\Models\BackupRun;
use App\Models\RestoreRun;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Backup\GfsRetentionService;
use App\Support\MaintenanceState;
use Cron\CronExpression;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Cleanup\CleanupStrategy;
use Throwable;

class BackupService
{
    public const TIER_DAILY = 'daily';

    public const TIER_OFFSITE = 'offsite';

    public const SCOPE_DB = 'db';

    public const SCOPE_FILES = 'files';

    public const SCOPE_BOTH = 'both';

    /** Grace period after approval so the approver receives the response before downtime. */
    public const RESTORE_START_DELAY_SECONDS = 15;

    /** Minimum time the site stays in maintenance mode during a completed restore or rollback. */
    public const RESTORE_MINIMUM_DOWNTIME_SECONDS = 30;

    /**
     * Where each backup job stores its archives.
     *
     * Daily archives live on the shared on-server disk; the off-site job
     * writes to the Backblaze B2 S3-compatible disk.
     */
    public const DISKS = [
        self::TIER_DAILY => 'local_backups',
        self::TIER_OFFSITE => 'b2',
    ];

    /**
     * The daily schedule is read from the system settings table. The default
     * keeps the existing 03:00 schedule for installations that have not yet
     * saved a preference.
     */
    public const DEFAULT_DAILY_TIME = '03:00';

    /**
     * Per-tier archive filename prefix. Keeps archives distinguishable on
     * shared disks and lets each tier's retention act only on its own files.
     */
    public const FILENAME_PREFIXES = [
        self::TIER_DAILY => 'backup-daily-',
        self::TIER_OFFSITE => 'backup-offsite-',
    ];

    /**
     * How many days of archives to retain for the daily and off-site jobs under GFS.
     */
    public const DAILY_RETENTION_DAYS = 14;

    public const OFFSITE_RETENTION_DAYS = 14;

    public const VALID_SCOPES = [self::SCOPE_DB, self::SCOPE_FILES, self::SCOPE_BOTH];

    public const VALID_TIERS = [self::TIER_DAILY, self::TIER_OFFSITE];

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly BackupNotificationService $backupNotificationService,
    ) {
        //
    }

    public function gfsRetentionService(): GfsRetentionService
    {
        return new GfsRetentionService(
            dailyDays: (int) config('backup.gfs.daily_days', self::DAILY_RETENTION_DAYS),
            weeklyWeeks: (int) config('backup.gfs.weekly_weeks', 5),
            monthlyMonths: (int) config('backup.gfs.monthly_months', 12),
        );
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
        if (! in_array($tier, self::VALID_TIERS, true)) {
            throw new InvalidArgumentException("Unknown backup tier [{$tier}].");
        }

        $time = SystemSetting::get('backup_daily_time', self::DEFAULT_DAILY_TIME);
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return "{$minute} {$hour} * * *";
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

    public function queueRestore(string $tier, string $path, string $scope, User $actor): RestoreRun
    {
        $password = $this->archivePassword();
        $this->validateRestoreRequest($tier, $path, $scope, $password);
        if (BackupRun::query()->whereIn('status', ['queued', 'running'])->exists() || RestoreRun::query()->whereIn('status', ['queued', 'running'])->exists()) {
            throw new RuntimeException('A backup or restore operation is already queued or running.');
        }
        $run = RestoreRun::create(['tier' => $tier, 'path' => $path, 'scope' => $scope, 'status' => 'queued', 'triggered_by' => $actor->id]);
        // Restores must never run inline with the chairman's approval request.
        // The database connection also honors the delay if QUEUE_CONNECTION is
        // accidentally set to `sync`; afterCommit avoids racing the transaction
        // that records the approval and queued run.
        $administratorEmails = $this->backupNotificationService->administratorEmails()->all();
        RestoreBackupJob::dispatch($run->id, $password, $administratorEmails)
            ->onConnection('database')
            ->delay(now()->addSeconds(self::RESTORE_START_DELAY_SECONDS))
            ->afterCommit();

        return $run;
    }

    /** Create a complete local snapshot without pruning the archive selected for restore. */
    public function createRestoreCheckpoint(?User $actor = null): BackupRun
    {
        $startedAt = now();
        $base = (string) config('backup.backup.name');
        $filenamePrefix = self::prefixFor(self::TIER_DAILY).'restore-checkpoint-'.Str::uuid().'-';

        try {
            $this->applyRuntimeConfiguration(
                self::TIER_DAILY,
                archivePassword: $this->archivePassword(),
                filenamePrefix: $filenamePrefix,
            );

            $exitCode = Artisan::call('backup:run', [
                '--only-to-disk' => self::diskFor(self::TIER_DAILY),
                '--disable-notifications' => true,
                '--no-interaction' => true,
                '--config' => 'backup',
            ]);
            if ($exitCode !== 0) {
                throw new RuntimeException('The safety checkpoint command failed: '.trim(Artisan::output()));
            }

            $disk = Storage::disk(self::diskFor(self::TIER_DAILY));
            $path = collect($disk->allFiles($base))
                ->filter(fn (string $file) => str_starts_with(basename($file), $filenamePrefix) && str_ends_with($file, '.zip'))
                ->sort()
                ->last();
            if (! is_string($path) || ! $disk->exists($path)) {
                throw new RuntimeException('The safety checkpoint command completed without creating a readable archive.');
            }

            $run = BackupRun::create([
                'tier' => self::TIER_DAILY,
                'scope' => self::SCOPE_BOTH,
                'status' => 'success',
                'filename' => $path,
                'size' => $disk->size($path),
                'started_at' => $startedAt,
                'finished_at' => now(),
                'triggered_by' => $actor?->id,
            ]);

            try {
                $this->auditLogService->log(
                    'backup.restore_checkpoint.created',
                    'A complete pre-restore safety checkpoint was created.',
                    'backup',
                    $run,
                    $actor,
                    ['file' => $path, 'scope' => self::SCOPE_BOTH],
                );
            } catch (Throwable) {
                // A valid checkpoint is more important than an audit write here.
            }

            return $run;
        } catch (Throwable $e) {
            $message = $this->operatorMessage($e);
            $run = BackupRun::create([
                'tier' => self::TIER_DAILY,
                'scope' => self::SCOPE_BOTH,
                'status' => 'failed',
                'message' => $message,
                'started_at' => $startedAt,
                'finished_at' => now(),
                'triggered_by' => $actor?->id,
            ]);

            try {
                $this->auditLogService->log('backup.restore_checkpoint.failed', 'The pre-restore safety checkpoint could not be created.', 'backup', $run, $actor, ['error' => $message]);
            } catch (Throwable) {
                // This runs before the restore, so preserve the original checkpoint failure.
            }
            $this->backupNotificationService->notifyFailure($run);

            throw new RuntimeException('Could not create the pre-restore safety checkpoint: '.$message, previous: $e);
        }
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
            $this->backupNotificationService->notifyFailure($run->refresh());

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
        return in_array($tier, self::VALID_TIERS, true);
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

        $gfs = $this->gfsRetentionService();

        return $files->map(function (string $path) use ($disk, $gfs) {
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

            $date = GfsRetentionService::parseArchiveDate($path, is_int($lastModified) ? $lastModified : null);
            $gfsTier = $gfs->categorizeArchive($date);

            return [
                'path' => $path,
                'name' => basename($path),
                'size' => $size,
                'created_at' => $lastModified ? Carbon::createFromTimestamp($lastModified)->toISOString() : $date->toISOString(),
                'gfs_tier' => $gfsTier,
            ];
        })->reverse()->values()->all();
    }

    public function newestBackupOnDisk(string $tier): ?array
    {
        return $this->list($tier)[0] ?? null;
    }

    /**
     * Delete a single backup archive from the given tier's disk.
     *
     * Manual deletion of backups is permanently disabled for security.
     */
    public function delete(string $tier, string $path, Model|Authenticatable|null $actor = null): void
    {
        throw new RuntimeException('Deleting backups is permanently disabled for security reasons.');
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

    public function importRestoreArchive(UploadedFile $archive, Model|Authenticatable|null $actor = null): string
    {
        $zip = new \ZipArchive;
        if (! $archive->isValid()
            || strtolower($archive->getClientOriginalExtension()) !== 'zip'
            || $zip->open($archive->getRealPath(), \ZipArchive::CHECKCONS) !== true) {
            throw new InvalidArgumentException('Choose a valid backup ZIP archive.');
        }

        $hasEntries = $zip->numFiles > 0;
        for ($index = 0; $hasEntries && $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);
            $normalizedEntry = is_string($entry) ? str_replace('\\', '/', $entry) : '';
            $isUnsafePath = $normalizedEntry === ''
                || str_starts_with($normalizedEntry, '/')
                || preg_match('/^[a-z]:/i', $normalizedEntry)
                || in_array('..', explode('/', $normalizedEntry), true);
            $operatingSystem = $attributes = 0;
            $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes);
            $fileType = ($attributes >> 16) & 0170000;
            if ($isUnsafePath || $fileType === 0120000) {
                $hasEntries = false;
            }
        }
        $zip->close();
        if (! $hasEntries) {
            throw new InvalidArgumentException('The uploaded ZIP is empty or contains unsafe paths.');
        }

        $base = (string) config('backup.backup.name');
        $filename = self::prefixFor(self::TIER_DAILY).'uploaded-'.now()->format('YmdHis').'-'.Str::uuid().'.zip';
        $path = Storage::disk(self::diskFor(self::TIER_DAILY))->putFileAs($base, $archive, $filename);
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The uploaded backup could not be stored.');
        }

        $this->auditLogService->log(
            'backup.restore_upload_staged',
            'An uploaded backup archive was staged for restore.',
            'backup',
            null,
            $actor,
            [
                'source' => 'uploaded_archive',
                'original_filename' => $archive->getClientOriginalName(),
                'storage_tier' => self::TIER_DAILY,
                'file' => basename($path),
            ],
        );

        return $path;
    }

    /**
     * Delete old backups on the tier's disk according to the Grandfather-Father-Son (GFS) policy.
     *
     * - daily / off-site: keep daily, weekly, and monthly tiers under GFS retention.
     * - Protects the newest backup and uploaded restore archives at all times.
     * - Gracefully catches and logs S3/B2 Object Lock protected archives instead of failing the job.
     */
    public function cleanup(string $tier, Model|Authenticatable|null $actor = null): void
    {
        if ($tier === self::TIER_OFFSITE && ! $this->isOffsiteConfigured()) {
            throw new RuntimeException('Backblaze B2 is not configured. Set the AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET and AWS_ENDPOINT values in .env.');
        }

        $disk = Storage::disk(self::diskFor($tier));
        $base = (string) config('backup.backup.name');

        $deleted = 0;
        $skippedLocked = 0;

        try {
            $archives = collect($disk->allFiles($base))
                ->filter(fn (string $path) => Str::startsWith(basename($path), self::prefixesFor($tier)) && str_ends_with($path, '.zip'))
                ->values();

            $archiveItems = $archives->map(function (string $path) use ($disk) {
                $lastModified = null;
                try {
                    $lastModified = $disk->lastModified($path);
                } catch (Throwable) {
                    // Metadata might not be available on some remote drivers
                }

                return [
                    'path' => $path,
                    'name' => basename($path),
                    'date' => GfsRetentionService::parseArchiveDate($path, is_int($lastModified) ? $lastModified : null),
                ];
            });

            $gfs = $this->gfsRetentionService();
            $plan = $gfs->planPruning($archiveItems);

            foreach ($plan['delete'] as $archive) {
                $path = $archive['path'];
                try {
                    $disk->delete($path);
                    $deleted++;
                } catch (Throwable $e) {
                    $msg = strtolower($e->getMessage());
                    if ($tier === self::TIER_OFFSITE && (
                        str_contains($msg, 'objectlock')
                        || str_contains($msg, 'accessdenied')
                        || str_contains($msg, 'protected')
                        || str_contains($msg, 'immutable')
                    )) {
                        $skippedLocked++;
                        $this->auditLogService->log(
                            'backup.cleanup_skipped_locked',
                            "Skipped locked backup archive [{$path}] on {$tier} tier due to Object Lock.",
                            'backup',
                            null,
                            $actor,
                            ['tier' => $tier, 'file' => $path, 'error' => Str::limit($e->getMessage(), 1000)],
                        );

                        continue;
                    }

                    throw $e;
                }
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
            "Old backups cleaned up on the {$tier} tier (GFS policy).",
            'backup',
            null,
            $actor,
            ['tier' => $tier, 'deleted' => $deleted, 'skipped_locked' => $skippedLocked],
        );
    }

    /**
     * Restore a backup archive (database, files or both).
     *
     * @param  string  $password  The archive encryption password (BACKUP_ARCHIVE_PASSWORD).
     */
    public function restore(
        string $tier,
        string $path,
        string $scope,
        string $password,
        Model|Authenticatable|null $actor = null,
        ?string $checkpointPath = null,
        array $adminEmails = [],
    ): void {
        $adminEmails = $adminEmails ?: $this->backupNotificationService->administratorEmails()->all();
        try {
            $this->validateRestoreRequest($tier, $path, $scope, $password);
            if (! is_string($checkpointPath) || $checkpointPath === '') {
                throw new RuntimeException('A pre-restore safety checkpoint is required.');
            }
            $this->validateRestoreRequest(self::TIER_DAILY, $checkpointPath, $scope, $password);
            $this->applyRuntimeConfiguration($tier, archivePassword: $password);
        } catch (Throwable $e) {
            $this->backupNotificationService->notifyRestoreEvent(
                $adminEmails,
                'Restore stopped before downtime',
                'Restore validation failed before the system was taken offline.',
                ['archive' => basename($path), 'error' => $this->operatorMessage($e)],
            );

            throw $e;
        }

        $source = $this->restoreSource($tier, $path);
        $sourceLabel = $source === 'uploaded_archive' ? 'an uploaded archive' : "the {$tier} tier";
        $notificationDetails = [
            'archive' => basename($path),
            'scope' => $scope,
            'checkpoint' => basename($checkpointPath),
        ];

        $restoreSecret = Str::random(32);
        $restoredFiles = false;
        $wentDown = false;
        $restoreSucceeded = false;
        $rollbackSucceeded = false;
        $targetFailure = null;
        $rollbackFailure = null;
        $maintenanceStartedAt = null;

        MaintenanceState::enter(MaintenanceState::REASON_RESTORE);

        try {
            Artisan::call('down', ['--secret' => $restoreSecret]);
            $wentDown = app()->maintenanceMode()->active();
            if (! $wentDown) {
                throw new RuntimeException('Laravel did not enter maintenance mode; restore was not started.');
            }
            $maintenanceStartedAt = hrtime(true);

            if (in_array($scope, [self::SCOPE_DB, self::SCOPE_BOTH], true)) {
                $this->restoreDatabase($tier, $path, $password);
            }

            if (in_array($scope, [self::SCOPE_FILES, self::SCOPE_BOTH], true)) {
                $this->restoreFiles($tier, $path, $password);
                $restoredFiles = true;
            }

            $this->clearCaches();
            $restoreSucceeded = true;
        } catch (Throwable $e) {
            $targetFailure = $e;
            if (! $wentDown && app()->maintenanceMode()->active()) {
                $wentDown = true;
                $maintenanceStartedAt = hrtime(true);
            }

            if ($wentDown) {
                MaintenanceState::enter(MaintenanceState::REASON_RESTORE_ROLLBACK, ['retry_after' => 30]);
                $this->backupNotificationService->notifyRestoreEvent(
                    $adminEmails,
                    'Restore failed ? automatic rollback started',
                    'The requested archive could not be restored. The system remains offline while the pre-restore checkpoint is applied.',
                    $notificationDetails + ['restore_error' => $this->operatorMessage($e)],
                );

                try {
                    $this->applyRuntimeConfiguration(self::TIER_DAILY, archivePassword: $password);
                    if (in_array($scope, [self::SCOPE_DB, self::SCOPE_BOTH], true)) {
                        $this->restoreDatabase(self::TIER_DAILY, $checkpointPath, $password);
                    }
                    if (in_array($scope, [self::SCOPE_FILES, self::SCOPE_BOTH], true)) {
                        $this->restoreFiles(self::TIER_DAILY, $checkpointPath, $password);
                    }
                    $this->clearCaches();
                    $rollbackSucceeded = true;
                } catch (Throwable $rollbackError) {
                    $rollbackFailure = $rollbackError;
                    MaintenanceState::enter(MaintenanceState::REASON_RESTORE_FAILED, ['retry_after' => 60]);
                    $this->backupNotificationService->notifyRestoreEvent(
                        $adminEmails,
                        'Restore and automatic rollback failed',
                        'The requested restore and its safety rollback both failed. The system is staying offline and requires hosting-level recovery.',
                        $notificationDetails + [
                            'restore_error' => $this->operatorMessage($e),
                            'rollback_error' => $this->operatorMessage($rollbackError),
                        ],
                    );
                }
            }

            try {
                $this->auditLogService->log('backup.restore_failed', "Restore failed from {$sourceLabel} ({$path}).", 'backup', null, $actor, ['source' => $source, 'storage_tier' => $tier, 'file' => $path, 'scope' => $scope, 'error' => Str::limit($e->getMessage(), 2000)]);
            } catch (Throwable) {
                // The database itself may be unavailable after a failed import.
            }
        } finally {
            if (! $wentDown) {
                MaintenanceState::leave();
            } elseif ($restoreSucceeded || $rollbackSucceeded) {
                try {
                    // Count from Laravel's actual down state, including restore and rollback work.
                    // Keep requests blocked until there has been time to observe the maintenance page.
                    $remaining = self::RESTORE_MINIMUM_DOWNTIME_SECONDS - (hrtime(true) - $maintenanceStartedAt) / 1_000_000_000;
                    if ($remaining > 0) {
                        Sleep::for($remaining)->seconds();
                    }

                    $exitCode = Artisan::call('up');
                    if ($exitCode !== 0 || app()->maintenanceMode()->active()) {
                        throw new RuntimeException('Restore recovery completed, but the application could not leave maintenance mode.');
                    }
                } catch (Throwable $e) {
                    try {
                        Artisan::call('down', ['--secret' => $restoreSecret]);
                    } catch (Throwable) {
                        // Keep the file-based notice if Laravel cannot rewrite its down file.
                    }
                    MaintenanceState::enter(MaintenanceState::REASON_RESTORE_FAILED, ['retry_after' => 60]);
                    $rollbackFailure ??= $e;
                    $this->backupNotificationService->notifyRestoreEvent(
                        $adminEmails,
                        'Restore recovery could not reopen the system',
                        'The data restore or rollback finished, but Laravel could not safely leave maintenance mode. The system remains offline and needs operator review.',
                        $notificationDetails + ['error' => $this->operatorMessage($e)],
                    );
                }

                if ($rollbackFailure === null) {
                    MaintenanceState::leave();
                }
            }
        }

        if ($rollbackFailure !== null) {
            throw new RuntimeException(
                'The requested restore failed and automatic recovery could not be completed. Restore error: '
                    .$this->operatorMessage($targetFailure ?? $rollbackFailure)
                    .'; recovery error: '.$this->operatorMessage($rollbackFailure),
                previous: $rollbackFailure,
            );
        }

        if ($targetFailure !== null) {
            if ($rollbackSucceeded) {
                $this->backupNotificationService->notifyRestoreEvent(
                    $adminEmails,
                    'Automatic rollback completed',
                    'The requested restore failed, but the pre-restore checkpoint was restored and the system is back online.',
                    $notificationDetails + ['restore_error' => $this->operatorMessage($targetFailure)],
                );
            } elseif (! $wentDown) {
                $this->backupNotificationService->notifyRestoreEvent(
                    $adminEmails,
                    'Restore stopped before downtime',
                    'The restore could not enter maintenance mode, so the selected archive was not applied and the system was not taken offline.',
                    $notificationDetails + ['error' => $this->operatorMessage($targetFailure)],
                );
            }

            throw $targetFailure;
        }

        $this->backupNotificationService->notifyRestoreEvent(
            $adminEmails,
            'Restore completed successfully',
            'The selected archive was restored successfully and the system is back online.',
            $notificationDetails,
        );

        try {
            $this->auditLogService->log('backup.restored', "Database restored from {$sourceLabel} ({$path}).", 'backup', null, $actor, ['source' => $source, 'storage_tier' => $tier, 'file' => $path, 'scope' => $scope, 'files_restored' => $restoredFiles, 'checkpoint' => $checkpointPath]);
        } catch (Throwable) {
            // A missing audit write must not turn a completed restore into a failed worker job.
        }
    }

    private function restoreSource(string $tier, string $path): string
    {
        return Str::startsWith(basename($path), self::prefixFor(self::TIER_DAILY).'uploaded-')
            ? 'uploaded_archive'
            : $tier;
    }

    private function validateRestoreRequest(string $tier, string $path, string $scope, string $password): void
    {
        if (! self::isValidScope($scope)) {
            throw new InvalidArgumentException("Unknown restore scope [{$scope}].");
        }

        $configuredPassword = $this->archivePassword();
        if (! is_string($configuredPassword) || $configuredPassword === '' || ! hash_equals($configuredPassword, $password)) {
            throw new RuntimeException('The archive encryption password does not match the configured backup password.');
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
        $gfs = $this->gfsRetentionService();

        foreach (self::VALID_TIERS as $tier) {
            $tiers[$tier] = [
                'disk' => self::diskFor($tier),
                'schedule' => self::scheduleFor($tier),
                'scope' => self::SCOPE_BOTH,
                'last_auto_run' => SystemSetting::get("backup_{$tier}_last_auto_run"),
                'retention' => [
                    'days' => $gfs->getDailyDays(),
                    'gfs' => [
                        'daily_days' => $gfs->getDailyDays(),
                        'weekly_weeks' => $gfs->getWeeklyWeeks(),
                        'monthly_months' => $gfs->getMonthlyMonths(),
                    ],
                ],
            ];
        }

        $offsite = $this->offsiteStatus();
        $offsite['object_lock'] = [
            'enabled' => (bool) config('backup.object_lock.enabled'),
            'mode' => (string) config('backup.object_lock.mode'),
            'retention_days' => (int) config('backup.object_lock.retention_days'),
        ];

        return [
            'tiers' => $tiers,
            'daily_time' => SystemSetting::get('backup_daily_time', self::DEFAULT_DAILY_TIME),
            'offsite' => $offsite,
        ];
    }

    /**
     * Persist the daily backup time after countersign approval.
     */
    public function updateDailyTime(string $time): void
    {
        SystemSetting::set('backup_daily_time', $time);
    }

    public function updateArchivePassword(string $password): void
    {
        SystemSetting::set('backup_archive_password', $password);
    }

    public function archivePassword(): string
    {
        $password = SystemSetting::get('backup_archive_password');
        if (is_string($password) && $password !== '') {
            return $password;
        }

        $password = config('backup.backup.password');
        if (is_string($password) && $password !== '') {
            return $password;
        }

        throw new RuntimeException('Backup encryption is required but no archive password is configured.');
    }

    /**
     * Point the backup engine at the tier's disk, filename prefix and retention
     * for the duration of this process (CLI schedules and API calls alike).
     */
    private function applyRuntimeConfiguration(string $tier, ?string $runId = null, ?string $archivePassword = null, ?string $filenamePrefix = null): void
    {
        $disk = self::diskFor($tier);

        // Point the "destination" disk list at the tier's disk only so the
        // spawned backup:run command writes to the correct tier, using the
        // tier's own prefix so shared disks stay distinguishable.
        config(['backup.backup.destination.disks' => [$disk]]);
        config(['backup.backup.destination.filename_prefix' => $filenamePrefix ?? self::prefixFor($tier)]);
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

        $archivePassword ??= $this->archivePassword();
        config(['backup.backup.password' => $archivePassword]);
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

        $options = [];
        if (config('backup.object_lock.enabled')) {
            $mode = (string) config('backup.object_lock.mode', 'GOVERNANCE');
            $days = (int) config('backup.object_lock.retention_days', 30);
            $options = [
                'ObjectLockMode' => $mode,
                'ObjectLockRetainUntilDate' => now()->addDays($days)->toIso8601ZuluString(),
            ];
        }

        if ($stream === false || ! $destination->writeStream($path, $stream, $options)) {
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
