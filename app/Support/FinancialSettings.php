<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Services\CountersignService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for the financial-accounts settings that govern
 * member interest crediting and withdrawal limits.
 *
 * These keys are high-security: they are only ever written through the
 * countersign workflow ({@see CountersignService}), using
 * {@see self::EDITABLE_KEYS} as the whitelist.
 */
class FinancialSettings
{
    public const ACCOUNT_TYPES = ['savings', 'share-capital'];

    public const INTERVALS = ['daily', 'monthly', 'yearly'];

    /**
     * Keys the financial-accounts endpoint may read/write. Includes the
     * relocation of the withdrawal card out of the Service Availability tab.
     *
     * @var array<int, string>
     */
    public const EDITABLE_KEYS = [
        'financial_savings_interest_rate',
        'financial_savings_interest_interval',
        'financial_savings_min_balance_to_earn',
        'financial_share_capital_interest_rate',
        'financial_share_capital_interest_interval',
        'financial_share_capital_min_balance_to_earn',
        'financial_withdrawal_min_amount',
        'financial_withdrawal_max_amount',
        'financial_withdrawal_savings_enabled',
        'financial_withdrawal_share_capital_enabled',
        'financial_interest_min_payout',
        'financial_interest_notify_members',
        // Withdrawal card relocated from Service Availability.
        'service_withdrawal_enabled',
        'service_withdrawal_member_enabled',
        'service_withdrawal_officer_enabled',
        'service_withdrawal_counter_enabled',
        'service_withdrawal_online_enabled',
        'service_withdrawal_bank_transfer_enabled',
    ];

    public static function keySegment(string $accountType): string
    {
        return str_replace('-', '_', $accountType);
    }

    public static function rate(string $accountType): float
    {
        return (float) SystemSetting::get('financial_'.self::keySegment($accountType).'_interest_rate', 0);
    }

    public static function interval(string $accountType): string
    {
        $value = (string) SystemSetting::get('financial_'.self::keySegment($accountType).'_interest_interval', 'monthly');

        return in_array($value, self::INTERVALS, true) ? $value : 'monthly';
    }

    public static function minBalance(string $accountType): float
    {
        return (float) SystemSetting::get('financial_'.self::keySegment($accountType).'_min_balance_to_earn', 0);
    }

    public static function lastCreditedAt(string $accountType): ?CarbonInterface
    {
        $value = SystemSetting::get('financial_'.self::keySegment($accountType).'_last_credited_at');

        return $value ? Carbon::parse($value) : null;
    }

    public static function stampLastCredited(string $accountType, CarbonInterface $at): void
    {
        SystemSetting::set(
            'financial_'.self::keySegment($accountType).'_last_credited_at',
            $at->format('Y-m-d H:i:s'),
            'string',
            'financial'
        );
    }

    public static function minPayout(): float
    {
        return (float) SystemSetting::get('financial_interest_min_payout', 0);
    }

    public static function notifyMembers(): bool
    {
        return (bool) SystemSetting::get('financial_interest_notify_members', false);
    }

    public static function withdrawalMin(): float
    {
        return (float) SystemSetting::get('financial_withdrawal_min_amount', 100);
    }

    public static function withdrawalMax(): float
    {
        return (float) SystemSetting::get('financial_withdrawal_max_amount', 0);
    }

    public static function savingsWithdrawalEnabled(): bool
    {
        return (bool) SystemSetting::get('financial_withdrawal_savings_enabled', true);
    }

    public static function shareCapitalWithdrawalEnabled(): bool
    {
        return (bool) SystemSetting::get('financial_withdrawal_share_capital_enabled', true);
    }

    /**
     * Convert the nominal annual rate to the divisor for the crediting interval.
     */
    public static function intervalDivisor(string $interval): int
    {
        return match ($interval) {
            'daily' => 365,
            'yearly' => 1,
            default => 12,
        };
    }

    /**
     * Period bucket label used for idempotency, e.g. `2026-10-16`, `2026-10`, `2026`.
     */
    public static function periodLabel(string $interval, CarbonInterface $at): string
    {
        return match ($interval) {
            'daily' => $at->format('Y-m-d'),
            'yearly' => $at->format('Y'),
            default => $at->format('Y-m'),
        };
    }
}
