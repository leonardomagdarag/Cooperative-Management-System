<?php

namespace App\Console\Commands;

use App\Models\LoanDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateLoanDocumentsToPrivateStorage extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'loans:migrate-documents-storage';

    /**
     * The console command description.
     */
    protected $description = 'Migrate existing public loan documents to private local storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Migrating loan documents to private storage...');

        $documents = LoanDocument::all();
        $migratedCount = 0;

        foreach ($documents as $doc) {
            if (Storage::disk('public')->exists($doc->storage_path)) {
                $content = Storage::disk('public')->get($doc->storage_path);
                Storage::disk('local')->put($doc->storage_path, $content);
                Storage::disk('public')->delete($doc->storage_path);
                $migratedCount++;
            }
        }

        $this->info("Successfully migrated {$migratedCount} loan document(s) to private storage.");

        return Command::SUCCESS;
    }
}
