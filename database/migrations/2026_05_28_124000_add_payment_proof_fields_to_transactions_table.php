<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('proof_of_payment_path')->nullable()->after('status');
            $table->timestamp('proof_of_payment_uploaded_at')->nullable()->after('proof_of_payment_path');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['proof_of_payment_path', 'proof_of_payment_uploaded_at']);
        });
    }
};
