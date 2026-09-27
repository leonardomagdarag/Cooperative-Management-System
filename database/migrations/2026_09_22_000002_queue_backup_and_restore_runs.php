<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_runs', function (Blueprint $table) {
            // Expand the status contract before workers begin writing queued rows.
            $table->string('status', 20)->default('queued')->change();
        });

        Schema::create('restore_runs', function (Blueprint $table) {
            $table->id();
            $table->string('tier', 20);
            $table->string('path', 500);
            $table->string('scope', 10);
            $table->string('status', 20)->default('queued');
            $table->text('message')->nullable();
            $table->string('rollback_path', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_runs');
        Schema::table('backup_runs', fn (Blueprint $table) => $table->string('status', 20)->change());
    }
};
