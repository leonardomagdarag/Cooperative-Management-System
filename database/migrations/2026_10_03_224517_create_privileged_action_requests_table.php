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
        Schema::create('privileged_action_requests', function (Blueprint $table) {
            $table->id();
            $table->string('action_type');
            $table->enum('status', ['pending', 'approved', 'executing', 'executed', 'rejected', 'expired', 'failed'])->default('pending');
            $table->json('payload');
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->timestamp('started_at')->nullable(); // For atomic claim
            $table->timestamp('executed_at')->nullable();
            $table->json('execution_result')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('privileged_action_requests');
    }
};
