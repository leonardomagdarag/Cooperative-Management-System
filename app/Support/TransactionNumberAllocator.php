<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class TransactionNumberAllocator
{
    public function observe(string $transactionNumber): void
    {
        if (! preg_match('/^TXN(\d{8})(\d+)$/', $transactionNumber, $matches)) {
            return;
        }

        $month = (int) substr($matches[1], 4, 2);
        $day = (int) substr($matches[1], 6, 2);
        if (! checkdate($month, $day, (int) substr($matches[1], 0, 4))) {
            return;
        }

        $date = substr($matches[1], 0, 4).'-'.substr($matches[1], 4, 2).'-'.substr($matches[1], 6, 2);
        $sequence = (int) $matches[2];

        DB::table('transaction_number_sequences')->insertOrIgnore([
            'business_date' => $date,
            'last_sequence' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('transaction_number_sequences')
            ->where('business_date', $date)
            ->where('last_sequence', '<', $sequence)
            ->update(['last_sequence' => $sequence, 'updated_at' => now()]);
    }

    public function next(?CarbonInterface $date = null): string
    {
        $date ??= now();
        $dateKey = $date->format('Y-m-d');

        $sequence = DB::transaction(function () use ($dateKey): int {
            DB::table('transaction_number_sequences')->insertOrIgnore([
                'business_date' => $dateKey,
                'last_sequence' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = DB::table('transaction_number_sequences')
                ->where('business_date', $dateKey)
                ->lockForUpdate()
                ->first();

            $next = ((int) $counter->last_sequence) + 1;

            DB::table('transaction_number_sequences')
                ->where('business_date', $dateKey)
                ->update(['last_sequence' => $next, 'updated_at' => now()]);

            return $next;
        }, attempts: 3);

        return 'TXN'.$date->format('Ymd').str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
