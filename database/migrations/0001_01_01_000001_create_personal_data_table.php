<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->string('address_id', 16)->primary();
            $table->string('country', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city_municipality', 100)->nullable();
            $table->string('barangay', 100)->nullable();
            $table->string('street', 255)->nullable();
            $table->string('zip_code', 20)->nullable();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->string('contact_id', 16)->primary();
            $table->string('phone_no', 11)->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('email', 255)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
        });

        Schema::create('personal_data', function (Blueprint $table) {
            $table->string('personal_data_id', 16)->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('mid_name', 100)->nullable();
            $table->string('suffix', 50)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('address_id', 16)->nullable();
            $table->string('contact_id', 16)->nullable();

            $table->foreign('address_id')->references('address_id')->on('addresses');
            $table->foreign('contact_id')->references('contact_id')->on('contacts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('personal_data');
    }
};
