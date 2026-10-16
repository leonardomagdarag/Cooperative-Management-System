<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Support\FinancialSettings;
use Illuminate\Database\Seeder;

/**
 * Financial-accounts settings.
 *
 * These keys are read by {@see FinancialSettings} and are only
 * ever written through the countersign workflow. They are seeded with safe
 * defaults: no interest is credited until an admin sets a rate.
 */
class FinancialSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // ── Savings interest ──────────────────────────────────────────
            [
                'key' => 'financial_savings_interest_rate',
                'value' => '0',
                'type' => 'float',
                'group' => 'financial',
                'label' => 'Savings Interest Rate (annual %)',
                'description' => 'Nominal annual rate credited to member savings accounts.',
            ],
            [
                'key' => 'financial_savings_interest_interval',
                'value' => 'monthly',
                'type' => 'string',
                'group' => 'financial',
                'label' => 'Savings Interest Interval',
                'description' => 'How often savings interest is credited: daily, monthly or yearly.',
            ],
            [
                'key' => 'financial_savings_min_balance_to_earn',
                'value' => '0',
                'type' => 'float',
                'group' => 'financial',
                'label' => 'Savings Minimum Balance to Earn',
                'description' => 'Savings accounts below this balance earn no interest.',
            ],

            // ── Share-capital interest ────────────────────────────────────
            [
                'key' => 'financial_share_capital_interest_rate',
                'value' => '0',
                'type' => 'float',
                'group' => 'financial',
                'label' => 'Share Capital Interest Rate (annual %)',
                'description' => 'Nominal annual rate credited to member share-capital accounts.',
            ],
            [
                'key' => 'financial_share_capital_interest_interval',
                'value' => 'monthly',
                'type' => 'string',
                'group' => 'financial',
                'label' => 'Share Capital Interest Interval',
                'description' => 'How often share-capital interest is credited: daily, monthly or yearly.',
            ],
            [
                'key' => 'financial_share_capital_min_balance_to_earn',
                'value' => '0',
                'type' => 'float',
                'group' => 'financial',
                'label' => 'Share Capital Minimum Balance to Earn',
                'description' => 'Share-capital accounts below this balance earn no interest.',
            ],

            // ── Interest run options ──────────────────────────────────────
            [
                'key' => 'financial_interest_min_payout',
                'value' => '0',
                'type' => 'float',
                'group' => 'financial',
                'label' => 'Minimum Interest Payout',
                'description' => 'Per-member interest below this amount is skipped. 0 credits any amount above zero.',
            ],
            [
                'key' => 'financial_interest_notify_members',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'financial',
                'label' => 'Notify Members on Interest Credit',
                'description' => 'Send each member a notification when interest is credited to their account.',
            ],

            // ── Withdrawal limits ─────────────────────────────────────────
            [
                'key' => 'financial_withdrawal_min_amount',
                'value' => '100',
                'type' => 'float',
                'group' => 'financial',
                'label' => 'Minimum Withdrawal Amount',
                'description' => 'Smallest withdrawal a member may request.',
            ],
            [
                'key' => 'financial_withdrawal_max_amount',
                'value' => '0',
                'type' => 'float',
                'group' => 'financial',
                'label' => 'Maximum Withdrawal Amount',
                'description' => 'Largest withdrawal a member may request. 0 means no cap beyond the available balance.',
            ],
            [
                'key' => 'financial_withdrawal_savings_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'financial',
                'label' => 'Allow Savings Withdrawals',
                'description' => 'When disabled, members cannot withdraw from their savings account.',
            ],
            [
                'key' => 'financial_withdrawal_share_capital_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'financial',
                'label' => 'Allow Share Capital Withdrawals',
                'description' => 'Reserved. Share-capital withdrawals are not available yet, so this toggle has no effect.',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting + ['is_editable' => true]);
        }

        $this->command?->info('Financial account settings seeded successfully!');
    }
}
