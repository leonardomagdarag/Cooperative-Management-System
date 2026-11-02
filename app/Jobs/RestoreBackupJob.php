<?php

namespace App\Jobs;

use App\Models\RestoreRun;
use App\Services\BackupNotificationService;
use App\Services\BackupService;
use App\Support\MaintenanceState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RestoreBackupJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $runId, private string $archivePassword, private array $administratorEmails = [])
    {
        $this->onQueue('backups');
    }

    public function handle(BackupService $backups): void
    {
        $run = RestoreRun::findOrFail($this->runId);
        $lock = Cache::store('backup_operations')->lock('backup-operation', 7200);
        $lockAcquired = false;
        $restoreAttempted = false;
        $checkpointPath = null;

        try {
            if (! $lock->get()) {
                throw new \RuntimeException('Another backup or restore operation is already active.');
            }
            $lockAcquired = true;
            $run->update(['status' => 'running', 'started_at' => now()]);
            $notifications = app(BackupNotificationService::class);
            $details = [
                'restore_run_id' => (string) $run->id,
                'archive' => basename($run->path),
                'scope' => $run->scope,
            ];
            $notifications->notifyRestoreEvent(
                $this->administratorEmails,
                'Restore approved — creating safety checkpoint',
                'The approved restore is preparing a backup of the current database and files before applying the selected archive.',
                $details,
            );

            $checkpoint = $backups->createRestoreCheckpoint($run->trigger);
            $checkpointPath = $checkpoint->filename;
            $run->update(['rollback_path' => $checkpoint->filename]);
            $notifications->notifyRestoreEvent(
                $this->administratorEmails,
                'Restore safety checkpoint created',
                'The current database and files were saved. The requested restore is starting; if it fails, the worker will attempt to restore this checkpoint.',
                $details + ['checkpoint' => basename((string) $checkpoint->filename)],
            );

            $restoreAttempted = true;
            $backups->restore(
                $run->tier,
                $run->path,
                $run->scope,
                $this->archivePassword,
                $run->trigger,
                (string) $checkpoint->filename,
                $this->administratorEmails,
            );

            // The target database archive can replace this run row, so status
            // tracking after a successful restore is best effort.
            try {
                $run->update(['status' => 'success', 'finished_at' => now()]);
            } catch (Throwable) {
                // The restore completion email is sent independently.
            }
        } catch (Throwable $e) {
            try {
                $run->update([
                    'status' => 'failed',
                    'message' => $backups->operatorMessage($e),
                    'rollback_path' => $checkpointPath,
                    'finished_at' => now(),
                ]);
            } catch (Throwable) {
                // A failed database restore can make this row unavailable too.
                // Preserve the original failure and the maintenance gate.
            }

            if (! $restoreAttempted) {
                app(BackupNotificationService::class)->notifyRestoreEvent(
                    $this->administratorEmails,
                    'Restore stopped before downtime',
                    'The worker could not begin the restore or finish its safety checkpoint. The selected archive was not applied and the system was not taken offline.',
                    ['restore_run_id' => (string) $this->runId, 'error' => $backups->operatorMessage($e)],
                );
            }

            throw $e;
        } finally {
            if ($lockAcquired) {
                $lock->release();
            }
        }
    }

    /** Notify admins if Laravel reports a terminal worker failure after downtime began. */
    public function failed(Throwable $exception): void
    {
        if ($this->administratorEmails === [] || ! app()->maintenanceMode()->active()) {
            return;
        }

        if ((MaintenanceState::current()['reason'] ?? null) === MaintenanceState::REASON_RESTORE_FAILED) {
            // BackupService already sent the detailed rollback-failure notice.
            return;
        }

        app(BackupNotificationService::class)->notifyRestoreEvent(
            $this->administratorEmails,
            'Restore worker failed while the system was offline',
            'The restore worker stopped while Laravel was in maintenance mode. The system remains offline; an administrator must inspect the worker error and recovery checkpoint.',
            [
                'restore_run_id' => (string) $this->runId,
                'error' => app(BackupService::class)->operatorMessage($exception),
            ],
        );
    }
}
