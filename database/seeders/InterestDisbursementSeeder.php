<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Services\InterestService;
use App\Support\FinancialSettings;
use Illuminate\Database\Seeder;

/**
 * Credits the month's interest on member savings and share capital.
 *
 * The board's standing rates are applied first (FinancialSettingSeeder ships
 * both at 0 because they are high-security settings a site has to opt into) and
 * then handed to the real InterestService, so the seed produces the same batch
 * rows, per-member items and `interest` ledger entries the scheduled run does.
 *
 * InterestService is itself idempotent per (type, month): a re-seed finds the
 * existing batch and returns before it writes anything, so balances and counts
 * hold still.
 */
class InterestDisbursementSeeder extends Seeder
{
    private const SAVINGS_RATE = 2.5;

    private const SHARE_CAPITAL_RATE = 4.0;

    public function run(): void
    {
        SystemSetting::set('financial_savings_interest_rate', self::SAVINGS_RATE, 'float', 'financial');
        SystemSetting::set('financial_share_capital_interest_rate', self::SHARE_CAPITAL_RATE, 'float', 'financial');
        SystemSetting::set('financial_savings_interest_interval', 'monthly', 'string', 'financial');
        SystemSetting::set('financial_share_capital_interest_interval', 'monthly', 'string', 'financial');

        $service = app(InterestService::class);

        foreach (FinancialSettings::ACCOUNT_TYPES as $accountType) {
            if (FinancialSettings::rate($accountType) <= 0) {
                continue;
            }

            $batch = $service->disburse($accountType, null, 'scheduled');

            $this->command?->info(sprintf(
                'Interest (%s): %d member(s), ₱%s at %s%% p.a.',
                $accountType,
                $batch->member_count,
                number_format((float) $batch->total_amount, 2),
                rtrim(rtrim(number_format((float) $batch->rate, 4, '.', ''), '0'), '.'),
            ));
        }
    }
}
