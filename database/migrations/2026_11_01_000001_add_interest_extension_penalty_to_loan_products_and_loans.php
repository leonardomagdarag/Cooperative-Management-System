<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->boolean('interest_extension_penalty')->default(false);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->boolean('interest_extension_penalty')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('interest_extension_penalty');
        });

        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn('interest_extension_penalty');
        });
    }
};
