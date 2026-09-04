<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // GENERAL SETTINGS
            [
                'key' => 'site_name',
                'value' => 'Settlers Credit Cooperative',
                'type' => 'text',
                'group' => 'general',
                'label' => 'Site Name',
                'description' => 'The name of the cooperative',
            ],
            [
                'key' => 'site_tagline',
                'value' => 'Building Financial Strength Together',
                'type' => 'text',
                'group' => 'general',
                'label' => 'Site Tagline',
                'description' => 'Short tagline for the cooperative',
            ],
            [
                'key' => 'maintenance_mode',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'general',
                'label' => 'Maintenance Mode',
                'description' => 'Enable maintenance mode to block access',
            ],
            [
                'key' => 'member_registration_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'general',
                'label' => 'Member Registration',
                'description' => 'Allow new member registration',
            ],

            // LOAN SETTINGS
            [
                'key' => 'loan_max_amount',
                'value' => '500000',
                'type' => 'number',
                'group' => 'loan',
                'label' => 'Maximum Loan Amount',
                'description' => 'Maximum loan amount in PHP',
            ],
            [
                'key' => 'loan_min_amount',
                'value' => '5000',
                'type' => 'number',
                'group' => 'loan',
                'label' => 'Minimum Loan Amount',
                'description' => 'Minimum loan amount in PHP',
            ],
            [
                'key' => 'loan_interest_rate_regular',
                'value' => '2.5',
                'type' => 'decimal',
                'group' => 'loan',
                'label' => 'Regular Loan Interest Rate (%)',
                'description' => 'Interest rate for regular loans',
            ],
            [
                'key' => 'loan_interest_rate_emergency',
                'value' => '1.5',
                'type' => 'decimal',
                'group' => 'loan',
                'label' => 'Emergency Loan Interest Rate (%)',
                'description' => 'Interest rate for emergency loans',
            ],
            [
                'key' => 'loan_approval_require_otp',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'loan',
                'label' => 'Require OTP for Approval',
                'description' => 'Require manager OTP for loan approval',
            ],
            [
                'key' => 'loan_due_reminder_days',
                'value' => json_encode([7, 3, 1]),
                'type' => 'json',
                'group' => 'loan',
                'label' => 'Due Reminder Days',
                'description' => 'Days before due date to send reminders',
            ],

            // NOTIFICATION SETTINGS
            [
                'key' => 'notification_email_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'notification',
                'label' => 'Email Notifications',
                'description' => 'Enable email notifications',
            ],
            [
                'key' => 'notification_sms_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'notification',
                'label' => 'SMS Notifications',
                'description' => 'Enable SMS notifications',
            ],
            [
                'key' => 'notification_retention_days',
                'value' => '30',
                'type' => 'number',
                'group' => 'notification',
                'label' => 'Notification Retention Days',
                'description' => 'Days to keep read notifications',
            ],

            // SECURITY SETTINGS
            [
                'key' => 'security_failed_login_attempts',
                'value' => '3',
                'type' => 'number',
                'group' => 'security',
                'label' => 'Failed Login Attempts',
                'description' => 'Number of failed attempts before alert',
            ],
            [
                'key' => 'security_session_timeout',
                'value' => '120',
                'type' => 'number',
                'group' => 'security',
                'label' => 'Session Timeout (minutes)',
                'description' => 'Automatic logout after inactivity',
            ],
            [
                'key' => 'security_password_min_length',
                'value' => '8',
                'type' => 'number',
                'group' => 'security',
                'label' => 'Minimum Password Length',
                'description' => 'Minimum characters for password',
            ],
            [
                'key' => 'security_require_strong_password',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'security',
                'label' => 'Require Strong Password',
                'description' => 'Require uppercase, numbers, symbols',
            ],
            [
                'key' => 'security_otp_expiry_minutes',
                'value' => '10',
                'type' => 'number',
                'group' => 'security',
                'label' => 'OTP Expiry (minutes)',
                'description' => 'Validity period of one-time verification codes',
            ],
            [
                'key' => 'security_otp_max_attempts',
                'value' => '5',
                'type' => 'number',
                'group' => 'security',
                'label' => 'OTP Maximum Attempts',
                'description' => 'Maximum failed OTP attempts before challenge is invalidated',
            ],
            [
                'key' => 'security_trusted_device_days',
                'value' => '90',
                'type' => 'number',
                'group' => 'security',
                'label' => 'Trusted Device Duration (days)',
                'description' => 'How long a verified device remains trusted',
            ],
            [
                'key' => 'security_withdrawal_otp_threshold',
                'value' => '2000',
                'type' => 'number',
                'group' => 'security',
                'label' => 'Withdrawal OTP Threshold',
                'description' => 'Require OTP when approving withdrawals at or above this amount',
            ],

            // PAYMENT SETTINGS
            [
                'key' => 'payment_online_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment',
                'label' => 'Online Payments',
                'description' => 'Enable online payment methods',
            ],
            [
                'key' => 'payment_methods',
                'value' => json_encode(['gcash', 'paymaya', 'bank_transfer']),
                'type' => 'json',
                'group' => 'payment',
                'label' => 'Payment Methods',
                'description' => 'Available payment methods',
            ],

            // BUSINESS SETTINGS
            [
                'key' => 'business_hours_weekday',
                'value' => '08:00-17:00',
                'type' => 'text',
                'group' => 'business',
                'label' => 'Weekday Business Hours',
                'description' => 'Format: HH:MM-HH:MM',
            ],
            [
                'key' => 'business_hours_saturday',
                'value' => '08:00-12:00',
                'type' => 'text',
                'group' => 'business',
                'label' => 'Saturday Business Hours',
                'description' => 'Format: HH:MM-HH:MM',
            ],
            [
                'key' => 'business_closed_days',
                'value' => json_encode(['sunday']),
                'type' => 'json',
                'group' => 'business',
                'label' => 'Closed Days',
                'description' => 'Days when office is closed',
            ],

            // BACKUP SETTINGS
            [
                'key' => 'backup_encryption_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'backup',
                'label' => 'Encrypt Backups',
                'description' => 'Encrypt all backup archives with BACKUP_ARCHIVE_PASSWORD',
            ],
            [
                'key' => 'backup_local_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'backup',
                'label' => 'On-Server Backups Enabled',
                'description' => 'Run scheduled backups to the server storage',
            ],
            [
                'key' => 'backup_local_schedule',
                'value' => '0 2 * * *',
                'type' => 'text',
                'group' => 'backup',
                'label' => 'On-Server Schedule',
                'description' => 'Cron expression (5 fields) for the on-server tier',
            ],
            [
                'key' => 'backup_local_scope',
                'value' => 'both',
                'type' => 'text',
                'group' => 'backup',
                'label' => 'On-Server Scope',
                'description' => 'What to back up: db, files or both',
            ],
            [
                'key' => 'backup_local_retention_days',
                'value' => '7',
                'type' => 'number',
                'group' => 'backup',
                'label' => 'On-Server Retention Days',
                'description' => 'Days to keep on-server backups',
            ],
            [
                'key' => 'backup_cloud_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'backup',
                'label' => 'Cloud Backups Enabled',
                'description' => 'Run scheduled backups to Google Drive',
            ],
            [
                'key' => 'backup_cloud_schedule',
                'value' => '0 3 * * 1',
                'type' => 'text',
                'group' => 'backup',
                'label' => 'Cloud Schedule',
                'description' => 'Cron expression (5 fields) for the cloud tier',
            ],
            [
                'key' => 'backup_cloud_scope',
                'value' => 'both',
                'type' => 'text',
                'group' => 'backup',
                'label' => 'Cloud Scope',
                'description' => 'What to back up: db, files or both',
            ],
            [
                'key' => 'backup_cloud_retention_days',
                'value' => '30',
                'type' => 'number',
                'group' => 'backup',
                'label' => 'Cloud Retention Days',
                'description' => 'Days to keep cloud backups',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('System settings seeded successfully!');
    }
}
