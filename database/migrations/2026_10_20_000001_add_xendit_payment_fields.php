<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('xendit_session_id', 100)->nullable()->index();
            $table->string('xendit_mode', 10)->nullable();
            $table->string('xendit_payment_id', 100)->nullable()->index();
        });

        Schema::create('xendit_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 100)->unique();
            $table->string('event_type', 100);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xendit_webhook_events');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['xendit_session_id']);
            $table->dropIndex(['xendit_payment_id']);
            $table->dropColumn(['xendit_session_id', 'xendit_mode', 'xendit_payment_id']);
        });
    }
};
