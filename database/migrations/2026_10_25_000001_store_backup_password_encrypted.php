<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('system_settings')->where('key', 'backup_archive_password')->exists()) {
            return;
        }

        $password = (string) (config('backup.backup.password') ?: Str::random(16).'A1');

        DB::table('system_settings')->insert([
            'key' => 'backup_archive_password',
            'value' => Crypt::encryptString($password),
            'type' => 'encrypted',
            'group' => 'backup',
            'label' => 'Backup Encryption Password',
            'description' => 'Encrypted password used for backup archives',
            'is_editable' => true,
            'requires_restart' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'backup_archive_password')->delete();
    }
};
