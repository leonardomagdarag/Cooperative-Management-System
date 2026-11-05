<?php

namespace App\Console\Commands;

use App\Models\ArchivedTransaction;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveOldTransactions extends Command
{
    protected $signature = 'transactions:archive 
                            {--years=3 : Archive transactions older than this many years}
                            {--dry-run : Show what would be archived without actually archiving}';

    protected $description = 'Archive old transactions to the archived_transactions table';

    public function handle(): int
    {
        $years = (int) $this->option('years');
        $dryRun = $this->option('dry-run');
        $cutoffDate = now()->subYears($years);

        $query = Transaction::where('transaction_date', '<', $cutoffDate);
        $count = $query->count();

        if ($count === 0) {
            $this->info("No transactions older than {$years} years found.");

            return Command::SUCCESS;
        }

        $this->info("Found {$count} transactions older than {$years} years (before {$cutoffDate->format('Y-m-d')}).");

        if ($dryRun) {
            $this->warn('Dry run mode - no changes will be made.');
            $this->table(
                ['Type', 'Count'],
                $query->selectRaw('transaction_type, COUNT(*) as count')
                    ->groupBy('transaction_type')
                    ->get()
                    ->map(fn ($item) => [$item->transaction_type, $item->count])
                    ->toArray()
            );

            return Command::SUCCESS;
        }

        if (! $this->confirm("Do you want to archive these {$count} transactions?")) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        $this->info('Archiving transactions...');

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $archived = 0;

        DB::transaction(function () use ($query, $bar, &$archived) {
            $query->chunkById(500, function ($transactions) use ($bar, &$archived) {
                foreach ($transactions as $transaction) {
                    ArchivedTransaction::create([
                        'original_id' => $transaction->id,
                        'reversal_of_id' => $transaction->reversal_of_id,
                        'transaction_number' => $transaction->transaction_number,
                        'transaction_type' => $transaction->transaction_type,
                        'account_id' => $transaction->account_id,
                        'loan_id' => $transaction->loan_id,
                        'amount' => $transaction->amount,
                        'fee_amount' => $transaction->fee_amount,
                        'transaction_date' => $transaction->transaction_date,
                        'note' => $transaction->note,
                        'recorded_by' => $transaction->recorded_by,
                        'created_at' => $transaction->created_at,
                        'updated_at' => $transaction->updated_at,
                    ]);

                    $transaction->delete();
                    $archived++;
                    $bar->advance();
                }
            });
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Successfully archived {$archived} transactions.");

        return Command::SUCCESS;
    }
}
