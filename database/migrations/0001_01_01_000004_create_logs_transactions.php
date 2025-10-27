<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('transactions', function (Blueprint $table) {
            $table->string('transaction_id', 16)->primary();
            $table->string('account_id', 16)->nullable();
            $table->string('processed_by', 16)->nullable();
            $table->enum('transaction_type', ['deposit', 'withdrawal', 'loan_payment', 'transfer']);
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->foreign('account_id')->references('account_id')->on('accounts');
        });

        Schema::create('logs', function (Blueprint $table) {
            $table->string('log_id', 16)->primary();
            $table->string('user_id', 16)->nullable();
            $table->string('activity', 255);
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users');
        });
    }

    public function down(): void {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('logs');
    }
};
