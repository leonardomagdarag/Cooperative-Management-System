<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transaction_receipts', function (Blueprint $table) {
            // Reprints/reissues of transactions that carry only a loan_id
            // (collection-run payments) may not resolve a member account.
            $table->unsignedBigInteger('issued_to_member_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('issued_to_member_id')->nullable(false)->change();
        });
    }
};
