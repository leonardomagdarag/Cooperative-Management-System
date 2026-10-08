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
        Schema::create('collection_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_run_id')->constrained('collection_runs')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('users');
            $table->foreignId('recorded_by')->constrained('users');
            $table->foreignId('loan_id')->constrained('loans');
            $table->foreignId('account_id')->constrained('accounts');
            $table->decimal('amount', 15, 2);
            $table->text('note')->nullable();
            $table->boolean('visited')->default(false);
            $table->enum('outcome', ['pending', 'applied', 'failed'])->default('pending');
            $table->text('failure_reason')->nullable();
            $table->foreignId('transaction_id')->nullable()->unique()->constrained('transactions')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_run_items');
    }
};
