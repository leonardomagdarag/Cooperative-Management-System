<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->enum('account_type', ['savings', 'share-capital'])->default('savings');
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('loan_type', 50);
            $table->decimal('loan_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->integer('term_months')->nullable();
            $table->enum('payment_frequency', ['weekly', 'bi-weekly', 'monthly', 'quarterly', 'annually'])->default('monthly');
            $table->enum('loan_status', ['pending', 'approved', 'active', 'paid_off'])->default('pending');
            $table->dateTime('applied_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 20)->unique();
            $table->enum('transaction_type', ['deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'interest', 'fee'])->default('deposit');
            $table->foreignId('account_id')->nullable()->constrained('accounts');
            $table->foreignId('loan_id')->nullable()->constrained('loans');
            $table->decimal('amount', 15, 2);
            $table->dateTime('transaction_date')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestamps();

            // Indexes for common queries
            $table->index('transaction_type');
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('accounts');
    }
};
