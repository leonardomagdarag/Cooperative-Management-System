<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transaction_number_sequences')) {
            Schema::create('transaction_number_sequences', function (Blueprint $table) {
                $table->date('business_date')->primary();
                $table->unsignedBigInteger('last_sequence')->default(0);
                $table->timestamps();
            });
        }

        $sequences = [];
        $remember = static function (?string $number) use (&$sequences): void {
            if ($number && preg_match('/^TXN(\d{8})(\d+)$/', $number, $matches)) {
                $month = (int) substr($matches[1], 4, 2);
                $day = (int) substr($matches[1], 6, 2);
                if (! checkdate($month, $day, (int) substr($matches[1], 0, 4))) {
                    return;
                }

                $date = substr($matches[1], 0, 4).'-'.substr($matches[1], 4, 2).'-'.substr($matches[1], 6, 2);
                $sequences[$date] = max($sequences[$date] ?? 0, (int) $matches[2]);
            }
        };

        if (Schema::hasTable('transactions')) {
            DB::table('transactions')->select('transaction_number')->orderBy('id')->chunk(1000, function ($rows) use ($remember): void {
                foreach ($rows as $row) {
                    $remember($row->transaction_number);
                }
            });
        }

        if (Schema::hasTable('archived_transactions')) {
            DB::table('archived_transactions')->select('transaction_number')->orderBy('id')->chunk(1000, function ($rows) use ($remember): void {
                foreach ($rows as $row) {
                    $remember($row->transaction_number);
                }
            });
        }

        if (Schema::hasTable('privileged_action_requests')) {
            DB::table('privileged_action_requests')->select('id', 'payload')->orderBy('id')->chunk(500, function ($rows) use ($remember): void {
                foreach ($rows as $row) {
                    $payload = json_decode($row->payload, true);
                    $walk = static function (mixed $value) use (&$walk, $remember): void {
                        if (! is_array($value)) {
                            return;
                        }

                        foreach ($value as $key => $item) {
                            if ($key === 'transaction_number' && is_string($item)) {
                                $remember($item);
                            } else {
                                $walk($item);
                            }
                        }
                    };
                    $walk($payload);
                }
            });
        }

        foreach ($sequences as $date => $highestSequence) {
            DB::table('transaction_number_sequences')->insertOrIgnore([
                'business_date' => $date,
                'last_sequence' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $current = (int) DB::table('transaction_number_sequences')->where('business_date', $date)->value('last_sequence');
            if ($highestSequence > $current) {
                DB::table('transaction_number_sequences')->where('business_date', $date)->update([
                    'last_sequence' => $highestSequence,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep allocated numbers across code rollbacks; dropping this history could reissue audit references.
    }
};
