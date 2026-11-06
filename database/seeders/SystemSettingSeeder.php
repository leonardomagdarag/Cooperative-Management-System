<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * System settings.
 *
 * Only keys the application actually reads are seeded here. The per-service
 * toggles live in ServiceAvailabilitySeeder, and the loan terms are not
 * settings at all: they are rows in the loan products table, so nothing in
 * this file may contradict that catalogue.
 */
class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ── Registration ──────────────────────────────────────────────
            // ── Login ─────────────────────────────────────────────────────
            [
                'key' => 'service_login_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Login (Global)',
                'description' => 'Allow anyone to sign in',
            ],
            [
                'key' => 'service_login_member_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Login (Member)',
                'description' => 'Allow members to sign in',
            ],
            [
                'key' => 'service_login_officer_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Login (Officer)',
                'description' => 'Allow officers to sign in',
            ],

            // ── Security ──────────────────────────────────────────────────
            [
                'key' => 'security_otp_expiry_minutes',
                'value' => '10',
                'type' => 'number',
                'group' => 'security',
                'label' => 'OTP Expiry (minutes)',
                'description' => 'How long a one-time code stays valid',
            ],
            [
                'key' => 'security_otp_max_attempts',
                'value' => '5',
                'type' => 'number',
                'group' => 'security',
                'label' => 'OTP Maximum Attempts',
                'description' => 'Failed entries before the code is invalidated',
            ],
            [
                'key' => 'security_trusted_device_days',
                'value' => '90',
                'type' => 'number',
                'group' => 'security',
                'label' => 'Trusted Device Duration (days)',
                'description' => 'How long a verified device stays trusted',
            ],
            [
                'key' => 'security_withdrawal_otp_threshold',
                'value' => '2000',
                'type' => 'number',
                'group' => 'security',
                'label' => 'Withdrawal OTP Threshold',
                'description' => 'A withdrawal of this amount or more needs a one-time code before it is released',
            ],

            // ── Online payments ───────────────────────────────────────────
            [
                'key' => 'paymongo_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'payment',
                'label' => 'Online Payments',
                'description' => 'The cooperative settles deposits and loan payments at the counter, so this is off until a payment gateway is configured',
            ],
            [
                'key' => 'paymongo_mode',
                'value' => 'sandbox',
                'type' => 'text',
                'group' => 'payment',
                'label' => 'Gateway Mode',
                'description' => 'sandbox or live',
            ],

            // ── Backups ───────────────────────────────────────────────────
            [
                'key' => 'backup_encryption_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'backup',
                'label' => 'Encrypt Backups',
                'description' => 'Encrypt every backup archive with BACKUP_ARCHIVE_PASSWORD',
            ],
            [
                'key' => 'backup_daily_time',
                'value' => '03:00',
                'type' => 'text',
                'group' => 'backup',
                'label' => 'Daily Backup Time',
                'description' => 'Time of day when daily and off-site backups are scheduled',
            ],
            [
                'key' => 'backup_storage_warning_percent',
                'value' => '85',
                'type' => 'number',
                'group' => 'backup',
                'label' => 'Backup Storage Warning Threshold',
                'description' => 'Notify administrators when local backup storage usage reaches this percentage',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }

        $archivePassword = SystemSetting::firstOrNew(['key' => 'backup_archive_password']);
        if (! $archivePassword->exists) {
            $archivePassword->fill([
                'value' => (string) (config('backup.backup.password') ?: Str::random(16).'A1'),
                'type' => 'encrypted',
                'group' => 'backup',
                'label' => 'Backup Encryption Password',
                'description' => 'Encrypted password used for backup archives',
                'is_editable' => true,
            ])->save();
        }

        SystemSetting::firstOrCreate(['key' => 'sms_service_enabled'], [
            'value' => '0',
            'type' => 'boolean',
            'group' => 'integrations',
            'label' => 'SMS Service Enabled',
            'description' => 'Globally enable or disable TextBee SMS delivery',
            'is_editable' => true,
        ]);

        $textbeeApiKey = SystemSetting::firstOrNew(['key' => 'textbee_api_key']);
        if (blank($textbeeApiKey->value)) {
            $textbeeApiKey->fill([
                'value' => (string) config('services.textbee.api_key', ''),
                'type' => 'string',
                'group' => 'integrations',
                'label' => 'TextBee API Key',
                'description' => 'API key used by the TextBee SMS integration',
                'is_editable' => true,
            ])->save();
        }

        $this->command?->info('Seeded '.(count($settings) + 2).' system settings.');
    }
}
