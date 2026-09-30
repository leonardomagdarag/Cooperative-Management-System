<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule notification checks daily at 8 AM
Schedule::command('notifications:check-loan-due')
    ->dailyAt('08:00')
    ->description('Check for loans due soon and send notifications');

// Schedule cleanup of old notifications daily at midnight
Schedule::command('notifications:cleanup')
    ->daily()
    ->description('Cleanup old read notifications and expired notifications');

// Schedule cleanup of old activity logs daily at midnight
Schedule::command('activitylog:clean')
    ->daily()
    ->description('Cleanup old activity log records based on retention policy');

// Backups: the ping command acts as the deciding agent. It reads the per-tier
// cron expressions from the database (SystemSetting) and only runs what is due.
// If the host has no cron (e.g. shared hosting), point a cron at /backup/ping
// instead — see docs/BACKUPS.md.
Schedule::command('backup:ping')
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Run scheduled backups that are due');

// Shared hosting (Namecheap) has no persistent queue worker, so drain the
// queue on every scheduler tick instead. `--stop-when-empty` exits as soon as
// the queue is drained. `--max-time` keeps the run inside the 60s window so it
// cannot outlive the tick, and `--timeout` stops a single stuck job from
// burning the whole window. All three queues are named explicitly because a
// bare `queue:work` only drains `default` — OTP/email jobs sit on `emails` and
// backup jobs on `backups`, and those would otherwise never run.
// Declared last so it runs after the commands above that dispatch jobs.
Schedule::command('queue:work --queue=default,emails,backups --stop-when-empty --max-time=50 --sleep=1 --tries=3 --timeout=45')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/queue-worker.log'))
    ->description('Drain the pending queue jobs (default, emails, backups)');
