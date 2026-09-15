<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BranchScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function financial(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('reports.view'), 403);

        [$start, $end] = $this->resolveRange($request);
        $branchId = BranchScope::resolveBranchId(Auth::user(), $request->integer('branch_id'));

        $totals = $this->applyWindowToTransactions(
            $this->completedTransactions($branchId)->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'fee']),
            $start,
            $end
        )
            ->selectRaw('transaction_type, SUM(amount) as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $totalExpenses = (float) Expense::query()
            ->when($branchId !== null, fn ($q) => $q->where('expenses.branch_id', $branchId))
            ->whereBetween('expense_date', [$start, $end])
            ->sum('amount');

        $payments = $this->completedTransactions($branchId)
            ->where('transaction_type', 'loan_payment')
            ->whereBetween('transaction_date', [$start, $end])
            ->with(['loan' => fn ($q) => $q->select('id', 'loan_amount', 'interest_rate', 'term_months')])
            ->get(['id', 'amount', 'loan_id']);

        $totalInterestEarned = 0.0;
        foreach ($payments as $payment) {
            if ($payment->loan) {
                $totalPayable = $payment->loan->totalPayable();
                $totalInterest = $payment->loan->totalInterest();
                if ($totalPayable > 0) {
                    $totalInterestEarned += (float) $payment->amount * ($totalInterest / $totalPayable);
                }
            }
        }
        $totalInterestEarned = round($totalInterestEarned, 2);

        $projectedInterest = (float) BranchScope::applyToLoans(
            Loan::query()
                ->whereIn('loan_status', ['approved', 'active', 'paid_off'])
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('applied_at', [$start, $end])
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->whereNull('applied_at')->whereBetween('created_at', [$start, $end]);
                        });
                }),
            Auth::user(),
            $branchId
        )
            ->get(['loan_amount', 'interest_rate', 'term_months'])
            ->sum(fn (Loan $loan) => $loan->totalInterest());

        $inflows = (float) ($totals['deposit'] ?? 0) + (float) ($totals['loan_payment'] ?? 0) + (float) ($totals['fee'] ?? 0);
        $outflows = (float) ($totals['withdrawal'] ?? 0) + (float) ($totals['loan_disbursement'] ?? 0) + $totalExpenses;

        return response()->json([
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'total_deposits' => (float) ($totals['deposit'] ?? 0),
            'total_withdrawals' => (float) ($totals['withdrawal'] ?? 0),
            'total_loan_releases' => (float) ($totals['loan_disbursement'] ?? 0),
            'total_loan_repayments' => (float) ($totals['loan_payment'] ?? 0),
            'total_interest_earned' => $totalInterestEarned,
            'projected_interest' => round($projectedInterest, 2),
            'total_fees' => (float) ($totals['fee'] ?? 0),
            'total_expenses' => $totalExpenses,
            'net' => $inflows - $outflows,
            'monthly_series' => $this->monthlySeries($end, $branchId),
        ]);
    }

    public function expenses(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('reports.view'), 403);

        [$start, $end] = $this->resolveRange($request);
        $category = $request->string('category')->toString();
        $branchId = BranchScope::resolveBranchId(Auth::user(), $request->integer('branch_id'));

        $query = Expense::query()
            ->with('recordedBy.personalData')
            ->whereBetween('expense_date', [$start, $end])
            ->when($branchId !== null, fn ($q) => $q->where('expenses.branch_id', $branchId))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->orderByDesc('expense_date');

        $paginator = $query->paginate(10);

        $items = collect($paginator->items())->map(fn (Expense $e) => [
            'id' => $e->id,
            'category' => $e->category,
            'description' => $e->description,
            'amount' => (float) $e->amount,
            'expense_date' => $e->expense_date?->format('Y-m-d'),
            'recorded_by' => $e->recordedBy?->personalData
                ? trim(($e->recordedBy->personalData->first_name ?? '').' '.($e->recordedBy->personalData->last_name ?? ''))
                : null,
        ]);

        $total = (float) Expense::query()
            ->whereBetween('expense_date', [$start, $end])
            ->when($branchId !== null, fn ($q) => $q->where('expenses.branch_id', $branchId))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->sum('amount');

        return response()->json([
            ...$this->withPagination($items, $paginator),
            'total' => $total,
        ]);
    }

    public function memberActivity(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('reports.view'), 403);

        [$start, $end] = $this->resolveRange($request);
        $activityType = $request->string('activity_type', 'all')->toString();
        $memberId = $request->integer('member_id');
        $branchId = BranchScope::resolveBranchId(Auth::user(), $request->integer('branch_id'));
        $allowedTypes = ['all', 'deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'fee'];

        $query = $this->completedTransactions($branchId)
            ->with(['account.user.personalData', 'loan.user.personalData'])
            ->where(function ($builder) {
                $builder->whereHas('account.user')
                    ->orWhereHas('loan.user');
            });

        $query = $this->applyWindowToTransactions($query, $start, $end);

        if ($memberId) {
            $query->where(function ($q) use ($memberId) {
                $q->whereHas('account', fn ($a) => $a->where('user_id', $memberId))
                    ->orWhereHas('loan', fn ($l) => $l->where('user_id', $memberId));
            });
        }

        if (in_array($activityType, array_slice($allowedTypes, 1), true)) {
            $query->where('transaction_type', $activityType);
        }

        $paginator = $query->latest('transaction_date')->paginate(10);

        $items = collect($paginator->items())->map(function (Transaction $transaction) {
            $user = $transaction->account?->user ?? $transaction->loan?->user;

            return [
                'id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'transaction_type' => $transaction->transaction_type,
                'amount' => (float) $transaction->amount,
                'transaction_date' => ($transaction->transaction_date ?? $transaction->created_at)->format('Y-m-d'),
                'member_name' => $user?->name ?: $user?->email,
            ];
        });

        return response()->json([
            ...$this->withPagination($items, $paginator),
            'total' => (float) $query->sum('amount'),
        ]);
    }

    public function memberSavings(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('reports.view'), 403);

        [$start, $end] = $this->resolveRange($request);
        $memberId = $request->integer('member_id');
        $branchId = BranchScope::resolveBranchId(Auth::user(), $request->integer('branch_id'));

        $accounts = Account::query()
            ->where('account_type', 'savings')
            ->whereNotNull('user_id')
            ->when($memberId, fn ($q) => $q->where('user_id', $memberId))
            ->when($branchId !== null, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('users.branch_id', $branchId)))
            ->with('user.personalData')
            ->orderBy('id', 'desc')
            ->paginate(10, ['id', 'user_id', 'balance']);

        $accountIds = collect($accounts->items())->pluck('id')->filter()->values();

        $totals = collect();
        if ($accountIds->isNotEmpty()) {
            $totals = $this->applyWindowToTransactions(
                $this->completedTransactions($branchId)->whereIn('account_id', $accountIds),
                $start,
                $end
            )
                ->whereIn('transaction_type', ['deposit', 'withdrawal'])
                ->selectRaw('account_id, transaction_type, SUM(amount) as total')
                ->groupBy('account_id', 'transaction_type')
                ->get();
        }

        $byAccount = [];
        foreach ($totals as $row) {
            $byAccount[$row->account_id][$row->transaction_type] = (float) $row->total;
        }

        $items = collect($accounts->items())->map(function (Account $account) use ($byAccount) {
            $name = 'N/A';
            if ($account->user?->personalData) {
                $name = trim(($account->user->personalData->first_name ?? '').' '.($account->user->personalData->last_name ?? ''));
            }

            return [
                'member_name' => $name,
                'total_deposits' => (float) ($byAccount[$account->id]['deposit'] ?? 0),
                'total_withdrawals' => (float) ($byAccount[$account->id]['withdrawal'] ?? 0),
                'current_balance' => (float) $account->balance,
            ];
        });

        return response()->json($this->withPagination($items, $accounts));
    }

    public function loanReport(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('reports.view'), 403);

        [$start, $end] = $this->resolveRange($request);
        $loanFilter = $request->string('filter', 'all')->toString();
        $branchId = BranchScope::resolveBranchId(Auth::user(), $request->integer('branch_id'));
        $completedStatuses = ['paid', 'paid_off'];

        $query = BranchScope::applyToLoans(
            Loan::query()
                ->with('user.personalData')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('applied_at', [$start, $end])
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->whereNull('applied_at')->whereBetween('created_at', [$start, $end]);
                        });
                }),
            Auth::user(),
            $branchId
        );

        if ($loanFilter === 'active') {
            $query->whereIn('loan_status', ['approved', 'active']);
        } elseif ($loanFilter === 'completed') {
            $query->whereIn('loan_status', $completedStatuses);
        } elseif ($loanFilter === 'overdue') {
            $query->whereNotIn('loan_status', $completedStatuses)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', Carbon::today());
        }

        $paginator = $query->orderByDesc('created_at')->paginate(10);

        $items = collect($paginator->items())->map(function (Loan $loan) {
            $name = 'N/A';
            if ($loan->user?->personalData) {
                $name = trim(($loan->user->personalData->first_name ?? '').' '.($loan->user->personalData->last_name ?? ''));
            }

            $amountPaid = (float) $loan->totalPaid();
            $remaining = (float) $loan->remainingBalance();
            $isOverdue = $loan->due_date && Carbon::parse($loan->due_date)->isPast() && $remaining > 0;

            $status = match (true) {
                in_array($loan->loan_status, ['paid', 'paid_off'], true) || $remaining <= 0 => 'Completed',
                $isOverdue => 'Overdue',
                default => 'Active',
            };

            return [
                'member_name' => $name,
                'loan_amount' => (float) $loan->loan_amount,
                'interest_rate' => (float) $loan->interest_rate,
                'loan_date' => ($loan->applied_at ?? $loan->created_at)->format('Y-m-d'),
                'due_date' => $loan->due_date ? Carbon::parse($loan->due_date)->format('Y-m-d') : null,
                'amount_paid' => $amountPaid,
                'remaining_balance' => $remaining,
                'status' => $status,
                'loan_number' => $loan->loan_number,
            ];
        });

        return response()->json($this->withPagination($items, $paginator));
    }

    public function payments(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('reports.view'), 403);

        [$start, $end] = $this->resolveRange($request);
        $branchId = BranchScope::resolveBranchId(Auth::user(), $request->integer('branch_id'));

        $query = Transaction::query()
            ->with('loan.user.personalData')
            ->where('transaction_type', 'loan_payment')
            ->where('status', 'completed')
            ->when($branchId !== null, fn ($q) => $q->where('transactions.branch_id', $branchId));

        $paginator = $this->applyWindowToTransactions($query, $start, $end)
            ->orderByDesc('transaction_date')
            ->paginate(10);

        $items = collect($paginator->items())->map(function (Transaction $transaction) {
            $pd = $transaction->loan?->user?->personalData;
            $name = $pd ? trim(($pd->first_name ?? '').' '.($pd->last_name ?? '')) : 'N/A';

            return [
                'member_name' => $name,
                'loan_number' => $transaction->loan?->loan_number ?? '—',
                'amount' => (float) $transaction->amount,
                'payment_date' => ($transaction->transaction_date ?? $transaction->created_at)->format('Y-m-d'),
                'remaining_balance' => (float) ($transaction->loan?->remainingBalance() ?? 0),
            ];
        });

        return response()->json($this->withPagination($items, $paginator));
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('reports.view'), 403);

        $branchId = BranchScope::resolveBranchId(Auth::user(), $request->integer('branch_id'));

        $members = BranchScope::applyToUsers(
            User::query()->memberUsers()->with('personalData'),
            Auth::user(),
            $branchId
        )->orderBy('id', 'desc')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->personalData
                    ? trim(($user->personalData->first_name ?? '').' '.($user->personalData->last_name ?? ''))
                    : ($user->email ?? 'N/A'),
                'branch_id' => $user->branch_id,
                'branch_name' => $user->branch?->name,
            ]);

        $categories = Expense::query()
            ->when($branchId !== null, fn ($q) => $q->where('expenses.branch_id', $branchId))
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json([
            'members' => $members,
            'expense_categories' => $categories,
        ]);
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('reports.financial'), 403);

        [$start, $end] = $this->resolveRange($request);
        $branchId = BranchScope::resolveBranchId(Auth::user(), $request->integer('branch_id'));

        return response()->json($this->buildBalanceSheet($start, $end, $branchId));
    }

    public function downloadBalanceSheet(Request $request)
    {
        abort_unless(Auth::user()?->can('reports.financial'), 403);

        [$start, $end] = $this->resolveRange($request);
        $branchId = BranchScope::resolveBranchId(Auth::user(), $request->integer('branch_id'));
        $balanceSheet = $this->buildBalanceSheet($start, $end, $branchId);
        $branchName = $branchId !== null ? $balanceSheet['branch_name'] : null;

        $pdf = Pdf::loadView('reports.balance-sheet-pdf', [
            'balanceSheet' => $balanceSheet,
            'appName' => config('app.name', 'Cooperative Management System'),
            'generatedAt' => now(),
            'generatedBy' => Auth::user(),
            'branchName' => $branchName,
        ]);

        $filename = 'balance-sheet-'.$end->format('Y-m-d').'.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    private function buildBalanceSheet(Carbon $start, Carbon $end, ?int $branchId = null): array
    {
        $branchName = $branchId !== null ? Branch::find($branchId)?->name : null;

        $totalsByType = $this->completedTransactions($branchId)
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

        $totalExpenses = (float) Expense::query()
            ->when($branchId !== null, fn ($q) => $q->where('expenses.branch_id', $branchId))
            ->whereDate('expense_date', '<=', $end->toDateString())
            ->sum('amount');

        $cashAndCashEquivalents = $cashInflows - $cashOutflows - $totalExpenses;

        $activeLoanStatuses = ['approved', 'active'];
        $loanPrincipalActive = (float) BranchScope::applyToLoans(
            Loan::query()->whereIn('loan_status', $activeLoanStatuses),
            Auth::user(),
            $branchId
        )->sum('loan_amount');
        $loanPaymentsOnActive = (float) Transaction::query()
            ->where('transaction_type', 'loan_payment')
            ->where('status', 'completed')
            ->when($branchId !== null, fn ($q) => $q->where('transactions.branch_id', $branchId))
            ->whereHas('loan', fn ($q) => $q->when($branchId !== null, fn ($l) => $l->where('loans.branch_id', $branchId))->whereIn('loan_status', $activeLoanStatuses))
            ->sum('amount');
        $netLoansReceivable = max(0, $loanPrincipalActive - $loanPaymentsOnActive);

        $accountTotals = $this->accountTotals($branchId);
        $memberSavingsLiability = $accountTotals['savings'];
        $shareCapital = $accountTotals['share_capital'];

        $totalAssets = $cashAndCashEquivalents + $netLoansReceivable;
        $totalLiabilities = $memberSavingsLiability;
        $totalEquity = $totalAssets - $totalLiabilities;
        $retainedEarnings = $totalEquity - $shareCapital;

        return [
            'start' => $start,
            'end' => $end,
            'as_of' => $end,
            'branch_name' => $branchName,
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

    private function monthlySeries(Carbon $end, ?int $branchId = null): array
    {
        $series = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $end->copy()->subMonths($i);
            $from = $month->copy()->startOfMonth();
            $to = $month->copy()->endOfMonth();

            $totals = $this->applyWindowToTransactions(
                $this->completedTransactions($branchId)->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'fee']),
                $from,
                $to
            )
                ->selectRaw('transaction_type, SUM(amount) as total')
                ->groupBy('transaction_type')
                ->pluck('total', 'transaction_type');

            $expenses = (float) Expense::query()
                ->when($branchId !== null, fn ($q) => $q->where('expenses.branch_id', $branchId))
                ->whereBetween('expense_date', [$from, $to])
                ->sum('amount');

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

    private function completedTransactions(?int $branchId = null)
    {
        $query = Transaction::query()->where('status', 'completed');

        return $branchId === null ? $query : $query->where('transactions.branch_id', $branchId);
    }

    private function accountTotals(?int $branchId): array
    {
        $query = Account::query()->whereIn('account_type', ['savings', 'share-capital']);

        if ($branchId !== null) {
            $query->whereHas('user', fn ($q) => $q->where('users.branch_id', $branchId));
        }

        $totals = $query
            ->selectRaw('account_type, SUM(balance) as total')
            ->groupBy('account_type')
            ->pluck('total', 'account_type');

        return [
            'savings' => (float) ($totals['savings'] ?? 0),
            'share_capital' => (float) ($totals['share_capital'] ?? 0),
        ];
    }

    private function applyWindowToTransactions(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('transaction_date', [$start, $end])
                ->orWhere(function ($q2) use ($start, $end) {
                    $q2->whereNull('transaction_date')
                        ->whereBetween('created_at', [$start, $end]);
                });
        });
    }

    private function withPagination($items, LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
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
