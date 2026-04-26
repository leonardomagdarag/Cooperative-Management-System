<?php

namespace App\Livewire\Content;

use App\Models\Account;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Reports extends Component
{
    use WithPagination;

    public string $selectedReport = 'financial';

    /**
     * Shared date filters across reports.
     * rangePreset: day|week|month|year|custom
     */
    public string $rangePreset = 'month';

    public ?string $startDate = null; // Y-m-d

    public ?string $endDate = null;   // Y-m-d

    // Per-report filters
    public ?int $memberId = null;

    public string $loanFilter = 'all'; // all|active|completed|overdue

    public ?string $expenseCategory = null;

    public string $activityType = 'all'; // all|deposit|withdrawal|loan_disbursement|loan_payment|fee

    protected $queryString = [
        'selectedReport' => ['except' => 'financial'],
        'rangePreset' => ['except' => 'month'],
        'startDate' => ['except' => null],
        'endDate' => ['except' => null],
        'memberId' => ['except' => null],
        'loanFilter' => ['except' => 'all'],
        'expenseCategory' => ['except' => null],
        'activityType' => ['except' => 'all'],
    ];

    public array $reports = [
        'financial' => 'Financial Summary',
        'member_savings' => 'Member Savings',
        'loans' => 'Loan Report',
        'payments' => 'Payment Collection',
        'expenses' => 'Expense Report',
        'member_activity' => 'Member Activity',
    ];

    public array $rangePresets = [
        'day' => 'Daily',
        'week' => 'Weekly',
        'month' => 'Monthly',
        'year' => 'Yearly',
        'custom' => 'Custom Range',
    ];

    public function mount(): void
    {
        abort_unless(Auth::check() && Auth::user()->can('reports.view'), 403);

        if ($this->canViewBalanceSheet) {
            $this->reports['balance_sheet'] = 'Balance Sheet';
        } elseif ($this->selectedReport === 'balance_sheet') {
            $this->selectedReport = 'financial';
        }

        // Default custom range to current month to avoid empty inputs.
        if (! $this->startDate || ! $this->endDate) {
            $this->startDate = Carbon::now()->startOfMonth()->toDateString();
            $this->endDate = Carbon::now()->endOfMonth()->toDateString();
        }
    }

    public function updatingSelectedReport(string $value): void
    {
        if ($value === 'balance_sheet' && ! $this->canViewBalanceSheet) {
            $this->selectedReport = 'financial';
        }

        $this->resetPage();
    }

    public function updatingRangePreset(): void
    {
        $this->resetPage();
    }

    public function updatingStartDate(): void
    {
        $this->resetPage();
    }

    public function updatingEndDate(): void
    {
        $this->resetPage();
    }

    public function updatingMemberId(): void
    {
        $this->resetPage();
    }

    public function updatingLoanFilter(): void
    {
        $this->resetPage();
    }

    public function updatingExpenseCategory(): void
    {
        $this->resetPage();
    }

    public function updatingActivityType(): void
    {
        $this->resetPage();
    }

    public function getDateWindowProperty(): array
    {
        return $this->resolveDateWindow($this->rangePreset, $this->startDate, $this->endDate);
    }

    private function resolveDateWindow(string $preset, ?string $startDate, ?string $endDate): array
    {
        return match ($preset) {
            'day' => [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()],
            'week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'year' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            'custom' => [
                Carbon::parse($startDate ?: Carbon::now()->startOfMonth()->toDateString())->startOfDay(),
                Carbon::parse($endDate ?: Carbon::now()->endOfMonth()->toDateString())->endOfDay(),
            ],
            default => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
        };
    }

    /**
     * Applies a date window to transactions.
     * Prefers transaction_date but falls back to created_at for legacy rows.
     */
    private function applyWindowToTransactionsQuery(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('transaction_date', [$start, $end])
                ->orWhere(function ($q2) use ($start, $end) {
                    $q2->whereNull('transaction_date')
                        ->whereBetween('created_at', [$start, $end]);
                });
        });
    }

    private function completedTransactionsQuery(): Builder
    {
        return Transaction::query()->where('status', 'completed');
    }

    public function getCanViewBalanceSheetProperty(): bool
    {
        $user = Auth::user();

        return (bool) $user?->hasRole('admin');
    }

    public function getFinancialSummaryProperty(): array
    {
        [$start, $end] = $this->dateWindow;

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

        // Expenses are stored separately in expenses table.
        $expenseQuery = Expense::query()->whereBetween('expense_date', [$start, $end]);
        $totalExpenses = (float) $expenseQuery->sum('amount');

        // Expected interest earned (theoretical) for loans created/applied within the selected window.
        $loans = Loan::query()
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('applied_at', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNull('applied_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->get(['loan_amount', 'interest_rate', 'term_months']);

        $totalInterestEarned = (float) $loans->sum(function (Loan $loan) {
            $principal = (float) $loan->loan_amount;
            $annualRate = (float) $loan->interest_rate / 100;
            $months = (int) ($loan->term_months ?: 0);

            if ($principal <= 0 || $annualRate <= 0 || $months <= 0) {
                return 0;
            }

            // Simple interest approximation over term.
            return $principal * $annualRate * ($months / 12);
        });

        // Cashflow-like net for the period.
        $inflows = $totalDeposits + $totalLoanRepayments + $totalFees;
        $outflows = $totalWithdrawals + $totalLoanReleases + $totalExpenses;
        $net = $inflows - $outflows;

        return [
            'start' => $start,
            'end' => $end,
            'total_deposits' => $totalDeposits,
            'total_withdrawals' => $totalWithdrawals,
            'total_loan_releases' => $totalLoanReleases,
            'total_loan_repayments' => $totalLoanRepayments,
            'total_interest_earned' => $totalInterestEarned,
            'total_fees' => $totalFees,
            'total_expenses' => $totalExpenses,
            'net' => $net,
        ];
    }

    public function getFinancialChartsDataProperty(): array
    {
        $summary = $this->financialSummary;

        $inflows = (float) ($summary['total_deposits'] ?? 0)
            + (float) ($summary['total_loan_repayments'] ?? 0)
            + (float) ($summary['total_fees'] ?? 0);

        $outflows = (float) ($summary['total_withdrawals'] ?? 0)
            + (float) ($summary['total_loan_releases'] ?? 0)
            + (float) ($summary['total_expenses'] ?? 0);

        return [
            'inflows' => $inflows,
            'outflows' => $outflows,
            'totalsByType' => [
                ['key' => 'deposit', 'label' => 'Deposits', 'value' => (float) ($summary['total_deposits'] ?? 0)],
                ['key' => 'withdrawal', 'label' => 'Withdrawals', 'value' => (float) ($summary['total_withdrawals'] ?? 0)],
                ['key' => 'loan_disbursement', 'label' => 'Loan Releases', 'value' => (float) ($summary['total_loan_releases'] ?? 0)],
                ['key' => 'loan_payment', 'label' => 'Loan Repayments', 'value' => (float) ($summary['total_loan_repayments'] ?? 0)],
                ['key' => 'fee', 'label' => 'Fees', 'value' => (float) ($summary['total_fees'] ?? 0)],
                ['key' => 'expense', 'label' => 'Expenses', 'value' => (float) ($summary['total_expenses'] ?? 0)],
            ],
        ];
    }

    public function getBalanceSheetDataProperty(): array
    {
        [$start, $end] = $this->dateWindow;

        $upToEndQuery = $this->completedTransactionsQuery()
            ->where(function ($q) use ($end) {
                $q->whereDate('transaction_date', '<=', $end->toDateString())
                    ->orWhere(function ($q2) use ($end) {
                        $q2->whereNull('transaction_date')
                            ->whereDate('created_at', '<=', $end->toDateString());
                    });
            });

        $totalsByType = (clone $upToEndQuery)
            ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'fee'])
            ->selectRaw('transaction_type, SUM(amount) as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $cashInflows = (float) ($totalsByType['deposit'] ?? 0)
            + (float) ($totalsByType['loan_payment'] ?? 0)
            + (float) ($totalsByType['fee'] ?? 0);

        $cashOutflows = (float) ($totalsByType['withdrawal'] ?? 0)
            + (float) ($totalsByType['loan_disbursement'] ?? 0);

        $totalExpenses = (float) Expense::query()
            ->whereDate('expense_date', '<=', $end->toDateString())
            ->sum('amount');

        $cashAndCashEquivalents = $cashInflows - $cashOutflows - $totalExpenses;

        $activeLoanStatuses = ['approved', 'active'];
        $loanPrincipalActive = (float) Loan::query()
            ->whereIn('loan_status', $activeLoanStatuses)
            ->sum('loan_amount');

        $loanPaymentsOnActive = (float) Transaction::query()
            ->where('transaction_type', 'loan_payment')
            ->where('status', 'completed')
            ->whereHas('loan', fn($q) => $q->whereIn('loan_status', $activeLoanStatuses))
            ->sum('amount');

        $netLoansReceivable = max(0, $loanPrincipalActive - $loanPaymentsOnActive);

        $memberSavingsLiability = (float) Account::query()
            ->where('account_type', 'savings')
            ->sum('balance');

        $shareCapital = (float) Account::query()
            ->whereIn('account_type', ['share-capital', 'share_capital'])
            ->sum('balance');

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

    public function downloadBalanceSheetPdf()
    {
        abort_unless($this->canViewBalanceSheet, 403);

        $balanceSheet = $this->balanceSheetData;
        $pdf = Pdf::loadView('reports.balance-sheet-pdf', [
            'balanceSheet' => $balanceSheet,
            'appName' => config('app.name', 'Cooperative Management System'),
            'generatedAt' => now(),
            'generatedBy' => Auth::user(),
        ]);

        $filename = 'balance-sheet-' . $balanceSheet['as_of']->format('Y-m-d') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function getMembersProperty()
    {
        return User::query()
            ->where('role', 'member')
            ->with('personalData')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getExpenseCategoriesProperty(): array
    {
        return Expense::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->toArray();
    }

    public function getMemberSavingsRowsProperty()
    {
        [$start, $end] = $this->dateWindow;

        $accounts = Account::query()
            ->where('account_type', 'savings')
            ->whereNotNull('user_id')
            ->when($this->memberId, fn($q) => $q->where('user_id', $this->memberId))
            ->with(['user.personalData'])
            ->orderBy('id', 'desc')
            ->paginate(10, ['id', 'user_id', 'balance']);

        $accountIds = $accounts->getCollection()->pluck('id');

        $baseTx = $this->applyWindowToTransactionsQuery(
            $this->completedTransactionsQuery()->whereIn('account_id', $accountIds),
            $start,
            $end
        );

        $totalsByAccount = (clone $baseTx)
            ->whereIn('transaction_type', ['deposit', 'withdrawal'])
            ->selectRaw('account_id, transaction_type, SUM(amount) as total')
            ->groupBy('account_id', 'transaction_type')
            ->get();

        $byAccount = [];
        foreach ($totalsByAccount as $row) {
            $byAccount[$row->account_id][$row->transaction_type] = (float) $row->total;
        }

        $accounts->getCollection()->transform(function (Account $account) use ($byAccount) {
            $depositTotal = (float) ($byAccount[$account->id]['deposit'] ?? 0);
            $withdrawalTotal = (float) ($byAccount[$account->id]['withdrawal'] ?? 0);

            $name = 'N/A';
            if ($account->user?->personalData) {
                $name = trim($account->user->personalData->first_name . ' ' . $account->user->personalData->last_name);
            }

            return [
                'member_name' => $name,
                'total_deposits' => $depositTotal,
                'total_withdrawals' => $withdrawalTotal,
                'current_balance' => (float) $account->balance,
            ];
        });

        return $accounts;
    }

    public function getLoanRowsProperty()
    {
        [$start, $end] = $this->dateWindow;

        $query = Loan::query()
            ->with(['user.personalData'])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('applied_at', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNull('applied_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            });

        $completedStatuses = ['paid', 'paid_off'];

        if ($this->loanFilter === 'active') {
            $query->whereIn('loan_status', ['approved', 'active']);
        } elseif ($this->loanFilter === 'completed') {
            $query->whereIn('loan_status', $completedStatuses);
        } elseif ($this->loanFilter === 'overdue') {
            $query->whereNotIn('loan_status', $completedStatuses)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', Carbon::today());
        }

        $loans = $query->orderBy('created_at', 'desc')->paginate(10);

        // Attach computed fields per row.
        $loans->getCollection()->transform(function (Loan $loan) {
            $name = 'N/A';
            if ($loan->user?->personalData) {
                $name = trim($loan->user->personalData->first_name . ' ' . $loan->user->personalData->last_name);
            }

            $amountPaid = (float) $loan->totalPaid();
            $remaining = (float) $loan->remainingBalance();
            $isOverdue = $loan->due_date && Carbon::parse($loan->due_date)->isPast() && $remaining > 0;

            $statusLabel = match (true) {
                in_array($loan->loan_status, ['paid', 'paid_off'], true) || $remaining <= 0 => 'Completed',
                $isOverdue => 'Overdue',
                default => 'Active',
            };

            return [
                'member_name' => $name,
                'loan_amount' => (float) $loan->loan_amount,
                'interest_rate' => (float) $loan->interest_rate,
                'loan_date' => $loan->applied_at ?: $loan->created_at,
                'due_date' => $loan->due_date,
                'amount_paid' => $amountPaid,
                'remaining_balance' => $remaining,
                'status' => $statusLabel,
                'loan_number' => $loan->loan_number,
            ];
        });

        return $loans;
    }

    public function getPaymentRowsProperty()
    {
        [$start, $end] = $this->dateWindow;

        $query = Transaction::query()
            ->with(['loan.user.personalData'])
            ->where('transaction_type', 'loan_payment')
            ->where('status', 'completed');

        $query = $this->applyWindowToTransactionsQuery($query, $start, $end);

        return $query->orderBy('transaction_date', 'desc')->paginate(10);
    }

    public function getExpenseRowsProperty()
    {
        [$start, $end] = $this->dateWindow;

        return Expense::query()
            ->with(['recordedBy.personalData'])
            ->whereBetween('expense_date', [$start, $end])
            ->when($this->expenseCategory, fn($q) => $q->where('category', $this->expenseCategory))
            ->orderBy('expense_date', 'desc')
            ->paginate(10);
    }

    public function getMemberActivityRowsProperty()
    {
        [$start, $end] = $this->dateWindow;

        $query = Transaction::query()
            ->with(['account.user.personalData', 'loan.user.personalData'])
            ->where('status', 'completed');

        $query = $this->applyWindowToTransactionsQuery($query, $start, $end);

        if ($this->memberId) {
            $query->where(function ($q) {
                $q->whereHas('account', fn($a) => $a->where('user_id', $this->memberId))
                    ->orWhereHas('loan', fn($l) => $l->where('user_id', $this->memberId));
            });
        }

        if ($this->activityType !== 'all') {
            $query->where('transaction_type', $this->activityType);
        }

        return $query->orderBy('transaction_date', 'desc')->paginate(10);
    }

    public function render()
    {
        $financialCharts = $this->financialChartsData;

        if ($this->selectedReport === 'financial') {
            $this->dispatch('reports:financial-charts', data: $financialCharts);
        }

        return view('livewire.content.reports', [
            'financialSummary' => $this->financialSummary,
            'financialCharts' => $financialCharts,
            'canViewBalanceSheet' => $this->canViewBalanceSheet,
            'balanceSheetData' => $this->canViewBalanceSheet ? $this->balanceSheetData : null,
            'members' => $this->members,
            'expenseCategories' => $this->expenseCategories,
            'memberSavingsRows' => $this->memberSavingsRows,
            'loanRows' => $this->loanRows,
            'paymentRows' => $this->paymentRows,
            'expenseRows' => $this->expenseRows,
            'memberActivityRows' => $this->memberActivityRows,
        ]);
    }
}
