<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL: expand the type enum to include 'event'.
        // SQLite (used in tests) stores columns as TEXT so no ALTER needed.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE announcements MODIFY COLUMN `type` ENUM('news', 'announcement', 'event') NOT NULL DEFAULT 'announcement'");
        }

        Schema::table('announcements', function (Blueprint $table) {
            $table->date('event_date')->nullable()->after('category');
            $table->string('event_time', 50)->nullable()->after('event_date');
            $table->string('event_location', 255)->nullable()->after('event_time');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['event_date', 'event_time', 'event_location']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE announcements MODIFY COLUMN `type` ENUM('news', 'announcement') NOT NULL DEFAULT 'announcement'");
        }
    }
};
