<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_data', function (Blueprint $table) {
            $table->string('profile_picture', 500)->nullable()->after('religion');
        });

        Schema::table('member_ids', function (Blueprint $table) {
            $table->string('id_photo_path', 500)->nullable()->after('barangay_id');
        });
    }

    public function down(): void
    {
        Schema::table('personal_data', function (Blueprint $table) {
            $table->dropColumn('profile_picture');
        });

        Schema::table('member_ids', function (Blueprint $table) {
            $table->dropColumn('id_photo_path');
        });
    }
};
