<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\InterestDisbursement;
use App\Models\InterestDisbursementItem;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Services\AuditLogService;
use App\Services\InterestService;
use App\Support\FinancialSettings;
use Carbon\Carbon;
use Database\Seeders\Support\LedgerWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Credits six months of monthly interest on member savings and share capital.
 *
 * The board's standing rates are applied first (FinancialSettingSeeder ships
 * both at 0 because they are high-security settings a site has to opt into).
 * Five prior monthly cycles are replayed against the historical balance each
 * account actually held at the end of that month, and the current month is
 * handed to the real InterestService, so the seed produces six months of batch
 * rows, per-member items and `interest` ledger entries.
 *
 * Each period is idempotent per `(type, YYYY-MM)`: a re-seed finds the
 * existing batch and returns before it writes anything, so balances and counts
 * hold still.
 */
class InterestDisbursementSeeder extends Seeder
{
    private const SAVINGS_RATE = 2.5;

    private const SHARE_CAPITAL_RATE = 4.0;

    private const HISTORICAL_MONTHS = 5;

    public function run(): void
    {
        SystemSetting::set('financial_savings_interest_rate', self::SAVINGS_RATE, 'float', 'financial');
        SystemSetting::set('financial_share_capital_interest_rate', self::SHARE_CAPITAL_RATE, 'float', 'financial');
        SystemSetting::set('financial_savings_interest_interval', 'monthly', 'string', 'financial');
        SystemSetting::set('financial_share_capital_interest_interval', 'monthly', 'string', 'financial');

        $ledger = new LedgerWriter;

        for ($offset = self::HISTORICAL_MONTHS; $offset >= 1; $offset--) {
            $periodDate = Carbon::now()->subMonthsNoOverflow($offset)->endOfMonth()->setTime(17, 0);

            foreach (FinancialSettings::ACCOUNT_TYPES as $accountType) {
                $rate = FinancialSettings::rate($accountType);
                if ($rate <= 0) {
                    continue;
                }

                $this->disburseHistoricalPeriod($ledger, $accountType, $rate, $periodDate);
            }
        }

        $service = app(InterestService::class);

        foreach (FinancialSettings::ACCOUNT_TYPES as $accountType) {
            if (FinancialSettings::rate($accountType) <= 0) {
                continue;
            }

            $batch = $service->disburse($accountType, null, 'scheduled');

            $this->command?->info(sprintf(
                'Interest (%s): %d member(s), ₱%s at %s%% p.a. (plus %d prior monthly cycles)',
                $accountType,
                $batch->member_count,
                number_format((float) $batch->total_amount, 2),
                rtrim(rtrim(number_format((float) $batch->rate, 4, '.', ''), '0'), '.'),
                self::HISTORICAL_MONTHS,
            ));
        }
    }

    private function disburseHistoricalPeriod(
        LedgerWriter $ledger,
        string $accountType,
        float $rate,
        Carbon $periodEnd
    ): InterestDisbursement {
        $interval = 'monthly';
        $periodLabel = FinancialSettings::periodLabel($interval, $periodEnd);
        $batchKey = FinancialSettings::keySegment($accountType).':'.$periodLabel;

        if ($existing = InterestDisbursement::query()->where('batch_key', $batchKey)->first()) {
            return $existing;
        }

        $divisor = FinancialSettings::intervalDivisor($interval);
        $minBalance = FinancialSettings::minBalance($accountType);
        $minPayout = FinancialSettings::minPayout();
        $periodStart = $periodEnd->copy()->startOfMonth();

        return DB::transaction(function () use (
            $ledger, $accountType, $rate, $interval, $periodLabel, $periodStart, $periodEnd,
            $batchKey, $divisor, $minBalance, $minPayout
        ) {
            $batch = InterestDisbursement::withoutTimestamps(function () use (
                $accountType, $rate, $interval, $periodLabel, $periodStart, $periodEnd, $batchKey
            ) {
                $model = new InterestDisbursement;
                $model->forceFill([
                    'account_type' => $accountType,
                    'rate' => $rate,
                    'interval' => $interval,
                    'period_label' => $periodLabel,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'status' => 'completed',
                    'trigger' => 'scheduled',
                    'batch_key' => $batchKey,
                    'requested_by' => null,
                    'approved_by' => null,
                ]);
                $model->created_at = $periodEnd;
                $model->updated_at = $periodEnd;
                $model->save();

                return $model;
            });

            $accounts = Account::query()
                ->where('account_type', $accountType)
                ->whereHas('user', function ($query) {
                    $query->where('account_status', 'active')
                        ->whereHas('roles', fn ($role) => $role->where('name', 'member'));
                })
                ->with(['user:'.InterestService::USER_EAGER_COLUMNS])
                ->orderBy('id')
                ->get();

            $count = 0;
            $skipped = 0;
            $total = 0.0;

            foreach ($accounts as $account) {
                $balanceBefore = $this->balanceAsOf($account, $periodEnd);
                if ($balanceBefore < $minBalance || $balanceBefore <= 0) {
                    continue;
                }

                $amount = round($balanceBefore * $rate / $divisor / 100, 2);
                if ($amount <= 0) {
                    continue;
                }

                if ($minPayout > 0 && $amount < $minPayout) {
                    $skipped++;
                    InterestDisbursementItem::withoutTimestamps(function () use ($batch, $account, $amount, $balanceBefore, $rate, $periodEnd) {
                        $item = new InterestDisbursementItem;
                        $item->forceFill([
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
                        $item->created_at = $periodEnd;
                        $item->updated_at = $periodEnd;
                        $item->save();
                    });

                    continue;
                }

                $transaction = Transaction::withoutTimestamps(function () use ($ledger, $account, $accountType, $interval, $rate, $periodLabel, $amount, $periodEnd) {
                    $tx = new Transaction;
                    $tx->forceFill([
                        'transaction_number' => $ledger->nextTransactionNumber($periodEnd),
                        'account_id' => $account->id,
                        'branch_id' => $account->user?->branch_id,
                        'transaction_type' => 'interest',
                        'transaction_mode' => 'system',
                        'amount' => $amount,
                        'status' => 'completed',
                        'transaction_date' => $periodEnd,
                        'note' => $this->note($accountType, $interval, $rate, $periodLabel),
                        'recorded_by' => null,
                        'approved_by' => null,
                        'approved_at' => $periodEnd,
                    ]);
                    $tx->created_at = $periodEnd;
                    $tx->updated_at = $periodEnd;
                    $tx->save();

                    return $tx;
                });

                Account::whereKey($account->id)->increment('balance', $amount);

                InterestDisbursementItem::withoutTimestamps(function () use ($batch, $account, $transaction, $amount, $balanceBefore, $rate, $periodEnd) {
                    $item = new InterestDisbursementItem;
                    $item->forceFill([
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
                    $item->created_at = $periodEnd;
                    $item->updated_at = $periodEnd;
                    $item->save();
                });

                $total += $amount;
                $count++;
            }

            InterestDisbursement::withoutTimestamps(function () use ($batch, $count, $skipped, $total, $periodEnd) {
                $batch->forceFill([
                    'member_count' => $count,
                    'skipped_count' => $skipped,
                    'total_amount' => round($total, 2),
                    'updated_at' => $periodEnd,
                ])->save();
            });

            app(AuditLogService::class)->log(
                'financial.interest.completed',
                "Interest run completed for {$accountType}: {$count} member(s), ₱".number_format($total, 2).'.',
                'financial',
                $batch,
                null,
                [
                    'account_type' => $accountType,
                    'member_count' => $count,
                    'skipped_count' => $skipped,
                    'total_amount' => round($total, 2),
                    'batch_key' => $batchKey,
                ],
            );

            return $batch->refresh();
        });
    }

    private function balanceAsOf(Account $account, Carbon $asOf): float
    {
        $balance = 0.0;

        $rows = Transaction::query()
            ->where('account_id', $account->id)
            ->where('status', 'completed')
            ->where('transaction_date', '<=', $asOf)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get(['transaction_type', 'amount', 'reversal_of_id']);

        foreach ($rows as $row) {
            $balance += LedgerWriter::balanceEffect(
                $row->transaction_type,
                (float) $row->amount,
                $row->reversal_of_id !== null
            );
        }

        return round(max(0.0, $balance), 2);
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
