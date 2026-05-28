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
        Schema::table('member_ids', function (Blueprint $table) {
            $table->dropColumn('id_photo_path');
            $table->string('national_id_photo', 500)->nullable()->after('national_id');
            $table->string('tin_photo', 500)->nullable()->after('tin');
            $table->string('sss_photo', 500)->nullable()->after('sss');
            $table->string('philhealth_photo', 500)->nullable()->after('philhealth');
            $table->string('barangay_id_photo', 500)->nullable()->after('barangay_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_ids', function (Blueprint $table) {
            $table->dropColumn(['national_id_photo', 'tin_photo', 'sss_photo', 'philhealth_photo', 'barangay_id_photo']);
            $table->string('id_photo_path', 500)->nullable()->after('barangay_id');
        });
    }
};
