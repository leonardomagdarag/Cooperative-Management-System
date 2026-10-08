<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class VerifyPrivilegedLogCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'oversight:verify-log';

    /**
     * The console command description.
     */
    protected $description = 'Verify the hash chain of the privileged actions log.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $logPath = 'privileged-actions.jsonl';

        if (! Storage::disk('local')->exists($logPath)) {
            $this->info('No privileged actions log found.');

            return 0;
        }

        $contents = Storage::disk('local')->get($logPath);
        $lines = array_filter(explode("\n", $contents));

        $expectedPrev = '0000000000000000000000000000000000000000000000000000000000000000';

        foreach ($lines as $i => $line) {
            $entry = json_decode($line, true);

            if (! $entry) {
                $this->error('Invalid JSON at line '.($i + 1));

                return 1;
            }

            if (($entry['prev'] ?? '') !== $expectedPrev) {
                $this->error('Hash chain broken at line '.($i + 1).". Expected prev: {$expectedPrev}, found: ".($entry['prev'] ?? 'none'));

                return 1;
            }

            $expectedPrev = hash('sha256', trim($line));
        }

        $this->info('Hash chain verified successfully. '.count($lines).' entries checked.');

        return 0;
    }
}
