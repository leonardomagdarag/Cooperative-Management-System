<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_ids', function (Blueprint $table) {
            $table->string('proof_of_registration_path', 500)->nullable()->after('barangay_id_photo');
            $table->string('other_document_path', 500)->nullable()->after('proof_of_registration_path');
        });
    }

    public function down(): void
    {
        Schema::table('member_ids', function (Blueprint $table) {
            $table->dropColumn(['proof_of_registration_path', 'other_document_path']);
        });
    }
};
