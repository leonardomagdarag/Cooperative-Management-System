<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('user_number', 20)->unique();
            $table->foreignId('personal_data_id')->nullable()->constrained('personal_data');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->rememberToken();
            $table->enum('role', [
                'developer',
                'admin',
                'manager',
                'member',
                'loan',
                'accountant',
                'secretary',
                'inactive',
                'terminated',
            ])->default('member');
            $table->enum('account_status', ['active', 'inactive', 'terminated'])->default('active');
            $table->boolean('is_officer')->default(false)->comment('Indicates if user is a system officer (staff) with custom permissions');
            $table->boolean('special_loan_eligible')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at', 'users_created_at_index');
            $table->index('account_status', 'users_account_status_index');
            $table->index('is_officer', 'users_is_officer_index');
            $table->index('personal_data_id', 'users_personal_data_id_index');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('mid_name', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('relationship', 100);
            $table->string('phone_no', 11)->nullable();
            $table->string('street', 255)->nullable();
            $table->string('barangay', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('activity', 255);
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs');
        Schema::dropIfExists('beneficiaries');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
