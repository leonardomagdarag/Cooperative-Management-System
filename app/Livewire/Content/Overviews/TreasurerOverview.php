<?php

namespace App\Livewire\Content\Overviews;

use App\Models\Account;
use App\Models\Announcement;
use App\Models\Expense;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TreasurerOverview extends Component
{
    public array $kpis = [];
    public array $monthlySeries = [];
    public float $monthlyMaxValue = 0.0;
    public Collection $recentTransactions;
    public Collection $recentExpenses;
    public Collection $recentAnnouncements;
    public string $greeting = '';

    public function mount(): void
    {
        abort_unless(Auth::check() && Auth::user()->hasAnyRole(['admin', 'manager', 'treasurer']), 403);

        $this->recentTransactions  = new Collection();
        $this->recentExpenses      = new Collection();
        $this->recentAnnouncements = new Collection();

        $hour = (int) now()->format('H');
        $this->greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default    => 'Good evening',
        };

        $this->loadData();
    }

    public function loadData(): void
    {
        $now   = now();
        $start = $now->copy()->startOfMonth();
        $end   = $now->copy()->endOfMonth();

        $totalSavings = (float) Account::query()->where('account_type', 'savings')->sum('balance');

        $totalShareCapital = (float) Account::query()
            ->whereIn('account_type', ['share-capital', 'share_capital'])
            ->sum('balance');

        $financialThisMonth = $this->financialSummary($start, $end);

        $pendingTxnCount = Transaction::query()->where('status', 'pending')->count();

        $this->recentTransactions = Transaction::query()
            ->with(['account.user.personalData'])
            ->where('status', 'completed')
            ->latest()
            ->take(8)
            ->get();

        $this->recentExpenses = Expense::query()
            ->orderByDesc('expense_date')
            ->take(6)
            ->get();

        $this->recentAnnouncements = Announcement::query()
            ->latest()
            ->take(3)
            ->get();

        $this->buildMonthlySeries(6);

        $this->kpis = [
            'period_label'        => $start->format('M Y'),
            'savings_total'       => $totalSavings,
            'share_capital_total' => $totalShareCapital,
            'total_assets'        => $totalSavings + $totalShareCapital,
            'month_deposits'      => $financialThisMonth['total_deposits'],
            'month_withdrawals'   => $financialThisMonth['total_withdrawals'],
            'month_expenses'      => $financialThisMonth['total_expenses'],
            'month_net'           => $financialThisMonth['net'],
            'pending_transactions' => $pendingTxnCount,
        ];
    }

    private function buildMonthlySeries(int $months): void
    {
        $months   = max(1, min(12, $months));
        $series   = [];
        $maxValue = 0.0;
        $baseNow  = now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = $baseNow->copy()->subMonths($i)->startOfMonth();
            $monthEnd   = $monthStart->copy()->endOfMonth();
            $summary    = $this->financialSummary($monthStart, $monthEnd);
            $dep = (float) ($summary['total_deposits'] ?? 0);
            $out = (float) ($summary['total_withdrawals'] ?? 0) + (float) ($summary['total_expenses'] ?? 0);
            $maxValue = max($maxValue, $dep, $out);

            $series[] = [
                'label'       => $monthStart->format('M Y'),
                'deposits'    => $dep,
                'withdrawals' => (float) ($summary['total_withdrawals'] ?? 0),
                'expenses'    => (float) ($summary['total_expenses'] ?? 0),
                'net'         => (float) ($summary['net'] ?? 0),
            ];
        }

        $this->monthlySeries  = $series;
        $this->monthlyMaxValue = (float) $maxValue;
    }

    private function completedTransactionsQuery(): EloquentBuilder
    {
        return Transaction::query()->where('status', 'completed');
    }

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
        $base   = $this->applyWindowToTransactionsQuery($this->completedTransactionsQuery(), $start, $end);
        $totals = (clone $base)
            ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'fee'])
            ->selectRaw('transaction_type, SUM(amount) as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $totalDeposits       = (float) ($totals['deposit'] ?? 0);
        $totalWithdrawals    = (float) ($totals['withdrawal'] ?? 0);
        $totalLoanReleases   = (float) ($totals['loan_disbursement'] ?? 0);
        $totalLoanRepayments = (float) ($totals['loan_payment'] ?? 0);
        $totalFees           = (float) ($totals['fee'] ?? 0);

        $totalExpenses = (float) Expense::query()
            ->whereBetween('expense_date', [$start, $end])
            ->sum('amount');

        $inflows  = $totalDeposits + $totalLoanRepayments + $totalFees;
        $outflows = $totalWithdrawals + $totalLoanReleases + $totalExpenses;

        return [
            'total_deposits'    => $totalDeposits,
            'total_withdrawals' => $totalWithdrawals,
            'total_expenses'    => $totalExpenses,
            'net'               => $inflows - $outflows,
        ];
    }

    public function render()
    {
        return view('livewire.content.overviews.treasurer-overview');
    }
}
