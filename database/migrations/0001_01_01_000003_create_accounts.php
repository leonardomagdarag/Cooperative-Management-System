<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('accounts', function (Blueprint $table) {
            $table->string('account_id', 16)->primary();
            $table->string('user_id', 16)->nullable();
            $table->enum('account_type', ['savings', 'checking', 'loan'])->default('savings');
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users');
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->string('loan_id', 16)->primary();
            $table->string('account_id', 16)->nullable();
            $table->string('loan_type', 50);
            $table->decimal('loan_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->enum('payment_frequency', ['weekly', 'monthly', 'quarterly', 'annually'])->default('monthly');
            $table->enum('loan_status', ['active', 'paid_off', 'defaulted', 'closed'])->default('active');
            $table->dateTime('approved_at')->nullable();
            $table->string('approved_by', 16)->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('account_id')->on('accounts');
        });

        Schema::create('deposits', function (Blueprint $table) {
            $table->string('deposit_id', 16)->primary();
            $table->string('account_id', 16)->nullable();
            $table->decimal('deposit_amount', 15, 2);
            $table->dateTime('deposit_date')->nullable();
            $table->string('recorded_by', 16)->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('account_id')->on('accounts');
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->string('withdrawal_id', 16)->primary();
            $table->string('account_id', 16)->nullable();
            $table->decimal('withdrawal_amount', 15, 2);
            $table->string('recorded_by', 16)->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('account_id')->on('accounts');
        });

        Schema::create('loan_payments', function (Blueprint $table) {
            $table->string('payment_id', 16)->primary();
            $table->string('loan_id', 16)->nullable();
            $table->decimal('payment_amount', 15, 2);
            $table->timestamps();

            $table->foreign('loan_id')->references('loan_id')->on('loans');
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('deposits');
        Schema::dropIfExists('withdrawals');
    }
};
