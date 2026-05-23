<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('officer_permission_overrides')) {
            return;
        }

        Schema::create('officer_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->string('effect', 16);
            $table->timestamps();

            $table->unique(['user_id', 'permission']);
            $table->index(['user_id', 'effect']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officer_permission_overrides');
    }
};
