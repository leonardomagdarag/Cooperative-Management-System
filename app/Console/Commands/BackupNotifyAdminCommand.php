<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Models\SystemSetting;
use App\Services\BackupNotificationService;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class BackupNotifyAdminCommand extends Command
{
    protected $signature = 'backup:notify-admin';

    protected $description = 'Send backup download and storage capacity notifications to administrators';

    public function handle(BackupNotificationService $notifications): int
    {
        $this->sendDownloadReminders($notifications);
        $this->checkStorage($notifications);

        return self::SUCCESS;
    }

    private function sendDownloadReminders(BackupNotificationService $notifications): void
    {
        $latest = BackupRun::query()
            ->where('tier', BackupService::TIER_DAILY)
            ->where('status', 'success')
            ->latest('finished_at')
            ->first();

        if (! $latest) {
            return;
        }

        foreach ([
            'biweekly' => [14, 'every 2 weeks'],
            'monthly' => [1, 'every month'],
        ] as $cadence => [$monthsOrDays, $label]) {
            $key = "backup_download_reminder_{$cadence}_last_sent";
            $lastSent = SystemSetting::get($key);

            if (! $lastSent) {
                SystemSetting::set($key, now()->toDateTimeString(), 'string', 'backup');

                continue;
            }

            try {
                $dueAt = $cadence === 'monthly'
                    ? Carbon::parse($lastSent)->addMonthNoOverflow()
                    : Carbon::parse($lastSent)->addDays($monthsOrDays);
            } catch (Throwable) {
                $dueAt = now();
            }

            if (now()->lt($dueAt)) {
                continue;
            }

            $notifications->send(
                'Backup download reminder',
                "Please download and store a copy of the latest backup. This is your {$label} reminder.",
                [
                    'latest_backup' => (string) ($latest->filename ?: 'Available in Backup & Restore'),
                    'completed_at' => (string) ($latest->finished_at?->toDateTimeString() ?: 'Unknown'),
                    'size_bytes' => (string) ($latest->size ?? 'Unknown'),
                ],
            );
            SystemSetting::set($key, now()->toDateTimeString(), 'string', 'backup');
        }
    }

    private function checkStorage(BackupNotificationService $notifications): void
    {
        $root = (string) config('filesystems.disks.local_backups.root');
        $total = @disk_total_space($root);
        $free = @disk_free_space($root);

        if (! is_numeric($total) || ! is_numeric($free) || (float) $total <= 0) {
            return;
        }

        $usedPercent = ((float) $total - (float) $free) / (float) $total * 100;
        $threshold = max(1, min(99, (int) SystemSetting::get('backup_storage_warning_percent', 85)));
        $warning = $usedPercent >= $threshold;
        $wasWarning = (bool) SystemSetting::get('backup_storage_warning_active', false);

        if ($warning && ! $wasWarning) {
            $notifications->send(
                'Backup storage is nearing capacity',
                'The server storage used for local backup archives is nearing its configured capacity threshold.',
                [
                    'used_percent' => number_format($usedPercent, 1).'%',
                    'threshold' => $threshold.'%',
                    'free_bytes' => (string) (int) $free,
                    'path' => $root,
                ],
            );
        }

        if ($warning !== $wasWarning) {
            SystemSetting::set('backup_storage_warning_active', $warning, 'boolean', 'backup');
        }
    }
}
