<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('privileged_action_requests', function (Blueprint $table) {
            $table->string('active_key')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('privileged_action_requests', function (Blueprint $table) {
            $table->dropUnique(['active_key']);
            $table->dropColumn('active_key');
        });
    }
};
