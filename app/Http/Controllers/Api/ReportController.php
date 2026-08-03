<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function financial(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('reports.view'), 403);

        [$start, $end] = $this->resolveRange($request);

        $totals = $this->completedTransactions()
            ->whereBetween('transaction_date', [$start, $end])
            ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'fee'])
            ->selectRaw('transaction_type, SUM(amount) as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $totalExpenses = (float) Expense::query()->whereBetween('expense_date', [$start, $end])->sum('amount');

        $inflows = (float) ($totals['deposit'] ?? 0) + (float) ($totals['loan_payment'] ?? 0) + (float) ($totals['fee'] ?? 0);
        $outflows = (float) ($totals['withdrawal'] ?? 0) + (float) ($totals['loan_disbursement'] ?? 0) + $totalExpenses;

        return response()->json([
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'total_deposits' => (float) ($totals['deposit'] ?? 0),
            'total_withdrawals' => (float) ($totals['withdrawal'] ?? 0),
            'total_loan_releases' => (float) ($totals['loan_disbursement'] ?? 0),
            'total_loan_repayments' => (float) ($totals['loan_payment'] ?? 0),
            'total_fees' => (float) ($totals['fee'] ?? 0),
            'total_expenses' => $totalExpenses,
            'net' => $inflows - $outflows,
            'monthly_series' => $this->monthlySeries($end),
        ]);
    }

    public function expenses(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('reports.view'), 403);

        [$start, $end] = $this->resolveRange($request);

        $expenses = Expense::query()
            ->with('recordedBy.personalData')
            ->whereBetween('expense_date', [$start, $end])
            ->orderByDesc('expense_date')
            ->get()
            ->map(fn (Expense $e) => [
                'id' => $e->id,
                'category' => $e->category,
                'description' => $e->description,
                'amount' => (float) $e->amount,
                'expense_date' => $e->expense_date?->format('Y-m-d'),
                'recorded_by' => $e->recordedBy?->personalData
                    ? trim(($e->recordedBy->personalData->first_name ?? '').' '.($e->recordedBy->personalData->last_name ?? ''))
                    : null,
            ]);

        return response()->json([
            'data' => $expenses,
            'total' => (float) $expenses->sum('amount'),
        ]);
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('reports.financial'), 403);

        [$start, $end] = $this->resolveRange($request);

        return response()->json($this->buildBalanceSheet($start, $end));
    }

    public function downloadBalanceSheet(Request $request)
    {
        abort_unless(Auth::user()?->can('reports.financial'), 403);

        [$start, $end] = $this->resolveRange($request);
        $balanceSheet = $this->buildBalanceSheet($start, $end);

        $pdf = Pdf::loadView('reports.balance-sheet-pdf', [
            'balanceSheet' => $balanceSheet,
            'appName' => config('app.name', 'Cooperative Management System'),
            'generatedAt' => now(),
            'generatedBy' => Auth::user(),
        ]);

        $filename = 'balance-sheet-'.$end->format('Y-m-d').'.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    private function buildBalanceSheet(Carbon $start, Carbon $end): array
    {
        $totalsByType = $this->completedTransactions()
            ->where(function ($q) use ($end) {
                $q->whereDate('transaction_date', '<=', $end->toDateString())
                    ->orWhere(function ($q2) use ($end) {
                        $q2->whereNull('transaction_date')
                            ->whereDate('created_at', '<=', $end->toDateString());
                    });
            })
            ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'fee'])
            ->selectRaw('transaction_type, SUM(amount) as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $cashInflows = (float) ($totalsByType['deposit'] ?? 0) + (float) ($totalsByType['loan_payment'] ?? 0) + (float) ($totalsByType['fee'] ?? 0);
        $cashOutflows = (float) ($totalsByType['withdrawal'] ?? 0) + (float) ($totalsByType['loan_disbursement'] ?? 0);

        $totalExpenses = (float) Expense::query()->whereDate('expense_date', '<=', $end->toDateString())->sum('amount');

        $cashAndCashEquivalents = $cashInflows - $cashOutflows - $totalExpenses;

        $activeLoanStatuses = ['approved', 'active'];
        $loanPrincipalActive = (float) Loan::query()->whereIn('loan_status', $activeLoanStatuses)->sum('loan_amount');
        $loanPaymentsOnActive = (float) Transaction::query()
            ->where('transaction_type', 'loan_payment')
            ->where('status', 'completed')
            ->whereHas('loan', fn ($q) => $q->whereIn('loan_status', $activeLoanStatuses))
            ->sum('amount');
        $netLoansReceivable = max(0, $loanPrincipalActive - $loanPaymentsOnActive);

        $memberSavingsLiability = (float) Account::query()->where('account_type', 'savings')->sum('balance');
        $shareCapital = (float) Account::query()->where('account_type', 'share-capital')->sum('balance');

        $totalAssets = $cashAndCashEquivalents + $netLoansReceivable;
        $totalLiabilities = $memberSavingsLiability;
        $totalEquity = $totalAssets - $totalLiabilities;
        $retainedEarnings = $totalEquity - $shareCapital;

        return [
            'start' => $start,
            'end' => $end,
            'as_of' => $end,
            'assets' => [
                ['label' => 'Cash and Cash Equivalents', 'amount' => $cashAndCashEquivalents],
                ['label' => 'Loans Receivable (Net)', 'amount' => $netLoansReceivable],
            ],
            'liabilities' => [
                ['label' => 'Member Savings Deposits', 'amount' => $memberSavingsLiability],
            ],
            'equity' => [
                ['label' => 'Share Capital', 'amount' => $shareCapital],
                ['label' => 'Retained Earnings', 'amount' => $retainedEarnings],
            ],
            'totals' => [
                'assets' => $totalAssets,
                'liabilities' => $totalLiabilities,
                'equity' => $totalEquity,
                'liabilities_and_equity' => $totalLiabilities + $totalEquity,
            ],
        ];
    }

    private function monthlySeries(Carbon $end): array
    {
        $series = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $end->copy()->subMonths($i);
            $from = $month->copy()->startOfMonth();
            $to = $month->copy()->endOfMonth();

            $totals = $this->completedTransactions()
                ->whereBetween('transaction_date', [$from, $to])
                ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'fee'])
                ->selectRaw('transaction_type, SUM(amount) as total')
                ->groupBy('transaction_type')
                ->pluck('total', 'transaction_type');

            $expenses = (float) Expense::query()->whereBetween('expense_date', [$from, $to])->sum('amount');

            $deposits = (float) ($totals['deposit'] ?? 0);
            $withdrawals = (float) ($totals['withdrawal'] ?? 0);

            $series[] = [
                'label' => $month->format('M Y'),
                'deposits' => $deposits,
                'withdrawals' => $withdrawals,
                'expenses' => $expenses,
                'net' => $deposits - $withdrawals - $expenses,
            ];
        }

        return $series;
    }

    private function completedTransactions()
    {
        return Transaction::query()->where('status', 'completed');
    }

    private function resolveRange(Request $request): array
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $end = $to !== '' ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        $start = $from !== '' ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();

        if ($start->gt($end)) {
            $start = $end->copy()->startOfMonth();
        }

        return [$start, $end];
    }
}
