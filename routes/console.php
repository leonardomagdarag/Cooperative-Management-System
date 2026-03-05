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
