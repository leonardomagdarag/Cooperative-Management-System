<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Canonical cooperative cash-flow, member balance, and loans-receivable math.
 *
 * Shared across ReportController and DashboardController so financial reports,
 * balance sheets, and dashboard KPIs agree down to the centavo.
 */
class FinancialCashFlow
{
    public const CASH_FEE_TYPES = ['deposit', 'loan_payment', 'fee'];

    /**
     * Compute cooperative cash inflows, outflows, fees, expenses, and net cash
     * movement for a date window (or cumulative through an end date when $start is null).
     *
     * Rules:
     * - Member `interest` credits increase member savings liability; they are NOT cash inflows.
     * - Only cash loan disbursements (`account_id IS NULL`) disburse physical cash; disbursements
     *   credited to member savings move zero cash until the member later withdraws.
     * - Attached fees on `withdrawal` (deducted from savings) and savings-destination
     *   `loan_disbursement` (deducted from savings credit) do NOT bring extra physical cash in.
     *
     * @return array<string, float>
     */
    public static function cashFlowSummary(
        ?Carbon $start,
        Carbon $end,
        ?int $branchId = null,
        bool $inclusiveEnd = true,
    ): array {
        $baseTx = fn (): Builder => self::applyDateFilter(
            Transaction::query()
                ->where('status', 'completed')
                ->when($branchId !== null, fn (Builder $q) => $q->where('transactions.branch_id', $branchId)),
            $start,
            $end,
            $inclusiveEnd
        );

        $totals = $baseTx()
            ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'interest', 'fee'])
            ->selectRaw('transaction_type, SUM('.Transaction::netAmountExpression().') as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $cashLoanDisbursements = (float) $baseTx()
            ->where('transaction_type', 'loan_disbursement')
            ->whereNull('account_id')
            ->selectRaw('SUM('.Transaction::netAmountExpression().') as total')
            ->value('total');

        $attachedFees = (float) $baseTx()
            ->selectRaw('SUM(CASE WHEN transactions.reversal_of_id IS NULL THEN fee_amount ELSE -fee_amount END) as total')
            ->value('total');

        $cashAttachedFees = (float) $baseTx()
            ->where(function (Builder $q) {
                $q->whereIn('transaction_type', ['deposit', 'loan_payment'])
                    ->orWhere(fn (Builder $sq) => $sq->where('transaction_type', 'loan_disbursement')->whereNull('account_id'));
            })
            ->selectRaw('SUM(CASE WHEN transactions.reversal_of_id IS NULL THEN fee_amount ELSE -fee_amount END) as total')
            ->value('total');

        $expensesQuery = Expense::query()
            ->when($branchId !== null, fn (Builder $q) => $q->where('expenses.branch_id', $branchId));

        if ($start !== null) {
            $expensesQuery->whereBetween('expense_date', [$start, $end]);
        } else {
            $expensesQuery->whereDate('expense_date', $inclusiveEnd ? '<=' : '<', $end->toDateString());
        }

        $expenses = (float) $expensesQuery->sum('amount');

        $deposits = (float) ($totals['deposit'] ?? 0);
        $withdrawals = (float) ($totals['withdrawal'] ?? 0);
        $loanReleases = (float) ($totals['loan_disbursement'] ?? 0);
        $loanRepayments = (float) ($totals['loan_payment'] ?? 0);
        $interestCredits = (float) ($totals['interest'] ?? 0);
        $explicitFees = (float) ($totals['fee'] ?? 0);

        $totalFees = $explicitFees + $attachedFees;
        $cashFees = $explicitFees + $cashAttachedFees;

        $cashInflows = $deposits + $loanRepayments + $cashFees;
        $cashOutflows = $withdrawals + $cashLoanDisbursements;

        return [
            'deposits' => $deposits,
            'withdrawals' => $withdrawals,
            'loan_releases' => $loanReleases,
            'cash_loan_releases' => $cashLoanDisbursements,
            'loan_repayments' => $loanRepayments,
            'interest_credits' => $interestCredits,
            'total_fees' => $totalFees,
            'cash_fees' => $cashFees,
            'expenses' => $expenses,
            'cash_inflows' => $cashInflows,
            'cash_outflows' => $cashOutflows,
            'net' => $cashInflows - $cashOutflows - $expenses,
        ];
    }

    /**
     * Aggregate active (non-soft-deleted) member Savings and Share Capital balances.
     * Enforces user existence consistently across both "All Branches" and per-branch views,
     * and supports rolling back post-$asOf movements for historical Balance Sheets.
     *
     * @return array{savings: float, share_capital: float}
     */
    public static function accountTotals(?int $branchId = null, ?Carbon $asOf = null): array
    {
        $totals = Account::query()
            ->whereIn('account_type', ['savings', 'share-capital'])
            ->whereHas('user', fn (Builder $q) => $q->when(
                $branchId !== null,
                fn (Builder $bq) => $bq->where('users.branch_id', $branchId)
            ))
            ->selectRaw('account_type, SUM(balance) as total')
            ->groupBy('account_type')
            ->pluck('total', 'account_type');

        $savings = (float) ($totals['savings'] ?? 0);
        $shareCapital = (float) ($totals['share-capital'] ?? 0);

        if ($asOf !== null && $asOf->copy()->endOfDay()->lt(now())) {
            $asOfDate = $asOf->toDateString();
            $subsequentMovements = Transaction::query()
                ->where('status', 'completed')
                ->where(function (Builder $q) use ($asOfDate) {
                    $q->whereDate('transaction_date', '>', $asOfDate)
                        ->orWhere(fn (Builder $q2) => $q2->whereNull('transaction_date')->whereDate('created_at', '>', $asOfDate));
                })
                ->whereHas('account', function (Builder $q) use ($branchId) {
                    $q->whereIn('account_type', ['savings', 'share-capital'])
                        ->whereHas('user', fn (Builder $uq) => $uq->when(
                            $branchId !== null,
                            fn (Builder $bq) => $bq->where('users.branch_id', $branchId)
                        ));
                })
                ->with('account:id,account_type')
                ->get();

            foreach ($subsequentMovements as $tx) {
                $effect = match ($tx->transaction_type) {
                    'deposit', 'interest' => (float) $tx->amount,
                    'loan_disbursement' => (float) $tx->amount - (float) $tx->fee_amount,
                    'withdrawal' => -((float) $tx->amount + (float) $tx->fee_amount),
                    default => 0.0,
                };
                if ($tx->reversal_of_id !== null) {
                    $effect = -$effect;
                }

                if ($tx->account?->account_type === 'savings') {
                    $savings -= $effect;
                } elseif ($tx->account?->account_type === 'share-capital') {
                    $shareCapital -= $effect;
                }
            }
        }

        return [
            'savings' => max(0.0, round($savings, 2)),
            'share_capital' => max(0.0, round($shareCapital, 2)),
        ];
    }

    /**
     * Compute outstanding principal receivable across disbursed loans.
     * Excludes penalty payments and post-term interest-extension allocations
     * from base schedule principal recovery.
     *
     * @param  array<int, string>  $statuses
     */
    public static function netLoansReceivable(
        ?int $branchId,
        ?User $actor,
        ?Carbon $asOf = null,
        array $statuses = ['active', 'paid_off'],
    ): float {
        $loansQuery = BranchScope::applyToLoans(
            Loan::query()
                ->whereIn('loan_status', $statuses)
                ->whereNotNull('disbursed_at')
                ->when($asOf !== null, fn (Builder $q) => $q->whereDate('disbursed_at', '<=', $asOf->toDateString())),
            $actor,
            $branchId
        )->with(['completedPayments' => function ($q) use ($asOf) {
            if ($asOf !== null) {
                $asOfDate = $asOf->toDateString();
                $q->where(function (Builder $sq) use ($asOfDate) {
                    $sq->whereDate('transaction_date', '<=', $asOfDate)
                        ->orWhere(fn (Builder $sq2) => $sq2->whereNull('transaction_date')->whereDate('created_at', '<=', $asOfDate));
                });
            }
        }]);

        $loans = $loansQuery->get();
        if ($loans->isEmpty()) {
            return 0.0;
        }

        $extensionAllocationsByLoan = DB::table('loan_payment_installment_allocations as allocations')
            ->join('transactions', 'transactions.id', '=', 'allocations.transaction_id')
            ->join('loans', 'loans.id', '=', 'transactions.loan_id')
            ->whereIn('loans.id', $loans->pluck('id'))
            ->where('transactions.status', 'completed')
            ->whereColumn('allocations.period', '>', 'loans.term_months')
            ->when($asOf !== null, function ($q) use ($asOf) {
                $asOfDate = $asOf->toDateString();
                $q->where(function ($sq) use ($asOfDate) {
                    $sq->whereDate('transactions.transaction_date', '<=', $asOfDate)
                        ->orWhere(fn ($sq2) => $sq2->whereNull('transactions.transaction_date')->whereDate('transactions.created_at', '<=', $asOfDate));
                });
            })
            ->selectRaw('loans.id as loan_id, COALESCE(SUM(CASE WHEN transactions.reversal_of_id IS NULL THEN allocations.amount ELSE -allocations.amount END), 0) as total')
            ->groupBy('loans.id')
            ->pluck('total', 'loan_id');

        $netLoansReceivable = 0.0;
        foreach ($loans as $loan) {
            $principal = (float) $loan->loan_amount;
            $totalPayable = (float) $loan->totalPayable();
            $schedulePaid = (float) $loan->completedPayments
                ->sum(fn ($p) => $p->reversal_of_id === null
                    ? (float) $p->amount - (float) ($p->penalty_amount ?? 0)
                    : -((float) $p->amount - (float) ($p->penalty_amount ?? 0)));

            $extensionPaid = max(0.0, (float) ($extensionAllocationsByLoan[$loan->id] ?? 0));
            $basePaid = max(0.0, $schedulePaid - $extensionPaid);

            if ($totalPayable > 0 && $principal > 0) {
                $principalRatio = min(1.0, $principal / $totalPayable);
                $principalPaid = $basePaid * $principalRatio;
                $remainingPrincipal = max(0.0, round($principal - $principalPaid, 2));
            } else {
                $remainingPrincipal = max(0.0, round($principal - $basePaid, 2));
            }

            $netLoansReceivable += $remainingPrincipal;
        }

        return round($netLoansReceivable, 2);
    }

    private static function applyDateFilter(
        Builder $query,
        ?Carbon $start,
        Carbon $end,
        bool $inclusiveEnd,
    ): Builder {
        if ($start !== null) {
            return $query->where(function (Builder $q) use ($start, $end) {
                $q->whereBetween('transaction_date', [$start, $end])
                    ->orWhere(fn (Builder $q2) => $q2->whereNull('transaction_date')->whereBetween('created_at', [$start, $end]));
            });
        }

        $op = $inclusiveEnd ? '<=' : '<';
        $endDate = $end->toDateString();

        return $query->where(function (Builder $q) use ($op, $endDate) {
            $q->whereDate('transaction_date', $op, $endDate)
                ->orWhere(fn (Builder $q2) => $q2->whereNull('transaction_date')->whereDate('created_at', $op, $endDate));
        });
    }
}
