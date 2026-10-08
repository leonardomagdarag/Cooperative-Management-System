<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archived_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('reversal_of_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('archived_transactions', function (Blueprint $table) {
            $table->dropIndex(['reversal_of_id']);
            $table->dropColumn('reversal_of_id');
        });
    }
};
