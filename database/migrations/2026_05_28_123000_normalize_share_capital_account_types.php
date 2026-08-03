<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')
            ->where('account_type', 'share_capital')
            ->update(['account_type' => 'share-capital']);
    }

    public function down(): void
    {
        // Irreversible normalization migration.
    }
};
