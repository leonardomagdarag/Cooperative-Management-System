<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('current_session_id');
            $table->string('pending_session_id');
            $table->string('device_label');
            $table->string('ip_address', 45)->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'expires_at']);
            $table->unique('pending_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_handoffs');
    }
};
