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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Setting identifier
            $table->text('value'); // Setting value
            $table->string('type')->default('text'); // text, number, boolean, json, array
            $table->string('group')->default('general'); // general, security, email, loan, notification, etc.
            $table->string('label'); // Human-readable label
            $table->text('description')->nullable();
            $table->text('options')->nullable(); // JSON for dropdown/select options
            $table->boolean('is_editable')->default(true);
            $table->boolean('requires_restart')->default(false); // If changing requires app restart
            $table->timestamps();

            $table->index('group');
            $table->index('key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
