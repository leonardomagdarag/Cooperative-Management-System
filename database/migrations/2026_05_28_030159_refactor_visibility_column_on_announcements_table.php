<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL: change ENUM to TEXT so we can store JSON arrays.
        // SQLite (tests) already treats ENUM as TEXT — no column-type change needed.
        if (DB::getDriverName() === 'mysql') {
            // TEXT columns in MySQL cannot carry a DEFAULT value; the application
            // always provides the value, so no DEFAULT is needed here.
            DB::statement('ALTER TABLE announcements MODIFY COLUMN `visibility` TEXT NOT NULL');
        }

        // Migrate existing string values to JSON-array format.
        DB::table('announcements')
            ->where('visibility', 'cooperative')
            ->update(['visibility' => json_encode(['members'])]);

        DB::table('announcements')
            ->where('visibility', 'public')
            ->update(['visibility' => json_encode(['everyone'])]);
    }

    public function down(): void
    {
        // Reverse: collapse JSON back to old string ENUM values.
        DB::table('announcements')
            ->where('visibility', json_encode(['members']))
            ->update(['visibility' => 'cooperative']);

        DB::table('announcements')
            ->where('visibility', json_encode(['everyone']))
            ->update(['visibility' => 'public']);

        DB::table('announcements')
            ->whereNotIn('visibility', ['public', 'cooperative'])
            ->update(['visibility' => 'cooperative']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE announcements MODIFY COLUMN `visibility` ENUM('public','cooperative') NOT NULL DEFAULT 'cooperative'");
        }
    }
};
