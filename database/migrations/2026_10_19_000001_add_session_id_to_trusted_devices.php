<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('current_session_id')->nullable()->index();
        });

        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->string('session_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
            $table->dropColumn('session_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['current_session_id']);
            $table->dropColumn('current_session_id');
        });
    }
};
