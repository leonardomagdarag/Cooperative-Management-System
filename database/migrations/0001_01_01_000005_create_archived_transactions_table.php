<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Archive table for old transactions (compliance & performance).
     * Records are moved here via scheduled job after X years.
     */
    public function up(): void
    {
        Schema::create('archived_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index(); // Original transaction ID
            $table->string('transaction_number', 20)->index();
            $table->enum('transaction_type', ['deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'interest', 'fee']);
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('loan_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->dateTime('transaction_date')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->useCurrent();

            // Indexes for common archive queries
            $table->index('transaction_type');
            $table->index('transaction_date');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_transactions');
    }
};
