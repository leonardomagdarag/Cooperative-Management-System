<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Wnx\LaravelBackupRestore\Actions\CleanupLocalBackupAction;
use Wnx\LaravelBackupRestore\Actions\DecompressBackupAction;
use Wnx\LaravelBackupRestore\Actions\DownloadBackupAction;
use Wnx\LaravelBackupRestore\PendingRestore;

class BackupRestoreFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'backup:restore-files
                            {--disk= : The disk where the backup archive is stored.}
                            {--backup= : The backup archive path to restore files from.}
                            {--password= : The encryption password of the archive.}';

    /**
     * The console command description.
     */
    protected $description = 'Restore the user-uploaded public files from a backup archive';

    public function handle(): int
    {
        $disk = $this->option('disk');
        $backup = $this->option('backup');
        $password = $this->option('password');

        if (! $backup) {
            $this->error('A --backup path is required.');

            return self::INVALID;
        }

        try {
            $connection = config('backup.backup.source.databases')[0];

            $pendingRestore = PendingRestore::make(
                disk: $disk,
                backup: $backup,
                connection: $connection,
                backupPassword: $password,
            );

            app(DownloadBackupAction::class)->execute($pendingRestore);
            app(DecompressBackupAction::class)->execute($pendingRestore);

            $extractRoot = $pendingRestore->getAbsolutePathToLocalDecompressedBackup();
            $sourcePublic = $extractRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';

            if (! is_dir($sourcePublic)) {
                throw new RuntimeException('The archive does not contain a file tree. Pick a backup created with the "files" or "both" scope.');
            }

            $target = storage_path('app/public');
            $preRestore = storage_path('app/backup-restore-temp').DIRECTORY_SEPARATOR.'public-pre-restore-'.basename($extractRoot);

            if (is_dir($target)) {
                File::moveDirectory($target, $preRestore, true);
            }

            try {
                File::copyDirectory($sourcePublic, $target);
            } catch (\Throwable $e) {
                if (is_dir($target)) {
                    File::deleteDirectory($target);
                }

                if (is_dir($preRestore)) {
                    File::moveDirectory($preRestore, $target, true);
                }

                throw $e;
            }

            if (is_dir($preRestore)) {
                File::deleteDirectory($preRestore);
            }

            app(CleanupLocalBackupAction::class)->execute($pendingRestore);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Files restored to '.storage_path('app/public'));

        return self::SUCCESS;
    }
}
