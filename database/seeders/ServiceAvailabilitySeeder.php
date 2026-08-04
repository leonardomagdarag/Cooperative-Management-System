<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class ServiceAvailabilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // DEPOSIT (5 toggles)
            [
                'key' => 'service_deposit_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Deposits Enabled',
                'description' => 'Global enable/disable for deposit service',
                'is_editable' => true,
            ],
            [
                'key' => 'service_deposit_member_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Deposits - Members',
                'description' => 'Allow members to use deposit service',
                'is_editable' => true,
            ],
            [
                'key' => 'service_deposit_officer_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Deposits - Officers',
                'description' => 'Allow officers to use deposit service for members',
                'is_editable' => true,
            ],
            [
                'key' => 'service_deposit_counter_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Deposits - Counter (OTC)',
                'description' => 'Allow over-the-counter deposits',
                'is_editable' => true,
            ],
            [
                'key' => 'service_deposit_online_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Deposits - Online Payment',
                'description' => 'Allow online payment deposits',
                'is_editable' => true,
            ],

            // WITHDRAWAL (6 toggles)
            [
                'key' => 'service_withdrawal_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Withdrawals Enabled',
                'description' => 'Global enable/disable for withdrawal service',
                'is_editable' => true,
            ],
            [
                'key' => 'service_withdrawal_member_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Withdrawals - Members',
                'description' => 'Allow members to use withdrawal service',
                'is_editable' => true,
            ],
            [
                'key' => 'service_withdrawal_officer_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Withdrawals - Officers',
                'description' => 'Allow officers to use withdrawal service for members',
                'is_editable' => true,
            ],
            [
                'key' => 'service_withdrawal_counter_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Withdrawals - Counter (OTC)',
                'description' => 'Allow over-the-counter withdrawals',
                'is_editable' => true,
            ],
            [
                'key' => 'service_withdrawal_online_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Withdrawals - Online Payment',
                'description' => 'Allow online payment withdrawals',
                'is_editable' => true,
            ],
            [
                'key' => 'service_withdrawal_bank_transfer_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Withdrawals - Bank Transfer',
                'description' => 'Allow bank transfer withdrawals',
                'is_editable' => true,
            ],

            // LOAN PAYMENT (5 toggles)
            [
                'key' => 'service_loan_payment_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Loan Payments Enabled',
                'description' => 'Global enable/disable for loan payment service',
                'is_editable' => true,
            ],
            [
                'key' => 'service_loan_payment_member_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Loan Payments - Members',
                'description' => 'Allow members to use loan payment service',
                'is_editable' => true,
            ],
            [
                'key' => 'service_loan_payment_officer_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Loan Payments - Officers',
                'description' => 'Allow officers to use loan payment service for members',
                'is_editable' => true,
            ],
            [
                'key' => 'service_loan_payment_counter_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Loan Payments - Counter (OTC)',
                'description' => 'Allow over-the-counter loan payments',
                'is_editable' => true,
            ],
            [
                'key' => 'service_loan_payment_online_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Loan Payments - Online Payment',
                'description' => 'Allow online loan payments',
                'is_editable' => true,
            ],

            // LOAN APPLICATION (3 toggles)
            [
                'key' => 'service_loan_application_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Loan Applications Enabled',
                'description' => 'Global enable/disable for loan application service',
                'is_editable' => true,
            ],
            [
                'key' => 'service_loan_application_member_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Loan Applications - Members',
                'description' => 'Allow members to apply for loans',
                'is_editable' => true,
            ],
            [
                'key' => 'service_loan_application_officer_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'service_availability',
                'label' => 'Loan Applications - Officers',
                'description' => 'Allow officers to process loan applications for members',
                'is_editable' => true,
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('Service availability settings seeded successfully!');
    }
}
