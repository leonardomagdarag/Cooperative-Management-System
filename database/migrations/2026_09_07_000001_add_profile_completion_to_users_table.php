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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('profile_completion_required')
                ->default(false)
                ->after('special_loan_eligible');
            $table->timestamp('profile_completed_at')
                ->nullable()
                ->after('profile_completion_required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['profile_completion_required', 'profile_completed_at']);
        });
    }
};
