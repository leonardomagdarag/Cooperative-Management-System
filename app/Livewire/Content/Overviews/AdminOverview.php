<?php

namespace App\Livewire\Content\Overviews;

use App\Models\Account;
use App\Models\Announcement;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AdminOverview extends Component
{
    public array $kpis = [];

    /**
     * Last N months (oldest -> newest) for simple bar charts.
     * Shape: [{ label, deposits, withdrawals, expenses, net }]
     *
     * @var array<int,array{label:string,deposits:float,withdrawals:float,expenses:float,net:float}>
     */
    public array $monthlySeries = [];

    /** @var float */
    public float $monthlyMaxAbsNet = 0.0;

    /** @var array<string,int> */
    public array $loanStatusCounts = [];

    /**
     * @var array<int,array{status:string,label:string,count:int,percent:float,color:string}>
     */
    public array $loanStatusSeries = [];

    /** @var \Illuminate\Database\Eloquent\Collection<int,\App\Models\Transaction> */
    public Collection $pendingTransactions;

    /** @var \Illuminate\Database\Eloquent\Collection<int,\App\Models\Expense> */
    public Collection $recentExpenses;

    /** @var \Illuminate\Database\Eloquent\Collection<int,\App\Models\Announcement> */
    public Collection $recentAnnouncements;

    public function mount(): void
    {
        abort_unless(Auth::check() && Auth::user()->can('reports.audit'), 403);

        $this->pendingTransactions = new Collection();
        $this->recentExpenses = new Collection();
        $this->recentAnnouncements = new Collection();

        $this->loadData();
    }

    public function loadData(): void
    {
        $now = now();
        $start = $now->copy()->startOfMonth();
        $end = $now->copy()->endOfMonth();

        $totalMembers = User::query()->where('role', 'member')->count();
        $totalStaff = User::query()->whereIn('role', ['admin', 'manager', 'loan', 'secretary', 'treasurer'])->count();
        $newMembersThisMonth = User::query()
            ->where('role', 'member')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $totalSavings = (float) Account::query()
            ->where('account_type', 'savings')
            ->sum('balance');

        $totalShareCapital = (float) Account::query()
            ->whereIn('account_type', ['share-capital', 'share_capital'])
            ->sum('balance');

        $financialThisMonth = $this->financialSummary($start, $end);

        // Loan snapshot
        $activeLoanStatuses = ['approved', 'active'];
        $paidLoanStatuses = ['paid', 'paid_off', 'paid-off'];

        $activeLoansCount = Loan::query()->whereIn('loan_status', $activeLoanStatuses)->count();
        $pendingLoansCount = Loan::query()->where('loan_status', 'pending')->count();

        $loanPrincipalActive = (float) Loan::query()->whereIn('loan_status', $activeLoanStatuses)->sum('loan_amount');

        $loanPaymentsOnActive = (float) DB::table('transactions')
            ->join('loans', 'transactions.loan_id', '=', 'loans.id')
            ->where('transactions.transaction_type', 'loan_payment')
            ->where('transactions.status', 'completed')
            ->whereIn('loans.loan_status', $activeLoanStatuses)
            ->sum('transactions.amount');

        $loanOutstanding = max(0, $loanPrincipalActive - $loanPaymentsOnActive);

        $overdueLoansCount = Loan::query()
            ->whereNotIn('loan_status', $paidLoanStatuses)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->count();

        $dueSoonLoansCount = Loan::query()
            ->whereIn('loan_status', $activeLoanStatuses)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [today(), today()->copy()->addDays(14)])
            ->count();

        $this->loanStatusCounts = Loan::query()
            ->select('loan_status', DB::raw('COUNT(*) as total'))
            ->groupBy('loan_status')
            ->pluck('total', 'loan_status')
            ->map(fn($value) => (int) $value)
            ->toArray();

        $this->buildLoanStatusSeries();

        $this->buildMonthlySeries(6);

        $pendingTransactionsCount = Transaction::query()->where('status', 'pending')->count();

        $this->pendingTransactions = Transaction::query()
            ->with(['account.user.personalData'])
            ->where('status', 'pending')
            ->latest()
            ->take(8)
            ->get();

        $this->recentExpenses = Expense::query()
            ->orderByDesc('expense_date')
            ->take(5)
            ->get();

        $this->recentAnnouncements = Announcement::query()
            ->with('creator.personalData')
            ->latest()
            ->take(5)
            ->get();

        $this->kpis = [
            'period_label' => $start->format('M Y'),
            'members_total' => $totalMembers,
            'members_new_month' => $newMembersThisMonth,
            'staff_total' => $totalStaff,
            'savings_total' => $totalSavings,
            'share_capital_total' => $totalShareCapital,

            'pending_transactions' => $pendingTransactionsCount,
            'pending_loans' => $pendingLoansCount,

            'loans_active' => $activeLoansCount,
            'loans_outstanding' => $loanOutstanding,
            'loans_overdue' => $overdueLoansCount,
            'loans_due_soon' => $dueSoonLoansCount,

            // This month cashflow-ish
            'month_deposits' => $financialThisMonth['total_deposits'],
            'month_withdrawals' => $financialThisMonth['total_withdrawals'],
            'month_expenses' => $financialThisMonth['total_expenses'],
            'month_net' => $financialThisMonth['net'],
        ];
    }

    private function buildMonthlySeries(int $months): void
    {
        $months = max(1, min(24, $months));

        $series = [];
        $maxAbsNet = 0.0;
        $baseNow = now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = $baseNow->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $summary = $this->financialSummary($monthStart, $monthEnd);

            $net = (float) ($summary['net'] ?? 0);
            $maxAbsNet = max($maxAbsNet, abs($net));

            $series[] = [
                'label' => $monthStart->format('M Y'),
                'deposits' => (float) ($summary['total_deposits'] ?? 0),
                'withdrawals' => (float) ($summary['total_withdrawals'] ?? 0),
                'expenses' => (float) ($summary['total_expenses'] ?? 0),
                'net' => $net,
            ];
        }

        $this->monthlySeries = $series;
        $this->monthlyMaxAbsNet = (float) $maxAbsNet;
    }

    private function buildLoanStatusSeries(): void
    {
        $total = array_sum($this->loanStatusCounts);
        $total = max(0, (int) $total);

        // Keep a sensible, consistent order; add any unknown statuses at the end.
        $preferredOrder = [
            'pending',
            'approved',
            'active',
            'paid',
            'paid_off',
            'paid-off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
            'paid_off',
        ];

        $colorMap = [
            'pending' => 'bg-amber-500',
            'approved' => 'bg-cyan-500',
            'active' => 'bg-emerald-500',
            'paid' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid-off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
            'paid_off' => 'bg-gray-700',
        ];

        $seen = [];
        $orderedStatuses = [];

        foreach ($preferredOrder as $status) {
            if (isset($this->loanStatusCounts[$status]) && !isset($seen[$status])) {
                $orderedStatuses[] = $status;
                $seen[$status] = true;
            }
        }

        foreach (array_keys($this->loanStatusCounts) as $status) {
            if (!isset($seen[$status])) {
                $orderedStatuses[] = $status;
                $seen[$status] = true;
            }
        }

        $series = [];
        foreach ($orderedStatuses as $status) {
            $count = (int) ($this->loanStatusCounts[$status] ?? 0);
            $percent = $total > 0 ? round(($count / $total) * 100, 1) : 0.0;

            $series[] = [
                'status' => (string) $status,
                'label' => ucwords(str_replace(['_', '-'], ' ', (string) $status)),
                'count' => $count,
                'percent' => (float) $percent,
                'color' => $colorMap[$status] ?? 'bg-slate-500',
            ];
        }

        $this->loanStatusSeries = $series;
    }

    private function completedTransactionsQuery(): EloquentBuilder
    {
        return Transaction::query()->where('status', 'completed');
    }

    /**
     * Applies a date window to a transaction query.
     * Prefers transaction_date but falls back to created_at for legacy rows.
     */
    private function applyWindowToTransactionsQuery(EloquentBuilder $query, Carbon $start, Carbon $end): EloquentBuilder
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('transaction_date', [$start, $end])
                ->orWhere(function ($q2) use ($start, $end) {
                    $q2->whereNull('transaction_date')
                        ->whereBetween('created_at', [$start, $end]);
                });
        });
    }

    private function financialSummary(Carbon $start, Carbon $end): array
    {
        $base = $this->applyWindowToTransactionsQuery($this->completedTransactionsQuery(), $start, $end);

        $totals = (clone $base)
            ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'fee'])
            ->selectRaw('transaction_type, SUM(amount) as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $totalDeposits = (float) ($totals['deposit'] ?? 0);
        $totalWithdrawals = (float) ($totals['withdrawal'] ?? 0);
        $totalLoanReleases = (float) ($totals['loan_disbursement'] ?? 0);
        $totalLoanRepayments = (float) ($totals['loan_payment'] ?? 0);
        $totalFees = (float) ($totals['fee'] ?? 0);

        $totalExpenses = (float) Expense::query()
            ->whereBetween('expense_date', [$start, $end])
            ->sum('amount');

        $inflows = $totalDeposits + $totalLoanRepayments + $totalFees;
        $outflows = $totalWithdrawals + $totalLoanReleases + $totalExpenses;

        return [
            'total_deposits' => $totalDeposits,
            'total_withdrawals' => $totalWithdrawals,
            'total_expenses' => $totalExpenses,
            'net' => $inflows - $outflows,
        ];
    }

    public function render()
    {
        return view('livewire.content.overviews.admin-overview');
    }
}
