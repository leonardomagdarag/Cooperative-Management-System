<?php

namespace App\Services;

use App\Models\Account;
use App\Models\InterestDisbursement;
use App\Models\InterestDisbursementItem;
use App\Models\Transaction;
use App\Models\User;
use App\Support\FinancialSettings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Credits member financial accounts with interest.
 *
 * The rate is a nominal annual figure, prorated by the configured interval
 * (daily = /365, monthly = /12, yearly = /1). Each run produces one
 * {@see InterestDisbursement} batch plus per-member
 * {@see InterestDisbursementItem} lines and a completed `interest`
 * transaction per credited member.
 */
class InterestService
{
    /**
     * `users` carries no name columns — names live on the related
     * `personal_data` row (see {@see User::name}). Only real `users` columns
     * may be listed here: SQLite silently turns an unknown double-quoted
     * identifier into a string literal, so a bogus column name would sail
     * through the SQLite test suite and only blow up on MySQL.
     */
    public const USER_EAGER_COLUMNS = 'id,branch_id,personal_data_id,email';

    /**
     * Whether a credit for the given account type is due right now.
     *
     * Also lazily initialises the "last credited" marker so that the first
     * run after enabling a rate does not immediately pay a full interval.
     */
    public function isDue(string $accountType): bool
    {
        if (FinancialSettings::rate($accountType) <= 0) {
            return false;
        }

        $last = FinancialSettings::lastCreditedAt($accountType);
        if ($last === null) {
            FinancialSettings::stampLastCredited($accountType, now());

            return false;
        }

        return match (FinancialSettings::interval($accountType)) {
            'daily' => $last->lt(now()->startOfDay()),
            'yearly' => $last->lt(now()->startOfYear()),
            default => $last->lt(now()->startOfMonth()),
        };
    }

    /**
     * Credit every eligible member account of the given type.
     *
     * Idempotent per (account type, period bucket): re-invoking for the same
     * period returns the existing batch instead of double-crediting.
     */
    public function disburse(string $accountType, ?User $actor = null, string $trigger = 'scheduled', ?User $approver = null): InterestDisbursement
    {
        if (! in_array($accountType, FinancialSettings::ACCOUNT_TYPES, true)) {
            throw new InvalidArgumentException("Unknown account type: {$accountType}");
        }

        $rate = FinancialSettings::rate($accountType);
        if ($rate <= 0) {
            throw new RuntimeException("No interest rate is configured for {$accountType} accounts.");
        }

        $interval = FinancialSettings::interval($accountType);
        $now = now();
        $periodLabel = FinancialSettings::periodLabel($interval, $now);
        $batchKey = FinancialSettings::keySegment($accountType).':'.$periodLabel;

        if ($existing = InterestDisbursement::query()->where('batch_key', $batchKey)->first()) {
            return $existing;
        }

        $divisor = FinancialSettings::intervalDivisor($interval);
        $minBalance = FinancialSettings::minBalance($accountType);
        $minPayout = FinancialSettings::minPayout();
        $notify = FinancialSettings::notifyMembers();

        $periodStart = match ($interval) {
            'daily' => $now->copy()->startOfDay(),
            'yearly' => $now->copy()->startOfYear(),
            default => $now->copy()->startOfMonth(),
        };

        app(AuditLogService::class)->log(
            'financial.interest.started',
            "Interest run started for {$accountType}.",
            'financial',
            null,
            $approver ?? $actor,
            ['account_type' => $accountType, 'batch_key' => $batchKey, 'trigger' => $trigger, 'requested_by' => $actor?->id, 'approved_by' => $approver?->id],
        );

        try {
            return DB::transaction(function () use (
                $accountType, $rate, $interval, $periodLabel, $periodStart, $now,
                $batchKey, $divisor, $minBalance, $minPayout, $notify, $trigger, $actor, $approver
            ) {
                $batch = InterestDisbursement::create([
                    'account_type' => $accountType,
                    'rate' => $rate,
                    'interval' => $interval,
                    'period_label' => $periodLabel,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $now->toDateString(),
                    'status' => 'completed',
                    'trigger' => $trigger,
                    'batch_key' => $batchKey,
                    'requested_by' => $actor?->id,
                    'approved_by' => $trigger === 'manual' ? ($approver?->id ?? $actor?->id) : null,
                ]);

                $accounts = Account::query()
                    ->where('account_type', $accountType)
                    ->whereHas('user', function ($query) {
                        $query->where('account_status', 'active')
                            ->whereHas('roles', fn ($role) => $role->where('name', 'member'));
                    })
                    ->with(['user:'.self::USER_EAGER_COLUMNS, 'user.personalData:id,first_name,last_name'])
                    ->lockForUpdate()
                    ->get();

                $count = 0;
                $skipped = 0;
                $total = 0.0;

                foreach ($accounts as $account) {
                    $balanceBefore = round((float) $account->balance, 2);
                    if ($balanceBefore < $minBalance) {
                        continue;
                    }

                    $amount = round($balanceBefore * $rate / $divisor / 100, 2);
                    if ($amount <= 0) {
                        continue;
                    }

                    if ($minPayout > 0 && $amount < $minPayout) {
                        $skipped++;
                        InterestDisbursementItem::create([
                            'interest_disbursement_id' => $batch->id,
                            'user_id' => $account->user_id,
                            'account_id' => $account->id,
                            'amount' => $amount,
                            'balance_before' => $balanceBefore,
                            'balance_after' => $balanceBefore,
                            'rate' => $rate,
                            'skipped' => true,
                            'skip_reason' => 'Below minimum payout',
                        ]);

                        continue;
                    }

                    $transaction = Transaction::create([
                        'account_id' => $account->id,
                        'branch_id' => $account->user?->branch_id,
                        'transaction_type' => 'interest',
                        'transaction_mode' => 'system',
                        'amount' => $amount,
                        'status' => 'completed',
                        'transaction_date' => $now,
                        'note' => $this->note($accountType, $interval, $rate, $periodLabel),
                        'recorded_by' => null,
                        'approved_by' => $trigger === 'manual' ? ($approver?->id ?? $actor?->id) : null,
                        'approved_at' => $now,
                    ]);

                    Account::whereKey($account->id)->increment('balance', $amount);

                    InterestDisbursementItem::create([
                        'interest_disbursement_id' => $batch->id,
                        'user_id' => $account->user_id,
                        'account_id' => $account->id,
                        'transaction_id' => $transaction->id,
                        'amount' => $amount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => round($balanceBefore + $amount, 2),
                        'rate' => $rate,
                        'skipped' => false,
                    ]);

                    $total += $amount;
                    $count++;

                    if ($notify && $account->user) {
                        app(NotificationService::class)->notifyAccountActivity(
                            $account->user,
                            'Interest Credited',
                            'Your '.str_replace('-', ' ', $accountType).' account earned ₱'.number_format($amount, 2).' in interest.',
                        );
                    }
                }

                $batch->update([
                    'member_count' => $count,
                    'skipped_count' => $skipped,
                    'total_amount' => round($total, 2),
                ]);

                FinancialSettings::stampLastCredited($accountType, $now);

                app(AuditLogService::class)->log(
                    'financial.interest.completed',
                    "Interest run completed for {$accountType}: {$count} member(s), ₱".number_format($total, 2).'.',
                    'financial',
                    $batch,
                    $approver ?? $actor,
                    [
                        'account_type' => $accountType,
                        'member_count' => $count,
                        'skipped_count' => $skipped,
                        'total_amount' => round($total, 2),
                        'batch_key' => $batchKey,
                        'requested_by' => $actor?->id,
                        'approved_by' => $approver?->id,
                    ],
                );

                return $batch->refresh();
            });
        } catch (\Throwable $e) {
            app(AuditLogService::class)->log(
                'financial.interest.failed',
                "Interest run failed for {$accountType}: {$e->getMessage()}",
                'financial',
                null,
                $approver ?? $actor,
                ['account_type' => $accountType, 'batch_key' => $batchKey],
            );

            throw $e;
        }
    }

    private function note(string $accountType, string $interval, float $rate, string $periodLabel): string
    {
        $label = $accountType === 'share-capital' ? 'Share capital dividend' : 'Savings interest';

        return sprintf(
            '%s credit — %s%% p.a. (%s) for %s.',
            $label,
            rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.'),
            $interval,
            $periodLabel,
        );
    }
}
