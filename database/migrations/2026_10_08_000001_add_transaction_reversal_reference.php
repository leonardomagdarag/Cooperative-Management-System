<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Keep the source id when the row moves to archived_transactions.
            // The unique index is the idempotency guard for one reversal per source.
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->unique('reversal_of_id', 'transactions_reversal_of_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_reversal_of_id_unique');
            $table->dropColumn('reversal_of_id');
        });
    }
};
