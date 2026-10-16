<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the `interest` transaction type used by the financial-accounts
     * interest-crediting engine.
     *
     * The base migration defines `transaction_type` as an enum. On MySQL this
     * is a real ENUM; on SQLite (the feature-test driver) it becomes a
     * `varchar check (...)` constraint. Native column modification handles both
     * drivers in Laravel 12, and the SQLite table rebuild preserves indexes.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('transaction_type', ['deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'interest', 'fee'])
                ->default('deposit')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('transaction_type', ['deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'fee'])
                ->default('deposit')
                ->change();
        });
    }
};
