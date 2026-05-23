<?php

namespace App\Livewire\Content\Overviews;

use App\Models\Announcement;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class LoanOverview extends Component
{
    public array $kpis = [];
    public array $loanStatusSeries = [];
    public array $loanStatusCounts = [];
    public Collection $pendingLoans;
    public Collection $overdueLoans;
    public Collection $recentAnnouncements;
    public string $greeting = '';

    public function mount(): void
    {
        abort_unless(Auth::check() && Auth::user()->hasAnyRole(['admin', 'manager', 'loan']), 403);

        $this->pendingLoans       = new Collection();
        $this->overdueLoans       = new Collection();
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
        $activeLoanStatuses = ['approved', 'active'];
        $paidLoanStatuses   = ['paid', 'paid_off', 'paid-off'];

        $pendingCount  = Loan::query()->where('loan_status', 'pending')->count();
        $activeCount   = Loan::query()->whereIn('loan_status', $activeLoanStatuses)->count();
        $rejectedCount = Loan::query()->where('loan_status', 'rejected')->count();

        $overdueCount = Loan::query()
            ->whereNotIn('loan_status', $paidLoanStatuses)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->count();

        $dueSoonCount = Loan::query()
            ->whereIn('loan_status', $activeLoanStatuses)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [today(), today()->copy()->addDays(14)])
            ->count();

        $loanPrincipalActive = (float) Loan::query()
            ->whereIn('loan_status', $activeLoanStatuses)
            ->sum('loan_amount');

        $loanPaymentsOnActive = (float) DB::table('transactions')
            ->join('loans', 'transactions.loan_id', '=', 'loans.id')
            ->where('transactions.transaction_type', 'loan_payment')
            ->where('transactions.status', 'completed')
            ->whereIn('loans.loan_status', $activeLoanStatuses)
            ->sum('transactions.amount');

        $loanOutstanding = max(0, $loanPrincipalActive - $loanPaymentsOnActive);

        $totalLoanDisbursed = (float) Loan::query()
            ->whereIn('loan_status', array_merge($activeLoanStatuses, $paidLoanStatuses))
            ->sum('loan_amount');

        $this->loanStatusCounts = Loan::query()
            ->select('loan_status', DB::raw('COUNT(*) as total'))
            ->groupBy('loan_status')
            ->pluck('total', 'loan_status')
            ->map(fn($v) => (int) $v)
            ->toArray();

        $this->buildLoanStatusSeries();

        $this->pendingLoans = Loan::query()
            ->with(['user.personalData', 'product'])
            ->where('loan_status', 'pending')
            ->latest()
            ->take(8)
            ->get();

        $this->overdueLoans = Loan::query()
            ->with(['user.personalData'])
            ->whereNotIn('loan_status', $paidLoanStatuses)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->orderBy('due_date')
            ->take(6)
            ->get();

        $this->recentAnnouncements = Announcement::query()
            ->latest()
            ->take(3)
            ->get();

        $this->kpis = [
            'loans_pending'     => $pendingCount,
            'loans_active'      => $activeCount,
            'loans_overdue'     => $overdueCount,
            'loans_due_soon'    => $dueSoonCount,
            'loans_rejected'    => $rejectedCount,
            'loans_outstanding' => $loanOutstanding,
            'loans_disbursed'   => $totalLoanDisbursed,
        ];
    }

    private function buildLoanStatusSeries(): void
    {
        $total = max(0, (int) array_sum($this->loanStatusCounts));
        $preferredOrder = ['pending', 'approved', 'active', 'disbursed', 'paid', 'paid_off', 'paid-off', 'rejected'];
        $colorMap = [
            'pending'   => 'bg-amber-500',
            'approved'  => 'bg-cyan-500',
            'active'    => 'bg-emerald-500',
            'disbursed' => 'bg-blue-500',
            'paid'      => 'bg-slate-500',
            'paid_off'  => 'bg-slate-500',
            'paid-off'  => 'bg-slate-500',
            'rejected'  => 'bg-red-400',
        ];

        $seen = [];
        $orderedStatuses = [];
        foreach ($preferredOrder as $s) {
            if (isset($this->loanStatusCounts[$s]) && !isset($seen[$s])) {
                $orderedStatuses[] = $s;
                $seen[$s] = true;
            }
        }
        foreach (array_keys($this->loanStatusCounts) as $s) {
            if (!isset($seen[$s])) {
                $orderedStatuses[] = $s;
                $seen[$s] = true;
            }
        }

        $series = [];
        foreach ($orderedStatuses as $status) {
            $count   = (int) ($this->loanStatusCounts[$status] ?? 0);
            $percent = $total > 0 ? round(($count / $total) * 100, 1) : 0.0;
            $series[] = [
                'status'  => (string) $status,
                'label'   => ucwords(str_replace(['_', '-'], ' ', (string) $status)),
                'count'   => $count,
                'percent' => (float) $percent,
                'color'   => $colorMap[$status] ?? 'bg-slate-400',
            ];
        }

        $this->loanStatusSeries = $series;
    }

    public function render()
    {
        return view('livewire.content.overviews.loan-overview');
    }
}
