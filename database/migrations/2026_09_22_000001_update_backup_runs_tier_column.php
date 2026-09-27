<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The old tier values map to the fixed job names: the old on-server tier
     * becomes "daily", the old Google Drive tier becomes the "offsite" job.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE backup_runs ALTER COLUMN tier TYPE VARCHAR(20)');
        } else {
            Schema::table('backup_runs', function (Blueprint $table) {
                $table->string('tier', 20)->change();
            });
        }

        DB::table('backup_runs')->where('tier', 'local')->update(['tier' => 'daily']);
        DB::table('backup_runs')->where('tier', 'cloud')->update(['tier' => 'offsite']);
    }

    public function down(): void
    {
        DB::table('backup_runs')->where('tier', 'daily')->update(['tier' => 'local']);
        DB::table('backup_runs')->where('tier', 'offsite')->update(['tier' => 'cloud']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE backup_runs ALTER COLUMN tier TYPE VARCHAR(20)');
        } else {
            Schema::table('backup_runs', function (Blueprint $table) {
                $table->string('tier', 20)->change();
            });
        }
    }
};
