<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_payment_installment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->unsignedSmallInteger('period');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->unique(['transaction_id', 'period'], 'loan_payment_installment_allocations_transaction_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payment_installment_allocations');
    }
};
