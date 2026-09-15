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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('payment_gateway', 50)->nullable()->after('transaction_mode');
            $table->string('payment_method_used', 50)->nullable()->after('payment_gateway');
            $table->string('paymongo_checkout_session_id', 100)->nullable()->after('payment_method_used');
            $table->string('paymongo_payment_intent_id', 100)->nullable()->after('paymongo_checkout_session_id');
            $table->string('paymongo_payment_id', 100)->nullable()->after('paymongo_payment_intent_id');
            $table->decimal('paymongo_fee', 15, 2)->nullable()->after('paymongo_payment_id');

            $table->index('paymongo_checkout_session_id');
            $table->index('paymongo_payment_intent_id');
            $table->index('paymongo_payment_id');
            $table->index('payment_gateway');
        });

        Schema::create('paymongo_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 150)->unique();
            $table->string('event_type', 100);
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('event_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paymongo_webhook_events');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['paymongo_checkout_session_id']);
            $table->dropIndex(['paymongo_payment_intent_id']);
            $table->dropIndex(['paymongo_payment_id']);
            $table->dropIndex(['payment_gateway']);

            $table->dropColumn([
                'payment_gateway',
                'payment_method_used',
                'paymongo_checkout_session_id',
                'paymongo_payment_intent_id',
                'paymongo_payment_id',
                'paymongo_fee',
            ]);
        });
    }
};
