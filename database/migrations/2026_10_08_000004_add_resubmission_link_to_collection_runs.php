<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_runs', function (Blueprint $table) {
            $table->foreignId('resubmission_of_id')
                ->nullable()
                ->unique()
                ->constrained('collection_runs')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collection_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resubmission_of_id');
        });
    }
};
