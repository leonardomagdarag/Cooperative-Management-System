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
        Schema::create('collection_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_number')->unique();
            $table->foreignId('loan_officer_id')->constrained('users');
            $table->date('collection_date');
            $table->foreignId('branch_id')->constrained('branches');
            $table->enum('status', ['draft', 'submitted', 'in_progress', 'approved', 'rejected', 'partially_applied', 'voided'])->default('draft');
            $table->integer('current_stage')->default(0);
            $table->json('stages_snapshot')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->integer('item_count')->default(0);
            $table->boolean('is_late')->default(false);
            $table->timestamp('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('orphaned_at')->nullable();
            $table->text('voided_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_runs');
    }
};
