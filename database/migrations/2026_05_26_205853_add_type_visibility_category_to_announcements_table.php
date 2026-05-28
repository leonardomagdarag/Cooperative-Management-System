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
        Schema::table('announcements', function (Blueprint $table) {
            $table->enum('type', ['news', 'announcement'])->default('announcement')->after('content');
            $table->enum('visibility', ['public', 'cooperative'])->default('cooperative')->after('type');
            $table->string('category', 100)->nullable()->after('visibility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['type', 'visibility', 'category']);
        });
    }
};
