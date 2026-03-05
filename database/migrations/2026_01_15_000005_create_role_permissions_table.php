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
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role'); // admin, manager, member, loan, secretary, treasurer
            $table->string('section'); // dashboard, officers, members, etc.
            $table->json('permissions')->nullable(); // ['view', 'create', 'edit', 'delete', 'approve']
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['role', 'section']);
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
