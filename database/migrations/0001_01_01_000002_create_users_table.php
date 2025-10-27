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
        Schema::create('users', function (Blueprint $table) {
            $table->string('user_id', 16)->primary();
            $table->string('personal_data_id', 16)->nullable();
            //$table->string('contact_id', 16)->nullable(); // foreign key for getting email or phone
            $table->string('email')->unique();
            $table->string('password', 255);
            $table->rememberToken();
            $table->enum('role', [
                'developer',
                'admin',
                'member',
                'officer',
                'inactive',
                'terminated'
            ])->default('inactive');
            $table->timestamps();

            $table->foreign('personal_data_id')->references('personal_data_id')->on('personal_data');
            //$table->foreign('contact_id')->references('contact_id')->on('contacts');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id', 16)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
