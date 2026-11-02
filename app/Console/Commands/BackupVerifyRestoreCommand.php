<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\AuditLogService;
use App\Services\BackupNotificationService;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use Wnx\LaravelBackupRestore\Actions\CleanupLocalBackupAction;
use Wnx\LaravelBackupRestore\Actions\DecompressBackupAction;
use Wnx\LaravelBackupRestore\Actions\DownloadBackupAction;
use Wnx\LaravelBackupRestore\Actions\ImportDumpAction;
use Wnx\LaravelBackupRestore\Actions\ResetDatabaseAction;
use Wnx\LaravelBackupRestore\PendingRestore;

class BackupVerifyRestoreCommand extends Command
{
    protected $signature = 'backup:verify-restore {--archive= : Local daily archive path; defaults to the latest dated daily archive}';

    protected $description = 'Restore a complete backup into a dedicated disposable MySQL database and inspect its files';

    public function handle(BackupService $backups, AuditLogService $audit, BackupNotificationService $notifications): int
    {
        $run = null;
        $archive = null;
        $pending = null;
        $lock = Cache::store('backup_operations')->lock('backup-operation', 7200);
        $locked = false;

        try {
            $this->assertIsolatedDatabase();

            $archive = $this->selectArchive();
            $run = BackupRun::query()->where('filename', $archive)->first();

            if (! Storage::disk('local_backups')->exists($archive)) {
                throw new RuntimeException('The selected local backup archive is missing.');
            }
            if (! $lock->get()) {
                throw new RuntimeException('Another backup or restore operation is active.');
            }
            $locked = true;

            // The package's actions always use this named connection. No production
            // connection, public uploads directory, or maintenance mode is changed.
            $pending = PendingRestore::make(
                disk: 'local_backups',
                backup: $archive,
                connection: 'restore_verification',
                backupPassword: $backups->archivePassword(),
            );
            app(DownloadBackupAction::class)->execute($pending);
            app(DecompressBackupAction::class)->execute($pending);

            $files = $pending->getAbsolutePathToLocalDecompressedBackup().DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';
            if (! is_dir($files)) {
                throw new RuntimeException('The archive has no public files directory.');
            }
            $fileCount = $this->checkFiles($files);
            if ($pending->getAvailableDbDumps()->isEmpty()) {
                throw new RuntimeException('The archive has no database dump.');
            }

            app(ResetDatabaseAction::class)->execute($pending);
            app(ImportDumpAction::class)->execute($pending);

            foreach (['users', 'loans', 'transactions'] as $table) {
                if (! DB::connection('restore_verification')->getSchemaBuilder()->hasTable($table)) {
                    throw new RuntimeException("The restored database is missing the {$table} table.");
                }
                DB::connection('restore_verification')->table($table)->count();
            }

            try {
                $audit->log('backup.restore_verified', 'Backup passed an isolated restore drill.', 'backup', $run, null, [
                    'file' => $archive,
                    'checked_files' => $fileCount,
                ]);
            } catch (Throwable) {
                // Recording the outcome must not change whether the archive passed.
            }
            $this->info("Restore drill passed for {$archive}; checked {$fileCount} files and three key tables.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $secrets = array_filter([
                config('database.connections.restore_verification.password'),
                $pending?->backupPassword,
            ], fn ($secret) => is_string($secret) && $secret !== '');
            $message = str_replace($secrets, '[redacted]', $backups->operatorMessage($e));
            $this->error('Restore drill failed: '.$message);
            try {
                $audit->log('backup.restore_verification_failed', 'Isolated backup restore drill failed.', 'backup', $run, null, [
                    'file' => $archive,
                    'error' => $message,
                ]);
            } catch (Throwable) {
                // The primary failure may be a database outage.
            }
            try {
                $notifications->notifyRestoreEvent(
                    $notifications->administratorEmails()->all(),
                    'Backup restore drill failed',
                    'A complete daily backup could not be verified in the isolated restore database. Review the backup and verification environment.',
                    ['archive' => basename((string) $archive), 'error' => $message],
                );
            } catch (Throwable) {
                // Keep the original failure as the command result.
            }

            return self::FAILURE;
        } finally {
            try {
                if ($pending !== null) {
                    app(CleanupLocalBackupAction::class)->execute($pending);
                    Storage::disk($pending->restoreDisk)->delete($pending->getPathToLocalCompressedBackup());
                }
            } finally {
                if ($locked) {
                    $lock->release();
                }
            }
        }
    }

    private function selectArchive(): string
    {
        $base = (string) config('backup.backup.name');
        $requested = $this->option('archive');
        if ($requested !== null) {
            $pattern = '#^'.preg_quote($base, '#').'/backup-daily-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.zip$#';
            if (preg_match($pattern, $requested) !== 1) {
                throw new RuntimeException('Select a dated daily archive on the local backup disk.');
            }

            return $requested;
        }

        $archives = collect(Storage::disk('local_backups')->allFiles($base))
            ->filter(fn (string $path) => preg_match('#^'.preg_quote($base, '#').'/backup-daily-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.zip$#', $path) === 1)
            ->sort()
            ->values();

        return $archives->last() ?? throw new RuntimeException('No dated daily backup archive was found.');
    }

    private function assertIsolatedDatabase(): void
    {
        $verification = config('database.connections.restore_verification');
        $production = config('database.connections.'.config('database.default'));

        if (($verification['driver'] ?? null) !== 'mysql'
            || blank($verification['database'] ?? null)
            || blank($verification['username'] ?? null)
            || blank($verification['password'] ?? null)
            || ($verification['database'] ?? null) === ($production['database'] ?? null)
            || ($verification['username'] ?? null) === ($production['username'] ?? null)) {
            throw new RuntimeException('Configure a separate MySQL database and distinct restricted account for restore verification.');
        }

        $actualDatabase = DB::connection('restore_verification')->selectOne('select database() as name')->name ?? null;
        if ($actualDatabase !== $verification['database'] || $actualDatabase === DB::connection()->getDatabaseName()) {
            throw new RuntimeException('Restore verification connection does not point to the expected isolated database.');
        }

        if (in_array($production['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $verificationAccount = DB::connection('restore_verification')->selectOne('select current_user() as name')->name ?? null;
            $productionAccount = DB::connection()->selectOne('select current_user() as name')->name ?? null;
            if ($verificationAccount === $productionAccount) {
                throw new RuntimeException('Restore verification is using the production MySQL account.');
            }
        }
    }

    private function checkFiles(string $directory): int
    {
        $count = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! $file->isFile() || hash_file('sha256', $file->getPathname()) === false) {
                throw new RuntimeException('An archived public file could not be read.');
            }
            $count++;
        }

        return $count;
    }
}
