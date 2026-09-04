<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ClearAllCaches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example usage: php artisan clear:all
     */
    protected $signature = 'clear:all';

    /**
     * The console command description.
     */
    protected $description = 'Clear and rebuild all caches (route, view, config, event, app)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing caches...');

        Artisan::call('route:clear');
        $this->info('Routes cleared');

        Artisan::call('view:clear');
        $this->info('Views cleared');

        Artisan::call('config:clear');
        $this->info('Config cleared');

        Artisan::call('cache:clear');
        $this->info('Application cache cleared');

        Artisan::call('event:clear');
        $this->info(' Events cleared');

        $this->newLine();
        $this->info('Rebuilding caches...');

        Artisan::call('config:cache');
        $this->info('Config cache rebuilt');

        Artisan::call('route:cache');
        $this->info('Route cache rebuilt');

        Artisan::call('event:cache');
        $this->info('Event cache rebuilt');

        Artisan::call('permission:cache-reset');
        $this->info('Spatie permission cache rebuilt');

        $this->newLine();
        $this->info('All caches cleared and rebuilt successfully!');

        return Command::SUCCESS;
    }
}
