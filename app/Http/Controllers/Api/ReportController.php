<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\InterestDisbursement;
use App\Models\InterestDisbursementItem;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportWorkbook;
use App\Support\BranchScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function financial(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('reports.view'), 403);

        [$start, $end] = $this->resolveRange($request);
        $branchId = BranchScope::resolveBranchId(Auth::user(), $request->integer('branch_id'));

        $totals = $this->applyWindowToTransactions(
            $this->completedTransactions($branchId)->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'interest', 'fee']),
            $start,
            $end
        )
            ->selectRaw('transaction_type, SUM('.Transaction::netAmountExpression().') as total')
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
            ->get(['id', 'amount', 'loan_id', 'reversal_of_id']);

        $totalInterestEarned = 0.0;
        foreach ($payments as $payment) {
            if ($payment->loan) {
                $totalPayable = $payment->loan->totalPayable();
                $totalInterest = $payment->loan->totalInterest();
                if ($totalPayable > 0) {
                    $direction = $payment->reversal_of_id === null ? 1 : -1;
                    $totalInterestEarned += $direction * (float) $payment->amount * ($totalInterest / $totalPayable);
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

        $totalInterestCredits = (float) ($totals['interest'] ?? 0);
        $inflows = (float) ($totals['deposit'] ?? 0) + (float) ($totals['loan_payment'] ?? 0) + $totalInterestCredits + (float) ($totals['fee'] ?? 0);
        $outflows = (float) ($totals['withdrawal'] ?? 0) + (float) ($totals['loan_disbursement'] ?? 0) + $totalExpenses;

        return response()->json([
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'total_deposits' => (float) ($totals['deposit'] ?? 0),
            'total_withdrawals' => (float) ($totals['withdrawal'] ?? 0),
            'total_loan_releases' => (float) ($totals['loan_disbursement'] ?? 0),
            'total_loan_repayments' => (float) ($totals['loan_payment'] ?? 0),
            'total_interest_credits' => $totalInterestCredits,
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
            ->when($category !== '', fn ($q) => $q->where('category', $category));
        $expenseSorts = ['date' => 'expense_date', 'amount' => 'amount', 'category' => 'category', 'description' => 'description'];
        $query->orderBy($expenseSorts[$request->input('sort_by', 'date')] ?? 'expense_date', $request->input('sort_direction') === 'asc' ? 'asc' : 'desc')->orderBy('id');

        $paginator = $this->reportPagination($query, $request);

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
        $allowedTypes = ['all', 'deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'interest', 'fee'];
        $sortBy = $request->string('sort_by', 'date')->toString();
        $sortDirection = strtolower($request->string('sort_direction', 'desc')->toString());
        $sortColumns = [
            'date' => 'transaction_date',
            'amount' => 'amount',
            'type' => 'transaction_type',
            'reference' => 'transaction_number',
        ];

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

        if ($sortBy === 'member') {
            $query->orderByRaw('COALESCE(
                (SELECT pd.last_name FROM accounts a JOIN users u ON u.id = a.user_id JOIN personal_data pd ON pd.id = u.personal_data_id WHERE a.id = transactions.account_id),
                (SELECT pd.last_name FROM loans l JOIN users u ON u.id = l.user_id JOIN personal_data pd ON pd.id = u.personal_data_id WHERE l.id = transactions.loan_id)
            ) '.($sortDirection === 'asc' ? 'ASC' : 'DESC'));
        } else {
            $query->orderBy($sortColumns[$sortBy] ?? 'transaction_date', $sortDirection === 'asc' ? 'asc' : 'desc');
        }
        $query->orderByDesc('transactions.id');

        $total = (float) (clone $query)->reorder()->selectRaw('SUM('.Transaction::netAmountExpression().') as aggregate_total')->value('aggregate_total');
        $paginator = $this->reportPagination($query, $request);

        $items = collect($paginator->items())->map(function (Transaction $transaction) {
            $user = $transaction->account?->user ?? $transaction->loan?->user;

            return [
                'id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'transaction_type' => $transaction->transaction_type,
                'amount' => ($transaction->reversal_of_id === null ? 1 : -1) * (float) $transaction->amount,
                'transaction_date' => ($transaction->transaction_date ?? $transaction->created_at)->format('Y-m-d'),
                'member_name' => $user?->name ?: $user?->email,
            ];
        });

        return response()->json([
            ...$this->withPagination($items, $paginator),
            'total' => $total,
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
            ->orderBy('id', 'desc');
        $accounts = $this->reportPagination($accounts, $request, ['id', 'user_id', 'balance']);

        $accountIds = collect($accounts->items())->pluck('id')->filter()->values();

        $totals = collect();
        if ($accountIds->isNotEmpty()) {
            $totals = $this->applyWindowToTransactions(
                $this->completedTransactions($branchId)->whereIn('account_id', $accountIds),
                $start,
                $end
            )
                ->whereIn('transaction_type', ['deposit', 'withdrawal', 'interest'])
                ->selectRaw('account_id, transaction_type, SUM('.Transaction::netAmountExpression().') as total')
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
                'total_interest_credits' => (float) ($byAccount[$account->id]['interest'] ?? 0),
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
            $query->whereIn('loan_status', ['approved', 'active'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', Carbon::today());
        }

        $paginator = $this->reportPagination($query->orderByDesc('created_at'), $request);

        $items = collect($paginator->items())->map(function (Loan $loan) {
            $name = 'N/A';
            if ($loan->user?->personalData) {
                $name = trim(($loan->user->personalData->first_name ?? '').' '.($loan->user->personalData->last_name ?? ''));
            }

            $amountPaid = (float) $loan->totalPaid();
            $remaining = (float) $loan->remainingBalance();
            $isOverdue = in_array($loan->loan_status, ['approved', 'active'], true) && $loan->due_date && Carbon::parse($loan->due_date)->lt(Carbon::today()) && $remaining > 0;

            $status = match (true) {
                in_array($loan->loan_status, ['paid', 'paid_off'], true) => 'Completed',
                $isOverdue => 'Overdue',
                in_array($loan->loan_status, ['approved', 'active'], true) => 'Active',
                default => ucwords(str_replace('_', ' ', $loan->loan_status)),
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

        $paginator = $this->reportPagination($this->applyWindowToTransactions($query, $start, $end)->orderByDesc('transaction_date')->orderByDesc('id'), $request);

        $items = collect($paginator->items())->map(function (Transaction $transaction) {
            $pd = $transaction->loan?->user?->personalData;
            $name = $pd ? trim(($pd->first_name ?? '').' '.($pd->last_name ?? '')) : 'N/A';

            return [
                'member_name' => $name,
                'loan_number' => $transaction->loan?->loan_number ?? '—',
                'amount' => ($transaction->reversal_of_id === null ? 1 : -1) * (float) $transaction->amount,
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
            'branches' => Branch::query()
                ->when(! BranchScope::isUnrestricted(Auth::user()), fn ($q) => $q->whereKey(Auth::user()->branch_id))
                ->active()->orderBy('name')->get(['id', 'name']),
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
        abort_unless(Auth::user()?->can('reports.view') && Auth::user()?->can('reports.financial') && Auth::user()?->can('reports.export'), 403);

        $request->merge(['report_type' => 'balance_sheet', 'format' => 'pdf']);

        return $this->export($request);
    }

    public function members(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('members.view'), 403);

        [$start, $end] = $this->resolveRange($request);
        $branchId = BranchScope::resolveBranchId(Auth::user(), $request->integer('branch_id'));

        $query = BranchScope::applyToUsers(
            User::query()->memberUsers()->with(['branch', 'personalData', 'personalData.contact']),
            Auth::user(),
            $branchId
        )->whereBetween('users.created_at', [$start, $end]);

        $paginator = $query->orderByDesc('users.created_at')->paginate(10);

        $items = collect($paginator->items())->map(fn (User $user) => [
            'member_id' => $user->user_number,
            'name' => $user->name ?: $user->email,
            'email' => $user->email,
            'phone' => $user->personalData?->contact?->phone_no,
            'status' => ucfirst((string) ($user->account_status ?? 'active')),
            'branch' => $user->branch?->name,
            'joined_at' => $user->created_at?->format('Y-m-d'),
        ]);

        return response()->json($this->withPagination($items, $paginator));
    }

    public function officers(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('officers.view') || Auth::user()?->can('members.view'), 403);

        [$start, $end] = $this->resolveRange($request);
        $branchId = BranchScope::resolveBranchId(Auth::user(), $request->integer('branch_id'));

        $query = BranchScope::applyToUsers(
            User::query()->officerUsers()->with(['branch', 'personalData', 'personalData.contact']),
            Auth::user(),
            $branchId
        )->whereBetween('users.created_at', [$start, $end]);

        $paginator = $query->orderByDesc('users.created_at')->paginate(10);

        $items = collect($paginator->items())->map(fn (User $user) => [
            'officer_id' => $user->user_number,
            'name' => $user->name ?: $user->email,
            'email' => $user->email,
            'role' => ucfirst((string) ($user->primary_role ?: 'Officer')),
            'title' => $user->personalData?->title,
            'branch' => $user->branch?->name,
            'status' => ucfirst((string) ($user->account_status ?? 'active')),
            'joined_at' => $user->created_at?->format('Y-m-d'),
        ]);

        return response()->json($this->withPagination($items, $paginator));
    }

    public function interestDisbursements(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('interest.view'), 403);

        $this->resolveRange($request);
        $branchId = BranchScope::resolveBranchId(Auth::user(), $request->integer('branch_id'));

        $query = InterestDisbursement::query()
            ->with(['requestedBy.personalData', 'approvedBy.personalData'])
            ->when($branchId !== null, fn ($q) => $q->whereHas('items.user', fn ($u) => $u->where('users.branch_id', $branchId)))
            ->when($request->filled('account_type'), fn ($q) => $q->where('account_type', $request->string('account_type')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()));

        if ($request->filled('from') || $request->filled('to')) {
            [$start, $end] = $this->resolveRange($request);
            $query->whereBetween('interest_disbursements.created_at', [$start, $end]);
        }

        $scopedItems = fn ($q) => $q->when($branchId !== null, fn ($items) => $items->whereHas('user', fn ($users) => $users->where('users.branch_id', $branchId)));
        $query->withCount([
            'items as scoped_member_count' => fn ($q) => $scopedItems($q)->where('skipped', false),
            'items as scoped_skipped_count' => fn ($q) => $scopedItems($q)->where('skipped', true),
        ])->withSum(['items as scoped_total_amount' => fn ($q) => $scopedItems($q)->where('skipped', false)], 'amount');

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $paginator = $this->reportPagination($query->orderByDesc('created_at')->orderByDesc('id'), $request, ['*'], $perPage);

        $items = collect($paginator->items())->map(function (InterestDisbursement $batch) {
            return [
                'id' => $batch->id,
                'account_type' => $batch->account_type,
                'account_label' => $batch->account_type === 'share-capital' ? 'Share Capital' : 'Savings',
                'rate' => (float) $batch->rate,
                'interval' => $batch->interval,
                'period_label' => $batch->period_label,
                'member_count' => $batch->scoped_member_count,
                'skipped_count' => $batch->scoped_skipped_count,
                'total_amount' => (float) $batch->scoped_total_amount,
                'status' => $batch->status,
                'trigger' => $batch->trigger,
                'requested_by' => $this->personName($batch->requestedBy?->personalData) ?: $batch->requestedBy?->email,
                'approved_by' => $this->personName($batch->approvedBy?->personalData) ?: $batch->approvedBy?->email,
                'created_at' => $batch->created_at?->toIso8601String(),
            ];
        });

        return response()->json($this->withPagination($items, $paginator));
    }

    public function interestDisbursementDetail(Request $request, InterestDisbursement $interestDisbursement): JsonResponse
    {
        abort_unless(Auth::user()?->can('interest.view'), 403);

        $branchId = BranchScope::resolveBranchId(Auth::user(), $request->integer('branch_id'));
        abort_unless($branchId === null || $interestDisbursement->items()->whereHas('user', fn ($q) => $q->where('users.branch_id', $branchId))->exists(), 404);

        $query = $interestDisbursement->items()
            ->with(['user.personalData', 'account:id,account_number,account_type'])
            ->when($branchId !== null, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('users.branch_id', $branchId)))
            ->when($request->has('include_skipped') && ! $request->boolean('include_skipped'),
                fn ($q) => $q->where('skipped', false))
            ->orderByDesc('skipped')
            ->orderByDesc('amount');

        $scopedStats = $interestDisbursement->items()
            ->when($branchId !== null, fn ($q) => $q->whereHas('user', fn ($users) => $users->where('users.branch_id', $branchId)))
            ->selectRaw('SUM(CASE WHEN skipped = 0 THEN 1 ELSE 0 END) as credited, SUM(CASE WHEN skipped = 1 THEN 1 ELSE 0 END) as skipped_count, SUM(CASE WHEN skipped = 0 THEN amount ELSE 0 END) as total_amount')
            ->first();

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function (InterestDisbursementItem $item) {
            return [
                'id' => $item->id,
                'member_name' => $this->personName($item->user?->personalData) ?: ($item->user?->email ?? 'N/A'),
                'account_number' => $item->account?->account_number,
                'amount' => (float) $item->amount,
                'balance_before' => (float) $item->balance_before,
                'balance_after' => (float) $item->balance_after,
                'skipped' => (bool) $item->skipped,
                'skip_reason' => $item->skip_reason,
                'transaction_id' => $item->transaction_id,
                'created_at' => $item->created_at?->toIso8601String(),
            ];
        });

        return response()->json(array_merge(
            [
                'batch' => [
                    'id' => $interestDisbursement->id,
                    'account_type' => $interestDisbursement->account_type,
                    'account_label' => $interestDisbursement->account_type === 'share-capital' ? 'Share Capital' : 'Savings',
                    'rate' => (float) $interestDisbursement->rate,
                    'interval' => $interestDisbursement->interval,
                    'period_label' => $interestDisbursement->period_label,
                    'member_count' => (int) $scopedStats->credited,
                    'skipped_count' => (int) $scopedStats->skipped_count,
                    'total_amount' => (float) $scopedStats->total_amount,
                    'status' => $interestDisbursement->status,
                    'trigger' => $interestDisbursement->trigger,
                    'created_at' => $interestDisbursement->created_at?->toIso8601String(),
                ],
            ],
            $this->withPagination($items, $paginator)
        ));
    }

    public function export(Request $request)
    {
        $request->validate([
            'report_type' => ['required', 'string', 'in:financial_report,balance_sheet,member_savings,loans,payments,expenses,member_activity,members,officers,interest_disbursements'],
            'format' => ['sometimes', 'string', 'in:pdf,excel'],
        ]);
        $type = $request->string('report_type')->toString();
        $format = $request->string('format', 'pdf')->toString();

        $viewer = Auth::user();
        $allowed = $viewer?->can('reports.view') && $viewer?->can('reports.export') && match ($type) {
            'balance_sheet' => $viewer->can('reports.financial'),
            'members' => $viewer->can('members.view'),
            'officers' => $viewer->can('officers.view'),
            'interest_disbursements' => $viewer->can('interest.view'),
            default => true,
        };
        abort_unless($allowed, 403);

        [$start, $end] = $this->resolveRange($request);
        $branchId = BranchScope::resolveBranchId($viewer, $request->integer('branch_id'));
        $payload = $this->buildExportPayload($type, $request, $start, $end, $branchId);
        $payload = $this->prepareExport($payload, $request, $branchId);

        return $format === 'pdf'
            ? $this->downloadReportPdf($payload, $end)
            : $this->downloadReportExcel($payload, $end);
    }

    private function buildExportPayload(string $type, Request $request, Carbon $start, Carbon $end, ?int $branchId): array
    {
        $rangeLabel = $start->format('M d, Y').' - '.$end->format('M d, Y');
        $branchName = $branchId !== null ? Branch::find($branchId)?->name : null;

        $base = [
            'appName' => config('app.name', 'Cooperative Management System'),
            'branch_name' => $branchName,
            'range' => $rangeLabel,
        ];

        return match ($type) {
            'financial_report' => array_merge($base, $this->financialExportPayload($request, $rangeLabel)),
            'balance_sheet' => array_merge($base, $this->balanceSheetExportPayload($start, $end, $branchId)),
            'member_savings', 'loans', 'payments', 'expenses', 'member_activity', 'interest_disbursements' => array_merge($base, $this->tableExportPayload($type, $request, $rangeLabel)),
            'members' => array_merge($base, $this->membersExportPayload($start, $end, $branchId, $rangeLabel)),
            'officers' => array_merge($base, $this->officersExportPayload($start, $end, $branchId, $rangeLabel)),
        };
    }

    private function downloadReportPdf(array $payload, Carbon $end)
    {
        $wide = collect($payload['sections'])->contains(fn ($section) => count($section['columns']) > 6);
        $pdf = Pdf::loadView('reports.report-export-pdf', $payload)->setPaper('a4', $wide ? 'landscape' : 'portrait');
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 115, $canvas->get_height() - 25, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.4, 0.4, 0.4]);

        $filename = $payload['filename_slug'].'-'.$end->format('Y-m-d').'.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    private function downloadReportExcel(array $payload, Carbon $end)
    {
        $book = app(ReportWorkbook::class)->build($payload);
        $filename = $payload['filename_slug'].'-'.$end->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($book) {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function financialExportPayload(Request $request, string $rangeLabel): array
    {
        $financial = $this->financial($request)->getData(true);

        $summaryColumns = [
            ['key' => 'metric', 'label' => 'Metric'],
            ['key' => 'value', 'label' => 'Amount', 'align' => 'right'],
        ];
        $summaryRows = [
            ['Total Deposits', $this->money($financial['total_deposits'])],
            ['Total Withdrawals', $this->money($financial['total_withdrawals'])],
            ['Total Loan Releases', $this->money($financial['total_loan_releases'])],
            ['Total Loan Repayments', $this->money($financial['total_loan_repayments'])],
            ['Interest Credits', $this->money($financial['total_interest_credits'])],
            ['Total Interest Earned (Collected)', $this->money($financial['total_interest_earned'])],
            ['Projected Interest', $this->money($financial['projected_interest'])],
            ['Total Fees', $this->money($financial['total_fees'])],
            ['Total Expenses', $this->money($financial['total_expenses'])],
            ['Net Balance', $this->money($financial['net'])],
        ];

        $monthlyColumns = [
            ['key' => 'month', 'label' => 'Month'],
            ['key' => 'deposits', 'label' => 'Deposits', 'align' => 'right'],
            ['key' => 'interest_credits', 'label' => 'Interest Credits', 'align' => 'right'],
            ['key' => 'loan_repayments', 'label' => 'Loan Repayments', 'align' => 'right'],
            ['key' => 'loan_releases', 'label' => 'Loan Releases', 'align' => 'right'],
            ['key' => 'withdrawals', 'label' => 'Withdrawals', 'align' => 'right'],
            ['key' => 'fees', 'label' => 'Fees', 'align' => 'right'],
            ['key' => 'expenses', 'label' => 'Expenses', 'align' => 'right'],
            ['key' => 'net', 'label' => 'Net', 'align' => 'right'],
        ];
        $monthlyRows = collect($financial['monthly_series'] ?? [])->map(fn (array $m) => [
            $m['label'],
            $this->money($m['deposits']),
            $this->money($m['interest_credits']),
            $this->money($m['loan_repayments']),
            $this->money($m['loan_releases']),
            $this->money($m['withdrawals']),
            $this->money($m['fees']),
            $this->money($m['expenses']),
            $this->money($m['net']),
        ])->all();

        return [
            'key' => 'financial_report',
            'title' => 'Financial Summary Report',
            'subtitle' => $rangeLabel,
            'filename_slug' => 'financial-summary',
            'sections' => [
                ['title' => 'Period Totals', 'columns' => $summaryColumns, 'rows' => $summaryRows],
                ['title' => 'Monthly Breakdown', 'columns' => $monthlyColumns, 'rows' => $monthlyRows],
            ],
        ];
    }

    private function balanceSheetExportPayload(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $bs = $this->buildBalanceSheet($start, $end, $branchId);

        $sections = [];
        foreach (['Assets' => 'assets', 'Liabilities' => 'liabilities', 'Equity' => 'equity'] as $title => $key) {
            $rows = collect($bs[$key])->map(fn (array $line) => [$line['label'], $this->money($line['amount'])])->all();
            $rows[] = ['Total '.$title, $this->money($bs['totals'][$key])];
            $sections[] = [
                'title' => $title,
                'columns' => [
                    ['key' => 'item', 'label' => $title],
                    ['key' => 'amount', 'label' => 'Amount', 'align' => 'right'],
                ],
                'rows' => $rows,
            ];
        }

        $sections[2]['rows'][] = ['Total Liabilities and Equity', $this->money($bs['totals']['liabilities_and_equity'])];

        return [
            'key' => 'balance_sheet',
            'title' => 'Balance Sheet ? Management Report',
            'subtitle' => 'As of '.$end->format('F d, Y'),
            'filename_slug' => 'balance-sheet',
            'sections' => $sections,
        ];
    }

    private function membersExportPayload(Carbon $start, Carbon $end, ?int $branchId, string $rangeLabel): array
    {
        $rows = BranchScope::applyToUsers(
            User::query()->memberUsers()->with(['branch', 'personalData', 'personalData.contact']),
            Auth::user(),
            $branchId
        )
            ->whereBetween('users.created_at', [$start, $end])
            ->orderByDesc('users.created_at')
            ->get()
            ->map(function (User $user) {
                return [
                    $user->user_number,
                    $user->name ?: $user->email,
                    $user->email,
                    $user->personalData?->contact?->phone_no ?? '—',
                    ucfirst((string) ($user->account_status ?? 'active')),
                    $user->branch?->name ?? '—',
                    $user->created_at?->format('M d, Y') ?? '—',
                ];
            })->all();

        return $this->singleSectionPayload(
            'members',
            'Members Report',
            $rangeLabel,
            'member-report',
            'Members',
            [
                ['key' => 'id', 'label' => 'Member ID'],
                ['key' => 'name', 'label' => 'Full Name'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'phone', 'label' => 'Phone'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'branch', 'label' => 'Branch'],
                ['key' => 'joined', 'label' => 'Join Date'],
            ],
            $rows
        );
    }

    private function officersExportPayload(Carbon $start, Carbon $end, ?int $branchId, string $rangeLabel): array
    {
        $rows = BranchScope::applyToUsers(
            User::query()->officerUsers()->with(['branch', 'personalData', 'personalData.contact']),
            Auth::user(),
            $branchId
        )
            ->whereBetween('users.created_at', [$start, $end])
            ->orderByDesc('users.created_at')
            ->get()
            ->map(function (User $user) {
                return [
                    $user->user_number,
                    $user->name ?: $user->email,
                    $user->email,
                    ucfirst((string) ($user->primary_role ?: 'Officer')),
                    $user->personalData?->title ? ucwords(str_replace('_', ' ', $user->personalData->title)) : '—',
                    $user->branch?->name ?? '—',
                    ucfirst((string) ($user->account_status ?? 'active')),
                    $user->created_at?->format('M d, Y') ?? '—',
                ];
            })->all();

        return $this->singleSectionPayload(
            'officers',
            'Officers Report',
            $rangeLabel,
            'officer-report',
            'Officers',
            [
                ['key' => 'id', 'label' => 'Officer ID'],
                ['key' => 'name', 'label' => 'Full Name'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'role', 'label' => 'Role'],
                ['key' => 'title', 'label' => 'Title'],
                ['key' => 'branch', 'label' => 'Branch'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'joined', 'label' => 'Join Date'],
            ],
            $rows
        );
    }

    private function reportPagination(Builder $query, Request $request, array $columns = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        // Set only on an internal request after export authorization, never from user input.
        if ($request->attributes->get('report_export') === true) {
            $items = $query->get($columns);

            return new LengthAwarePaginator($items, $items->count(), max(1, $items->count()));
        }

        return $query->paginate($perPage, $columns);
    }

    private function tableExportPayload(string $type, Request $request, string $range): array
    {
        [$reader, $title, $fields] = match ($type) {
            'member_savings' => ['memberSavings', 'Member Savings', [
                ['member_name', 'Member', 'text'], ['total_deposits', 'Deposits', 'money'],
                ['total_withdrawals', 'Withdrawals', 'money'], ['total_interest_credits', 'Interest Credits', 'money'],
                ['current_balance', 'Current Savings Balance', 'money'],
            ]],
            'loans' => ['loanReport', 'Loan Register', [
                ['loan_number', 'Loan Number', 'text'], ['member_name', 'Member', 'text'],
                ['loan_amount', 'Principal', 'money'], ['interest_rate', 'Interest Rate', 'percent'],
                ['loan_date', 'Application Date', 'date'], ['due_date', 'Due Date', 'date'],
                ['amount_paid', 'Paid to Date', 'money'], ['remaining_balance', 'Current Remaining Balance', 'money'],
                ['status', 'Status', 'text'],
            ]],
            'payments' => ['payments', 'Loan Payment Collections', [
                ['member_name', 'Member', 'text'], ['loan_number', 'Loan Number', 'text'],
                ['amount', 'Net Payment', 'money'], ['payment_date', 'Payment Date', 'date'],
                ['remaining_balance', 'Current Loan Balance', 'money'],
            ]],
            'expenses' => ['expenses', 'Operating Expenses', [
                ['category', 'Category', 'text'], ['description', 'Description', 'text'],
                ['amount', 'Amount', 'money'], ['expense_date', 'Expense Date', 'date'], ['recorded_by', 'Recorded By', 'text'],
            ]],
            'member_activity' => ['memberActivity', 'Transaction History', [
                ['transaction_number', 'Reference', 'text'], ['member_name', 'Member', 'text'],
                ['transaction_type', 'Transaction Type', 'text'], ['amount', 'Net Amount', 'money'], ['transaction_date', 'Transaction Date', 'date'],
            ]],
            'interest_disbursements' => ['interestDisbursements', 'Interest Credits', [
                ['created_at', 'Batch Created', 'date'], ['account_label', 'Account', 'text'], ['period_label', 'Period', 'text'],
                ['rate', 'Rate', 'percent'], ['interval', 'Interval', 'text'], ['member_count', 'Members Credited', 'number'],
                ['skipped_count', 'Skipped', 'number'], ['total_amount', 'Amount Credited', 'money'],
                ['trigger', 'Trigger', 'text'], ['status', 'Status', 'text'],
            ]],
        };
        $exportRequest = $request->duplicate();
        $exportRequest->attributes->set('report_export', true);
        $data = $this->{$reader}($exportRequest)->getData(true);
        $columns = array_map(fn ($field) => ['key' => $field[0], 'label' => $field[1], 'type' => $field[2], 'align' => in_array($field[2], ['money', 'number', 'percent']) ? 'right' : 'left'], $fields);
        $rows = array_map(fn ($row) => array_map(fn ($field) => $row[$field[0]] ?? null, $fields), $data['data']);

        return $this->singleSectionPayload($type, $title, $range, str_replace('_', '-', $type), $title, $columns, $rows);
    }

    private function prepareExport(array $payload, Request $request, ?int $branchId): array
    {
        $filters = ['Branch: '.($branchId === null ? 'All permitted branches' : Branch::find($branchId)?->name)];
        foreach (['member_id' => 'Member ID', 'filter' => 'Loan status', 'category' => 'Category', 'activity_type' => 'Transaction type', 'account_type' => 'Account', 'status' => 'Status', 'sort_by' => 'Sort', 'sort_direction' => 'Direction'] as $key => $label) {
            if ($request->filled($key)) {
                $filters[] = $label.': '.$request->input($key);
            }
        }
        $payload['filter_summary'] = implode(' · ', $filters);
        $payload['generatedAt'] = now();
        $payload['generatedBy'] = Auth::user()?->name ?: Auth::user()?->email;
        $payload['note'] = match ($payload['key']) {
            'balance_sheet' => 'Management report using simplified ledger calculations. Cash is calculated through the selected end date; savings, share capital and loan balances are current. Retained earnings is a residual. This is not an audited or fully historical financial statement.',
            'member_savings' => 'Activity uses the selected transaction dates. Current savings balances are live balances, not historical closing balances.',
            'loans' => 'Loans are selected by application date. Paid and remaining amounts reflect current balances.',
            'payments' => 'Completed externally funded loan payments, net of reversals. Loan balances are current.',
            'financial_report' => 'Period totals use the selected dates. Monthly breakdown covers the trailing six months through the selected end month. Collected loan interest is estimated and shown separately from interest credits.',
            default => '',
        };
        foreach ($payload['sections'] as &$section) {
            foreach ($section['columns'] as &$column) {
                $column['type'] ??= ($column['align'] ?? '') === 'right' ? 'money' : 'text';
            }
            unset($column);
            if (in_array($payload['key'], ['member_savings', 'loans', 'payments', 'expenses', 'member_activity', 'interest_disbursements'])) {
                $section['totals'] = [];
                foreach ($section['columns'] as $index => $column) {
                    // A payment row repeats its loan's current balance; summing it would mislead.
                    if ($column['type'] === 'money' && ! ($payload['key'] === 'payments' && $column['key'] === 'remaining_balance')) {
                        $section['totals'][$index] = round(array_sum(array_column($section['rows'], $index)), 2);
                    }
                }
            }
        }
        unset($section);

        return $payload;
    }

    private function singleSectionPayload(string $key, string $title, string $subtitle, string $slug, string $sectionTitle, array $columns, array $rows): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'subtitle' => $subtitle,
            'filename_slug' => $slug,
            'sections' => [
                ['title' => $sectionTitle, 'columns' => $columns, 'rows' => $rows],
            ],
        ];
    }

    private function money(null|int|float|string $value): float
    {
        return round((float) $value, 2);
    }

    private function personName(?object $personalData): string
    {
        if (! $personalData) {
            return 'N/A';
        }

        return trim(($personalData->first_name ?? '').' '.($personalData->last_name ?? '')) ?: 'N/A';
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
            ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'interest', 'fee'])
            ->selectRaw('transaction_type, SUM('.Transaction::netAmountExpression().') as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $cashInflows = (float) ($totalsByType['deposit'] ?? 0) + (float) ($totalsByType['loan_payment'] ?? 0) + (float) ($totalsByType['interest'] ?? 0) + (float) ($totalsByType['fee'] ?? 0);
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
            ->selectRaw('SUM('.Transaction::netAmountExpression().') as aggregate_total')->value('aggregate_total');
        $netLoansReceivable = max(0, $loanPrincipalActive - $loanPaymentsOnActive);

        $accountTotals = $this->accountTotals($branchId);
        $memberSavingsLiability = $accountTotals['savings'];
        $shareCapital = $accountTotals['share_capital'];

        $totalAssets = $cashAndCashEquivalents + $netLoansReceivable;
        $totalLiabilities = $memberSavingsLiability;
        $totalEquity = $totalAssets - $totalLiabilities;
        $retainedEarnings = $totalEquity - $shareCapital;

        $priorTotalsByType = $this->completedTransactions($branchId)
            ->where(function ($q) use ($start) {
                $q->whereDate('transaction_date', '<', $start->toDateString())
                    ->orWhere(function ($q2) use ($start) {
                        $q2->whereNull('transaction_date')
                            ->whereDate('created_at', '<', $start->toDateString());
                    });
            })
            ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'interest', 'fee'])
            ->selectRaw('transaction_type, SUM('.Transaction::netAmountExpression().') as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $priorInflows = (float) ($priorTotalsByType['deposit'] ?? 0) + (float) ($priorTotalsByType['loan_payment'] ?? 0) + (float) ($priorTotalsByType['interest'] ?? 0) + (float) ($priorTotalsByType['fee'] ?? 0);
        $priorOutflows = (float) ($priorTotalsByType['withdrawal'] ?? 0) + (float) ($priorTotalsByType['loan_disbursement'] ?? 0);
        $priorExpenses = (float) Expense::query()
            ->when($branchId !== null, fn ($q) => $q->where('expenses.branch_id', $branchId))
            ->whereDate('expense_date', '<', $start->toDateString())
            ->sum('amount');
        $openingCashBalance = $priorInflows - $priorOutflows - $priorExpenses;

        return [
            'start' => $start,
            'end' => $end,
            'as_of' => $end,
            'branch_name' => $branchName,
            'opening_balance' => [
                'cash' => $openingCashBalance,
            ],
            'period_movement' => [
                'inflows' => $cashInflows - $priorInflows,
                'outflows' => $cashOutflows - $priorOutflows,
                'expenses' => $totalExpenses - $priorExpenses,
                'net_cash_flow' => $cashAndCashEquivalents - $openingCashBalance,
            ],
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
                $this->completedTransactions($branchId)->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'interest', 'fee']),
                $from,
                $to
            )
                ->selectRaw('transaction_type, SUM('.Transaction::netAmountExpression().') as total')
                ->groupBy('transaction_type')
                ->pluck('total', 'transaction_type');

            $expenses = (float) Expense::query()
                ->when($branchId !== null, fn ($q) => $q->where('expenses.branch_id', $branchId))
                ->whereBetween('expense_date', [$from, $to])
                ->sum('amount');

            $deposits = (float) ($totals['deposit'] ?? 0);
            $interestCredits = (float) ($totals['interest'] ?? 0);
            $withdrawals = (float) ($totals['withdrawal'] ?? 0);

            $series[] = [
                'label' => $month->format('M Y'),
                'deposits' => $deposits,
                'interest_credits' => $interestCredits,
                'loan_repayments' => (float) ($totals['loan_payment'] ?? 0),
                'loan_releases' => (float) ($totals['loan_disbursement'] ?? 0),
                'withdrawals' => $withdrawals,
                'fees' => (float) ($totals['fee'] ?? 0),
                'expenses' => $expenses,
                'net' => $deposits + $interestCredits + (float) ($totals['loan_payment'] ?? 0) + (float) ($totals['fee'] ?? 0) - $withdrawals - (float) ($totals['loan_disbursement'] ?? 0) - $expenses,
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
        $request->validate([
            'all_time' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'member_id' => ['nullable', 'integer', 'exists:users,id'],
            'filter' => ['nullable', 'in:all,active,completed,overdue'],
            'activity_type' => ['nullable', 'in:all,deposit,withdrawal,loan_disbursement,loan_payment,interest,fee'],
            'sort_by' => ['nullable', 'in:date,amount,type,member,reference,category,description'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'category' => ['nullable', 'string', 'max:255'],
            'account_type' => ['nullable', 'in:savings,share-capital'],
            'status' => ['nullable', 'in:pending,approved,processing,completed,failed,rejected,cancelled,reversed'],
        ]);
        $end = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();
        $start = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : ($request->boolean('all_time') ? Carbon::create(1000, 1, 1)->startOfDay() : $end->copy()->startOfMonth());
        if ($start->gt($end)) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }

        return [$start, $end];
    }
}
