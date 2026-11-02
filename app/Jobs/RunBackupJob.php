<?php

namespace App\Jobs;

use App\Models\BackupRun;
use App\Services\BackupNotificationService;
use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RunBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $runId)
    {
        $this->onQueue('backups');
    }

    public function handle(BackupService $backups): void
    {
        $run = BackupRun::findOrFail($this->runId);
        $lock = Cache::store('backup_operations')->lock('backup-operation', 7200);
        $lockAcquired = false;

        try {
            if (! $lock->get()) {
                throw new \RuntimeException('Another backup or restore operation is already active.');
            }

            $lockAcquired = true;
            $run->update(['status' => 'running', 'started_at' => now()]);
            $backups->executeRun($run, $run->trigger);
        } catch (Throwable $e) {
            // executeRun() records and notifies its own failures. Only notify
            // here when the job failed before executeRun() could do that (for
            // example, when the operation lock could not be acquired).
            $run->refresh();
            if ($run->status !== 'failed') {
                $run->update([
                    'status' => 'failed',
                    'message' => $backups->operatorMessage($e),
                    'finished_at' => now(),
                ]);
                app(BackupNotificationService::class)->notifyFailure($run->refresh());
            }
            throw $e;
        } finally {
            if ($lockAcquired) {
                $lock->release();
            }
        }
    }

    /** Mark the run terminal if the worker exhausts attempts or times out. */
    public function failed(Throwable $exception): void
    {
        $run = BackupRun::find($this->runId);
        if (! $run || in_array($run->status, ['success', 'failed'], true)) {
            return;
        }

        $run->update([
            'status' => 'failed',
            'message' => app(BackupService::class)->operatorMessage($exception),
            'finished_at' => now(),
        ]);
        app(BackupNotificationService::class)->notifyFailure($run->refresh());
    }
}
