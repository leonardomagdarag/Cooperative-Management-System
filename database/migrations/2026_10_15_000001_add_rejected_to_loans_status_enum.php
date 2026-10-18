<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->enum('loan_status', ['pending', 'approved', 'active', 'paid_off', 'rejected'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        // Rejected applications go back to pending before the enum shrinks,
        // otherwise rows would hold a value the column no longer accepts.
        DB::table('loans')->where('loan_status', 'rejected')->update(['loan_status' => 'pending']);

        Schema::table('loans', function (Blueprint $table) {
            $table->enum('loan_status', ['pending', 'approved', 'active', 'paid_off'])
                ->default('pending')
                ->change();
        });
    }
};
