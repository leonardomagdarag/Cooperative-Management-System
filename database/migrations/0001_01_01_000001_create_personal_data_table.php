<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->string('country', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city_municipality', 100)->nullable();
            $table->string('barangay', 100)->nullable();
            $table->string('street', 255)->nullable();
            $table->string('zip_code', 20)->nullable();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('phone_no', 11)->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('email', 255)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('facebook', 255)->nullable();
        });

        Schema::create('employments', function (Blueprint $table) {
            $table->id();
            $table->enum('employment_type', ['full-time', 'part-time', 'self-employed', 'freelancer', 'government', 'private', 'ofw', 'retired', 'unemployed', 'student', 'homemaker'])->nullable();
            $table->string('company', 255)->nullable();
            $table->string('position', 100)->nullable();
            $table->decimal('monthly_income', 15, 2)->nullable();
            $table->string('other_income_source', 255)->nullable();
            $table->decimal('other_income_amount', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('member_ids', function (Blueprint $table) {
            $table->id();
            $table->string('national_id', 64)->nullable();
            $table->string('tin', 64)->nullable();
            $table->string('sss', 64)->nullable();
            $table->string('philhealth', 64)->nullable();
            $table->string('barangay_id', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 16);
            $table->string('drive_file_id', 255);
            $table->string('mime_type', 255);
            $table->string('is_archived', 255);
            $table->timestamps();
        });

        Schema::create('personal_data', function (Blueprint $table) {
            $table->id();
            $table->string('personal_data_number', 20)->unique();
            $table->enum('title', ['Chairman', 'Vice Chairman', 'Secretary', 'Treasurer', 'Loan Officer', 'Member'])->default('Member');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('mid_name', 100)->nullable();
            $table->string('suffix', 50)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birthplace', 255)->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('education', 255)->nullable();
            $table->enum('civil_status', ['single', 'married', 'widowed', 'separated'])->nullable();
            $table->string('religion', 64)->nullable();
            $table->foreignId('address_id')->nullable()->unique()->constrained('addresses');
            $table->foreignId('contact_id')->nullable()->unique()->constrained('contacts');
            $table->foreignId('employment_id')->nullable()->unique()->constrained('employments');
            $table->foreignId('member_id')->nullable()->unique()->constrained('member_ids');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_data');
        Schema::dropIfExists('images');
        Schema::dropIfExists('member_ids');
        Schema::dropIfExists('employments');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('addresses');
    }
};
