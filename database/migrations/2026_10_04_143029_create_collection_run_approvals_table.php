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
        Schema::create('collection_run_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_run_id')->constrained('collection_runs')->onDelete('cascade');
            $table->integer('stage_index');
            $table->string('stage_key');
            $table->string('stage_label');
            $table->foreignId('decided_by')->constrained('users');
            $table->timestamp('decided_at');
            $table->enum('action', ['approved', 'rejected']);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['collection_run_id', 'stage_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_run_approvals');
    }
};
