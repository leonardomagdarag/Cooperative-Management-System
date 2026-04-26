<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_status', ['active', 'inactive', 'terminated'])
                ->default('active')
                ->after('role');
        });

        // Backfill lifecycle status from legacy role values.
        DB::table('users')
            ->where('role', 'terminated')
            ->update(['account_status' => 'terminated']);

        DB::table('users')
            ->where('role', 'inactive')
            ->update(['account_status' => 'inactive']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_status');
        });
    }
};
