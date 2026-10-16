<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interest_disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('account_type', 20);              // savings | share-capital
            $table->decimal('rate', 8, 4)->default(0);       // annual rate snapshot (%)
            $table->string('interval', 20)->default('monthly');
            $table->string('period_label', 30);              // 2026-10-16 | 2026-10 | 2026
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('completed'); // completed | failed | reversed
            $table->string('trigger', 20)->default('scheduled'); // scheduled | manual
            $table->string('batch_key', 100)->unique();          // idempotency guard
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['account_type', 'created_at']);
            $table->index('status');
        });

        Schema::create('interest_disbursement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interest_disbursement_id')->constrained('interest_disbursements')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('balance_before', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->decimal('rate', 8, 4)->default(0);
            $table->boolean('skipped')->default(false);
            $table->string('skip_reason', 100)->nullable();
            $table->timestamps();

            $table->index(['interest_disbursement_id', 'skipped'], 'interest_items_batch_skipped_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interest_disbursement_items');
        Schema::dropIfExists('interest_disbursements');
    }
};
