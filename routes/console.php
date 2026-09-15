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
