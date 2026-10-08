<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_runs', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->index();
        });

        DB::table('collection_runs')
            ->whereIn('status', ['submitted', 'in_progress', 'approved', 'rejected', 'partially_applied'])
            ->whereNull('submitted_at')
            ->update(['submitted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('collection_runs', function (Blueprint $table) {
            $table->dropIndex(['submitted_at']);
            $table->dropColumn('submitted_at');
        });
    }
};
