<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Support\PermissionCatalog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $role = $user->primary_role;

        return match ($role) {
            'admin' => $this->admin(),
            'manager' => $this->manager(),
            'accountant', 'treasurer' => $this->treasurer(),
            'secretary' => $this->secretary(),
            'loan' => $this->loan(),
            default => $this->member(),
        };
    }

    // ─── Member ────────────────────────────────────────────────

    private function member(): JsonResponse
    {
        $user = Auth::user()->load([
            'accounts',
            'loans.payments',
            'personalData',
        ]);

        $savingsAccount = $user->accounts->where('account_type', 'savings')->first();
        $shareCapitalAccount = $user->accounts->where('account_type', 'share-capital')->first();

        $totalSavings = $savingsAccount?->balance ?? 0;
        $totalShareCapital = $shareCapitalAccount?->balance ?? 0;

        $activeLoans = $user->loans->whereIn('loan_status', ['approved', 'active'])->values();

        $totalLoanBalance = $activeLoans->sum(function ($l) {
            $totalPaid = $l->payments->where('status', 'completed')->sum('amount');

            return max(0, round($l->totalPayable() - $totalPaid, 2));
        });

        $announcements = Announcement::query()
            ->with('creator.personalData')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'content' => $a->content,
                'type' => $a->type,
                'image_url' => $a->image_path ? asset('storage/'.$a->image_path) : null,
                'created_at' => $a->created_at->toISOString(),
                'created_at_diff' => $a->created_at->diffForHumans(),
                'creator_name' => trim(
                    ($a->creator->personalData->first_name ?? '').' '.
                    ($a->creator->personalData->last_name ?? '')
                ) ?: 'Unknown',
            ]);

        return response()->json([
            'greeting' => $this->greeting(),
            'user_number' => $user->user_number,
            'total_balance' => $user->accounts->sum('balance'),
            'total_savings' => $totalSavings,
            'total_share_capital' => $totalShareCapital,
            'total_loan_balance' => $totalLoanBalance,
            'savings_account' => $savingsAccount ? [
                'id' => $savingsAccount->id,
                'account_number' => $savingsAccount->account_number,
                'balance' => $savingsAccount->balance,
            ] : null,
            'share_capital_account' => $shareCapitalAccount ? [
                'id' => $shareCapitalAccount->id,
                'account_number' => $shareCapitalAccount->account_number,
                'balance' => $shareCapitalAccount->balance,
            ] : null,
            'accounts' => $user->accounts->map(fn ($a) => [
                'id' => $a->id,
                'account_number' => $a->account_number,
                'account_type' => $a->account_type,
                'balance' => $a->balance,
                'status' => $a->status,
            ]),
            'active_loans_count' => $activeLoans->count(),
            'loans' => $activeLoans->map(function ($l) {
                $totalPaid = $l->payments->where('status', 'completed')->sum('amount');
                $progress = $l->loan_amount > 0 ? round(($totalPaid / $l->loan_amount) * 100, 1) : 0;

                return [
                    'id' => $l->id,
                    'loan_number' => $l->loan_number,
                    'loan_type' => $l->loan_type,
                    'loan_amount' => $l->loan_amount,
                    'loan_status' => $l->loan_status,
                    'remaining_balance' => max(0, round($l->totalPayable() - $totalPaid, 2)),
                    'total_paid' => $totalPaid,
                    'progress' => $progress,
                    'due_date' => $l->due_date?->format('M d, Y'),
                    'due_date_raw' => $l->due_date?->toISOString(),
                    'interest_rate' => $l->interest_rate,
                    'term_months' => $l->term_months,
                    'payment_frequency' => $l->payment_frequency,
                ];
            }),
            'announcements' => $announcements,
        ]);
    }

    // ─── Admin ─────────────────────────────────────────────────

    private function admin(): JsonResponse
    {
        $now = now();
        $start = $now->copy()->startOfMonth();
        $end = $now->copy()->endOfMonth();

        $totalMembers = User::query()->memberUsers()->count();
        $totalStaff = User::query()->officerUsers()->count();
        $newMembersThisMonth = User::query()->memberUsers()->whereBetween('created_at', [$start, $end])->count();

        $totalSavings = (float) DB::table('accounts')->where('account_type', 'savings')->sum('balance');
        $totalShareCapital = (float) DB::table('accounts')->where('account_type', 'share-capital')->sum('balance');

        $financialThisMonth = $this->financialSummary($start, $end);

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

        $pendingTransactionsCount = Transaction::query()->where('status', 'pending')->count();

        $pendingTransactions = Transaction::query()
            ->with(['account.user.personalData'])
            ->where('status', 'pending')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'transaction_number' => $t->transaction_number,
                'transaction_type' => $t->transaction_type,
                'amount' => $t->amount,
                'status' => $t->status,
                'member_name' => trim(
                    ($t->account->user->personalData->first_name ?? '').' '.
                    ($t->account->user->personalData->last_name ?? '')
                ) ?: 'Unknown',
                'created_at' => $t->created_at->format('M d, Y'),
                'created_at_raw' => $t->created_at->toISOString(),
            ]);

        $recentExpenses = Expense::query()
            ->orderByDesc('expense_date')
            ->take(5)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'category' => $e->category,
                'amount' => $e->amount,
                'description' => $e->description,
                'expense_date' => $e->expense_date->format('M d, Y'),
                'expense_date_raw' => $e->expense_date->toISOString(),
            ]);

        $recentAnnouncements = Announcement::query()
            ->with('creator.personalData')
            ->latest()
            ->take(4)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'content' => $a->content,
                'type' => $a->type,
                'created_at_diff' => $a->created_at->diffForHumans(),
                'created_at' => $a->created_at->toISOString(),
                'creator_name' => trim(
                    ($a->creator->personalData->first_name ?? '').' '.
                    ($a->creator->personalData->last_name ?? '')
                ) ?: 'Unknown',
            ]);

        return response()->json([
            'greeting' => $this->greeting(),
            'period_label' => $start->format('M Y'),
            'total_members' => $totalMembers,
            'members_new_month' => $newMembersThisMonth,
            'total_officers' => $totalStaff,
            'staff_total' => $totalStaff,
            'total_savings' => $totalSavings,
            'savings_total' => $totalSavings,
            'total_share_capital' => $totalShareCapital,
            'share_capital_total' => $totalShareCapital,
            'pending_transactions' => $pendingTransactionsCount,
            'pending_loans' => $pendingLoansCount,
            'loans_active' => $activeLoansCount,
            'active_loans' => $activeLoansCount,
            'loans_outstanding' => $loanOutstanding,
            'loans_overdue' => $overdueLoansCount,
            'loans_due_soon' => $dueSoonLoansCount,
            'month_deposits' => $financialThisMonth['total_deposits'],
            'month_withdrawals' => $financialThisMonth['total_withdrawals'],
            'month_expenses' => $financialThisMonth['total_expenses'],
            'month_net' => $financialThisMonth['net'],
            'monthly_series' => $this->buildMonthlySeries(6),
            'monthly_max_value' => $this->monthlyMax,
            'loan_status_series' => $this->buildLoanStatusSeries(),
            'pending_transactions_list' => $pendingTransactions,
            'recent_expenses' => $recentExpenses,
            'recent_announcements' => $recentAnnouncements,
        ]);
    }

    // ─── Manager ───────────────────────────────────────────────

    private function manager(): JsonResponse
    {
        $now = now();
        $start = $now->copy()->startOfMonth();
        $end = $now->copy()->endOfMonth();

        $totalMembers = User::query()->memberUsers()->count();
        $newMembersThisMonth = User::query()->memberUsers()->whereBetween('created_at', [$start, $end])->count();
        $totalStaff = User::query()->officerUsers()->count();
        $totalSavings = (float) DB::table('accounts')->where('account_type', 'savings')->sum('balance');

        $activeLoanStatuses = ['approved', 'active'];
        $activeLoansCount = Loan::query()->whereIn('loan_status', $activeLoanStatuses)->count();
        $pendingLoansCount = Loan::query()->where('loan_status', 'pending')->count();

        $overdueLoansCount = Loan::query()
            ->whereNotIn('loan_status', ['paid', 'paid_off', 'paid-off'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->count();

        $financialThisMonth = $this->financialSummary($start, $end);
        $pendingTxnCount = Transaction::query()->where('status', 'pending')->count();

        $pendingTransactions = Transaction::query()
            ->with(['account.user.personalData'])
            ->where('status', 'pending')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'transaction_number' => $t->transaction_number,
                'transaction_type' => $t->transaction_type,
                'amount' => $t->amount,
                'member_name' => trim(
                    ($t->account->user->personalData->first_name ?? '').' '.
                    ($t->account->user->personalData->last_name ?? '')
                ) ?: 'Unknown',
                'created_at' => $t->created_at->format('M d, Y'),
            ]);

        $recentMembers = User::query()
            ->with('personalData')
            ->memberUsers()
            ->latest()
            ->take(6)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => trim(
                    ($m->personalData->first_name ?? '').' '.
                    ($m->personalData->last_name ?? '')
                ) ?: 'Unknown',
                'created_at' => $m->created_at->format('M d, Y'),
                'created_at_raw' => $m->created_at->toISOString(),
            ]);

        $recentAnnouncements = Announcement::query()
            ->with('creator.personalData')
            ->latest()
            ->take(4)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'content' => $a->content,
                'type' => $a->type,
                'created_at_diff' => $a->created_at->diffForHumans(),
            ]);

        return response()->json([
            'greeting' => $this->greeting(),
            'period_label' => $start->format('M Y'),
            'total_members' => $totalMembers,
            'members_new_month' => $newMembersThisMonth,
            'staff_total' => $totalStaff,
            'savings_total' => $totalSavings,
            'loans_active' => $activeLoansCount,
            'loans_pending' => $pendingLoansCount,
            'loans_overdue' => $overdueLoansCount,
            'pending_transactions' => $pendingTxnCount,
            'month_deposits' => $financialThisMonth['total_deposits'],
            'month_withdrawals' => $financialThisMonth['total_withdrawals'],
            'month_expenses' => $financialThisMonth['total_expenses'],
            'month_net' => $financialThisMonth['net'],
            'monthly_series' => $this->buildMonthlySeries(6),
            'monthly_max_value' => $this->monthlyMax,
            'pending_transactions_list' => $pendingTransactions,
            'recent_members' => $recentMembers,
            'recent_announcements' => $recentAnnouncements,
        ]);
    }

    // ─── Treasurer / Accountant ────────────────────────────────

    private function treasurer(): JsonResponse
    {
        $now = now();
        $start = $now->copy()->startOfMonth();
        $end = $now->copy()->endOfMonth();

        $totalSavings = (float) DB::table('accounts')->where('account_type', 'savings')->sum('balance');
        $totalShareCapital = (float) DB::table('accounts')->where('account_type', 'share-capital')->sum('balance');
        $financialThisMonth = $this->financialSummary($start, $end);

        $pendingTxnCount = Transaction::query()->where('status', 'pending')->count();

        $recentTransactions = Transaction::query()
            ->with(['account.user.personalData'])
            ->where('status', 'completed')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'transaction_number' => $t->transaction_number,
                'transaction_type' => $t->transaction_type,
                'amount' => $t->amount,
                'status' => $t->status,
                'member_name' => trim(
                    ($t->account->user->personalData->first_name ?? '').' '.
                    ($t->account->user->personalData->last_name ?? '')
                ) ?: 'Unknown',
                'transaction_date' => ($t->transaction_date ?? $t->created_at)->format('M d'),
                'created_at' => $t->created_at->toISOString(),
            ]);

        $recentExpenses = Expense::query()
            ->orderByDesc('expense_date')
            ->take(6)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'category' => $e->category,
                'amount' => $e->amount,
                'description' => $e->description,
                'expense_date' => $e->expense_date->format('M d, Y'),
            ]);

        $recentAnnouncements = Announcement::query()
            ->latest()
            ->take(3)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'content' => $a->content,
                'created_at_diff' => $a->created_at->diffForHumans(),
            ]);

        return response()->json([
            'greeting' => $this->greeting(),
            'period_label' => $start->format('M Y'),
            'total_savings' => $totalSavings,
            'savings_total' => $totalSavings,
            'total_share_capital' => $totalShareCapital,
            'share_capital_total' => $totalShareCapital,
            'total_assets' => $totalSavings + $totalShareCapital,
            'month_deposits' => $financialThisMonth['total_deposits'],
            'month_withdrawals' => $financialThisMonth['total_withdrawals'],
            'month_expenses' => $financialThisMonth['total_expenses'],
            'month_net' => $financialThisMonth['net'],
            'pending_transactions' => $pendingTxnCount,
            'monthly_series' => $this->buildMonthlySeries(6),
            'monthly_max_value' => $this->monthlyMax,
            'recent_transactions' => $recentTransactions,
            'recent_expenses' => $recentExpenses,
            'recent_announcements' => $recentAnnouncements,
        ]);
    }

    // ─── Secretary ─────────────────────────────────────────────

    private function secretary(): JsonResponse
    {
        $now = now();
        $start = $now->startOfMonth();
        $end = $now->endOfMonth();

        $memberQuery = User::query()->where('role', 'member');

        $recentMembers = (clone $memberQuery)
            ->with('personalData')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => trim(
                    ($m->personalData->first_name ?? '').' '.
                    ($m->personalData->last_name ?? '')
                ) ?: 'Unknown',
                'user_number' => $m->user_number,
                'account_status' => $m->account_status,
                'created_at' => $m->created_at->format('M d, Y'),
                'created_at_raw' => $m->created_at->toISOString(),
            ]);

        $recentAnnouncements = Announcement::query()
            ->with('creator.personalData')
            ->latest()
            ->take(6)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'content' => mb_substr($a->content, 0, 100),
                'type' => $a->type,
                'created_at_diff' => $a->created_at->diffForHumans(),
                'created_at' => $a->created_at->toISOString(),
                'creator_name' => trim(
                    ($a->creator->personalData->first_name ?? '').' '.
                    ($a->creator->personalData->last_name ?? '')
                ) ?: 'Unknown',
            ]);

        return response()->json([
            'greeting' => $this->greeting(),
            'period_label' => $start->format('M Y'),
            'total_members' => (clone $memberQuery)->count(),
            'members_total' => (clone $memberQuery)->count(),
            'new_this_month' => (clone $memberQuery)->whereBetween('created_at', [$start, $end])->count(),
            'members_new_month' => (clone $memberQuery)->whereBetween('created_at', [$start, $end])->count(),
            'members_active' => (clone $memberQuery)->where('account_status', 'active')->count(),
            'members_inactive' => (clone $memberQuery)->where('account_status', '!=', 'active')->count(),
            'total_announcements' => Announcement::query()->count(),
            'announcements_total' => Announcement::query()->count(),
            'announcements_this_month' => Announcement::query()->whereBetween('created_at', [$start, $end])->count(),
            'recent_members' => $recentMembers,
            'recent_announcements' => $recentAnnouncements,
        ]);
    }

    // ─── Loan ──────────────────────────────────────────────────

    private function loan(): JsonResponse
    {
        $activeLoanStatuses = ['approved', 'active'];
        $paidLoanStatuses = ['paid', 'paid_off', 'paid-off'];

        $pendingCount = Loan::query()->where('loan_status', 'pending')->count();
        $activeCount = Loan::query()->whereIn('loan_status', $activeLoanStatuses)->count();
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

        $pendingLoans = Loan::query()
            ->with(['user.personalData'])
            ->where('loan_status', 'pending')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'loan_number' => $l->loan_number,
                'loan_type' => $l->loan_type,
                'loan_amount' => $l->loan_amount,
                'member_name' => trim(
                    ($l->user->personalData->first_name ?? '').' '.
                    ($l->user->personalData->last_name ?? '')
                ) ?: 'Unknown',
                'created_at' => $l->created_at->format('M d, Y'),
            ]);

        $overdueLoans = Loan::query()
            ->with(['user.personalData'])
            ->whereNotIn('loan_status', $paidLoanStatuses)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->orderBy('due_date')
            ->take(6)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'loan_number' => $l->loan_number,
                'loan_type' => $l->loan_type,
                'loan_amount' => $l->loan_amount,
                'member_name' => trim(
                    ($l->user->personalData->first_name ?? '').' '.
                    ($l->user->personalData->last_name ?? '')
                ) ?: 'Unknown',
                'due_date' => $l->due_date?->format('M d, Y'),
                'due_date_raw' => $l->due_date?->toISOString(),
                'days_overdue' => $l->due_date ? max(0, (int) $l->due_date->diffInDays(today())) : 0,
            ]);

        $recentAnnouncements = Announcement::query()
            ->latest()
            ->take(3)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'content' => $a->content,
                'created_at_diff' => $a->created_at->diffForHumans(),
            ]);

        return response()->json([
            'greeting' => $this->greeting(),
            'loans_pending' => $pendingCount,
            'pending_loans' => $pendingCount,
            'loans_active' => $activeCount,
            'active_loans' => $activeCount,
            'loans_overdue' => $overdueCount,
            'overdue_loans' => $overdueCount,
            'loans_due_soon' => $dueSoonCount,
            'loans_rejected' => $rejectedCount,
            'loans_outstanding' => $loanOutstanding,
            'outstanding_amount' => $loanOutstanding,
            'loans_disbursed' => $totalLoanDisbursed,
            'loan_status_series' => $this->buildLoanStatusSeries(),
            'pending_loans_list' => $pendingLoans,
            'overdue_loans_list' => $overdueLoans,
            'recent_announcements' => $recentAnnouncements,
        ]);
    }

    // ─── Member Loans ──────────────────────────────────────────

    public function memberLoans(Request $request): JsonResponse
    {
        $actor = Auth::user();
        $targetUser = $actor;

        if ($request->filled('user_id') && (int) $request->query('user_id') !== (int) $actor->id) {
            abort_unless(
                $actor->hasRole('admin') || $actor->hasAnyRole(PermissionCatalog::officerRoles()),
                403,
                'You are not authorized to view another member\'s loans.'
            );
            $targetUser = User::findOrFail($request->query('user_id'));
        }

        $user = $targetUser->load(['loans.payments']);

        $loans = $user->loans()
            ->with(['payments'])
            ->latest()
            ->get()
            ->map(function ($l) {
                $totalPaid = $l->payments->sum('amount');
                $remaining = $l->remainingBalance();
                $progress = $l->loan_amount > 0 ? round(($totalPaid / $l->loan_amount) * 100, 1) : 0;

                $schedule = $l->paymentSchedule();

                return [
                    'id' => $l->id,
                    'loan_number' => $l->loan_number,
                    'loan_type' => $l->loan_type,
                    'loan_type_label' => ucwords(str_replace('_', ' ', $l->loan_type)),
                    'loan_amount' => $l->loan_amount,
                    'principal_amount' => $l->principal_amount ?? $l->loan_amount,
                    'interest_rate' => (float) ($l->interest_rate ?? 0),
                    'term_months' => (int) ($l->term_months ?? 0),
                    'payment_frequency' => $l->payment_frequency ?? 'monthly',
                    'loan_status' => $l->loan_status,
                    'remaining_balance' => $remaining,
                    'total_paid' => $totalPaid,
                    'progress' => $progress,
                    'due_date' => $l->due_date?->format('M d, Y'),
                    'due_date_raw' => $l->due_date?->toISOString(),
                    'applied_at' => $l->created_at->format('M d, Y'),
                    'approved_at' => $l->approved_at?->format('M d, Y'),
                    'approved_by' => $l->approvedBy?->personalData
                        ? trim(($l->approvedBy->personalData->first_name ?? '').' '.($l->approvedBy->personalData->last_name ?? ''))
                        : null,
                    'group_members' => $l->groupMembers->map(fn ($gm) => [
                        'name' => $gm->name ?? 'Unknown',
                        'status' => $gm->status ?? 'pending',
                    ])->values(),
                    'payment_schedule' => $schedule,
                    'completed_payments' => $l->payments->sortBy('payment_date')->values()->map(fn ($p, $idx) => [
                        'id' => $p->id,
                        'payment_number' => $idx + 1,
                        'transaction_number' => $p->transaction_number ?? ('PAY-'.$p->id),
                        'amount' => $p->amount,
                        'payment_date' => ($p->payment_date ?? $p->created_at)->format('M d, Y'),
                        'status' => $p->status ?? 'completed',
                    ]),
                    'estimated_interest' => $schedule ? $schedule[count($schedule) - 1]['total_interest'] ?? 0 : 0,
                    'estimated_total_repayment' => $schedule
                        ? ($schedule[count($schedule) - 1]['total_payment'] ?? 0)
                        : $l->totalPayable(),
                ];
            });

        return response()->json(['data' => $loans]);
    }

    // ─── Shared helpers ────────────────────────────────────────

    private float $monthlyMax = 0.0;

    private function greeting(): string
    {
        $hour = (int) now()->format('H');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    private function financialSummary(Carbon $start, Carbon $end): array
    {
        $totals = Transaction::query()
            ->where('status', 'completed')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('transaction_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNull('transaction_date')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            })
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

    private function buildMonthlySeries(int $months): array
    {
        $months = max(1, min(24, $months));
        $series = [];
        $this->monthlyMax = 0.0;
        $baseNow = now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = $baseNow->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $summary = $this->financialSummary($monthStart, $monthEnd);
            $dep = (float) ($summary['total_deposits'] ?? 0);
            $out = (float) ($summary['total_withdrawals'] ?? 0) + (float) ($summary['total_expenses'] ?? 0);
            $this->monthlyMax = max($this->monthlyMax, $dep, $out);

            $series[] = [
                'label' => $monthStart->format('M Y'),
                'deposits' => $dep,
                'withdrawals' => (float) ($summary['total_withdrawals'] ?? 0),
                'expenses' => (float) ($summary['total_expenses'] ?? 0),
                'net' => (float) ($summary['net'] ?? 0),
            ];
        }

        return $series;
    }

    private function buildLoanStatusSeries(): array
    {
        $statusCounts = Loan::query()
            ->select('loan_status', DB::raw('COUNT(*) as total'))
            ->groupBy('loan_status')
            ->pluck('total', 'loan_status')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $total = max(0, (int) array_sum($statusCounts));
        $preferredOrder = ['pending', 'approved', 'active', 'disbursed', 'paid', 'paid_off', 'paid-off', 'rejected'];
        $colorMap = [
            'pending' => 'bg-amber-500', 'approved' => 'bg-cyan-500', 'active' => 'bg-emerald-500',
            'disbursed' => 'bg-blue-500', 'paid' => 'bg-slate-500', 'paid_off' => 'bg-slate-500',
            'paid-off' => 'bg-slate-500', 'rejected' => 'bg-red-400',
        ];

        $seen = [];
        $ordered = [];
        foreach ($preferredOrder as $s) {
            if (isset($statusCounts[$s]) && ! isset($seen[$s])) {
                $ordered[] = $s;
                $seen[$s] = true;
            }
        }
        foreach (array_keys($statusCounts) as $s) {
            if (! isset($seen[$s])) {
                $ordered[] = $s;
                $seen[$s] = true;
            }
        }

        $series = [];
        foreach ($ordered as $status) {
            $count = (int) ($statusCounts[$status] ?? 0);
            $percent = $total > 0 ? round(($count / $total) * 100, 1) : 0.0;
            $series[] = [
                'status' => (string) $status,
                'label' => ucwords(str_replace(['_', '-'], ' ', (string) $status)),
                'count' => $count,
                'percent' => $percent,
                'color' => $colorMap[$status] ?? 'bg-slate-400',
            ];
        }

        return $series;
    }
}
