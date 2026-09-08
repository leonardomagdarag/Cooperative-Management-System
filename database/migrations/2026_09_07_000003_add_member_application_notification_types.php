<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPES = [
        'announcement',
        'loan_due',
        'loan_approved',
        'loan_rejected',
        'loan_payment',
        'security_alert',
        'email_verification',
        'otp',
        'general',
        'member_application',
        'member_approved',
        'member_declined',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', self::TYPES)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [
                'announcement',
                'loan_due',
                'loan_approved',
                'loan_rejected',
                'loan_payment',
                'security_alert',
                'email_verification',
                'otp',
                'general',
            ])->change();
        });
    }
};
