<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class CleanupOldNotifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notifications:cleanup';

    /**
     * The console command description.
     */
    protected $description = 'Cleanup old read notifications (older than 30 days) and expired notifications';

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Cleaning up old notifications...');

        $deletedRead = $this->notificationService->cleanupOldNotifications();
        $this->line("Deleted {$deletedRead} old read notifications (older than 30 days).");

        $deletedExpired = $this->notificationService->cleanupExpiredNotifications();
        $this->line("Deleted {$deletedExpired} expired notifications.");

        $total = $deletedRead + $deletedExpired;
        $this->info("Total notifications cleaned up: {$total}");

        return Command::SUCCESS;
    }
}
