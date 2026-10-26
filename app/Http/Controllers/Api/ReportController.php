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
            'total' => (float) $query->selectRaw('SUM('.Transaction::netAmountExpression().') as aggregate_total')->value('aggregate_total'),
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
        abort_unless(Auth::user()?->can('reports.financial') || Auth::user()?->can('reports.export'), 403);

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

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        $items = collect($paginator->items())->map(function (InterestDisbursement $batch) {
            return [
                'id' => $batch->id,
                'account_type' => $batch->account_type,
                'account_label' => $batch->account_type === 'share-capital' ? 'Share Capital' : 'Savings',
                'rate' => (float) $batch->rate,
                'interval' => $batch->interval,
                'period_label' => $batch->period_label,
                'member_count' => $batch->member_count,
                'skipped_count' => $batch->skipped_count,
                'total_amount' => (float) $batch->total_amount,
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
                    'member_count' => $interestDisbursement->member_count,
                    'skipped_count' => $interestDisbursement->skipped_count,
                    'total_amount' => (float) $interestDisbursement->total_amount,
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
        $type = $request->string('report_type')->toString();
        $format = $request->string('format', 'pdf')->toString();

        if (! in_array($format, ['pdf', 'excel'], true)) {
            abort(422, 'Invalid export format.');
        }

        $allowedTypes = ['financial_report', 'balance_sheet', 'member_savings', 'loans', 'payments', 'expenses', 'member_activity', 'members', 'officers', 'interest_disbursements'];
        if (! in_array($type, $allowedTypes, true)) {
            abort(422, 'Invalid report type.');
        }

        $viewer = Auth::user();
        $canExport = $viewer?->can('reports.export') ?? false;
        $allowed = match ($type) {
            'balance_sheet' => $canExport || $viewer?->can('reports.financial'),
            'members' => $canExport || $viewer?->can('members.view'),
            'officers' => $canExport || $viewer?->can('officers.view') || $viewer?->can('members.view'),
            'interest_disbursements' => $canExport || $viewer?->can('interest.view'),
            default => $canExport || $viewer?->can('reports.view'),
        };
        abort_unless($allowed, 403);

        [$start, $end] = $this->resolveRange($request);
        $branchId = BranchScope::resolveBranchId($viewer, $request->integer('branch_id'));
        $payload = $this->buildExportPayload($type, $request, $start, $end, $branchId);

        return $format === 'pdf'
            ? $this->downloadReportPdf($payload, $end)
            : $this->downloadReportExcel($payload);
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
            'member_savings' => array_merge($base, $this->memberSavingsExportPayload($request, $start, $end, $branchId, $rangeLabel)),
            'loans' => array_merge($base, $this->loansExportPayload($request, $start, $end, $branchId, $rangeLabel)),
            'payments' => array_merge($base, $this->paymentsExportPayload($start, $end, $branchId, $rangeLabel)),
            'expenses' => array_merge($base, $this->expensesExportPayload($request, $start, $end, $branchId, $rangeLabel)),
            'member_activity' => array_merge($base, $this->memberActivityExportPayload($request, $start, $end, $branchId, $rangeLabel)),
            'members' => array_merge($base, $this->membersExportPayload($start, $end, $branchId, $rangeLabel)),
            'officers' => array_merge($base, $this->officersExportPayload($start, $end, $branchId, $rangeLabel)),
            'interest_disbursements' => array_merge($base, $this->interestDisbursementsExportPayload($request, $start, $end, $branchId, $rangeLabel)),
        };
    }

    private function downloadReportPdf(array $payload, Carbon $end)
    {
        $pdf = Pdf::loadView('reports.report-export-pdf', $payload + [
            'generatedAt' => now(),
            'generatedBy' => Auth::user(),
        ]);

        $filename = $payload['filename_slug'].'-'.$end->format('Y-m-d').'.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    private function downloadReportExcel(array $payload)
    {
        $xml = $this->buildReportSpreadsheetXml($payload);

        $filename = $payload['filename_slug'].'-'.now()->format('Y-m-d').'.xls';

        return response()->streamDownload(function () use ($xml) {
            echo $xml;
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
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
            'title' => 'Balance Sheet',
            'subtitle' => 'As of '.$end->format('F d, Y'),
            'filename_slug' => 'balance-sheet',
            'sections' => $sections,
        ];
    }

    private function memberSavingsExportPayload(Request $request, Carbon $start, Carbon $end, ?int $branchId, string $rangeLabel): array
    {
        $memberId = $request->integer('member_id');

        $accounts = Account::query()
            ->where('account_type', 'savings')
            ->whereNotNull('user_id')
            ->when($memberId, fn ($q) => $q->where('user_id', $memberId))
            ->when($branchId !== null, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('users.branch_id', $branchId)))
            ->with('user.personalData')
            ->orderBy('id', 'desc')
            ->get(['id', 'user_id', 'balance']);

        $accountIds = $accounts->pluck('id')->filter()->values();

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

        $rows = $accounts->map(function (Account $account) use ($byAccount) {
            return [
                $this->personName($account->user?->personalData),
                $this->money($byAccount[$account->id]['deposit'] ?? 0),
                $this->money($byAccount[$account->id]['interest'] ?? 0),
                $this->money($byAccount[$account->id]['withdrawal'] ?? 0),
                $this->money($account->balance),
            ];
        })->values()->all();

        return $this->singleSectionPayload(
            'member_savings',
            'Member Savings Report',
            $rangeLabel,
            'member-savings',
            'Member Savings',
            [
                ['key' => 'member', 'label' => 'Member'],
                ['key' => 'deposits', 'label' => 'Total Deposits', 'align' => 'right'],
                ['key' => 'interest_credits', 'label' => 'Interest Credits', 'align' => 'right'],
                ['key' => 'withdrawals', 'label' => 'Total Withdrawals', 'align' => 'right'],
                ['key' => 'balance', 'label' => 'Current Savings Balance', 'align' => 'right'],
            ],
            $rows
        );
    }

    private function loansExportPayload(Request $request, Carbon $start, Carbon $end, ?int $branchId, string $rangeLabel): array
    {
        $loanFilter = $request->string('filter', 'all')->toString();
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

        $rows = $query->orderByDesc('created_at')->get()->map(function (Loan $loan) {
            $remaining = (float) $loan->remainingBalance();
            $isOverdue = $loan->due_date && Carbon::parse($loan->due_date)->isPast() && $remaining > 0;
            $status = match (true) {
                in_array($loan->loan_status, ['paid', 'paid_off'], true) || $remaining <= 0 => 'Completed',
                $isOverdue => 'Overdue',
                default => 'Active',
            };

            return [
                $this->personName($loan->user?->personalData),
                $this->money($loan->loan_amount),
                number_format((float) $loan->interest_rate, 2).'%',
                ($loan->applied_at ?? $loan->created_at)->format('M d, Y'),
                $loan->due_date ? Carbon::parse($loan->due_date)->format('M d, Y') : '—',
                $this->money($loan->totalPaid()),
                $this->money($remaining),
                $status,
            ];
        })->all();

        return $this->singleSectionPayload(
            'loans',
            'Loan Report',
            $rangeLabel,
            'loan-report',
            'Loans',
            [
                ['key' => 'member', 'label' => 'Member'],
                ['key' => 'amount', 'label' => 'Loan Amount', 'align' => 'right'],
                ['key' => 'interest', 'label' => 'Interest', 'align' => 'right'],
                ['key' => 'date', 'label' => 'Loan Date'],
                ['key' => 'due', 'label' => 'Due Date'],
                ['key' => 'paid', 'label' => 'Paid', 'align' => 'right'],
                ['key' => 'remaining', 'label' => 'Remaining Balance', 'align' => 'right'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            $rows
        );
    }

    private function paymentsExportPayload(Carbon $start, Carbon $end, ?int $branchId, string $rangeLabel): array
    {
        $query = $this->completedTransactions($branchId)
            ->with('loan.user.personalData')
            ->where('transaction_type', 'loan_payment');

        $rows = $this->applyWindowToTransactions($query, $start, $end)
            ->orderByDesc('transaction_date')
            ->get()
            ->map(function (Transaction $transaction) {
                return [
                    $this->personName($transaction->loan?->user?->personalData),
                    $transaction->loan?->loan_number ?? '—',
                    $this->money($transaction->amount),
                    ($transaction->transaction_date ?? $transaction->created_at)->format('M d, Y'),
                    $this->money($transaction->loan?->remainingBalance() ?? 0),
                ];
            })->all();

        return $this->singleSectionPayload(
            'payments',
            'Payment Collection Report',
            $rangeLabel,
            'payment-collection',
            'Payment Collection',
            [
                ['key' => 'member', 'label' => 'Member'],
                ['key' => 'loan', 'label' => 'Loan ID'],
                ['key' => 'amount', 'label' => 'Payment Amount', 'align' => 'right'],
                ['key' => 'date', 'label' => 'Payment Date'],
                ['key' => 'remaining', 'label' => 'Remaining Balance', 'align' => 'right'],
            ],
            $rows
        );
    }

    private function expensesExportPayload(Request $request, Carbon $start, Carbon $end, ?int $branchId, string $rangeLabel): array
    {
        $category = $request->string('category')->toString();

        $rows = Expense::query()
            ->with('recordedBy.personalData')
            ->whereBetween('expense_date', [$start, $end])
            ->when($branchId !== null, fn ($q) => $q->where('expenses.branch_id', $branchId))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->orderByDesc('expense_date')
            ->get()
            ->map(function (Expense $expense) {
                return [
                    $expense->category,
                    $expense->description ?? '—',
                    $this->money($expense->amount),
                    $expense->expense_date?->format('M d, Y') ?? '—',
                    $expense->recordedBy?->personalData ? $this->personName($expense->recordedBy->personalData) : '—',
                ];
            })->all();

        return $this->singleSectionPayload(
            'expenses',
            'Expense Report',
            $rangeLabel,
            'expense-report',
            'Expenses',
            [
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'description', 'label' => 'Description'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'right'],
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'recorded', 'label' => 'Recorded By'],
            ],
            $rows
        );
    }

    private function memberActivityExportPayload(Request $request, Carbon $start, Carbon $end, ?int $branchId, string $rangeLabel): array
    {
        $activityType = $request->string('activity_type', 'all')->toString();
        $memberId = $request->integer('member_id');
        $allowedTypes = ['all', 'deposit', 'withdrawal', 'loan_disbursement', 'loan_payment', 'fee'];

        $query = $this->completedTransactions($branchId)
            ->with(['account.user.personalData', 'loan.user.personalData'])
            ->where(function ($builder) {
                $builder->whereHas('account.user')->orWhereHas('loan.user');
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

        $typeLabels = [
            'deposit' => 'Deposit',
            'withdrawal' => 'Withdrawal',
            'loan_disbursement' => 'Loan Disbursement',
            'loan_payment' => 'Loan Payment',
            'fee' => 'Fee',
        ];

        $rows = $query->latest('transaction_date')->get()->map(function (Transaction $transaction) use ($typeLabels) {
            $user = $transaction->account?->user ?? $transaction->loan?->user;

            return [
                $user?->name ?: ($user?->email ?? 'N/A'),
                $typeLabels[$transaction->transaction_type] ?? ucwords(str_replace('_', ' ', (string) $transaction->transaction_type)),
                $this->money($transaction->amount),
                ($transaction->transaction_date ?? $transaction->created_at)->format('M d, Y'),
                $transaction->transaction_number,
            ];
        })->all();

        return $this->singleSectionPayload(
            'member_activity',
            'Member Activity Report',
            $rangeLabel,
            'member-activity',
            'Member Activity',
            [
                ['key' => 'member', 'label' => 'Member'],
                ['key' => 'type', 'label' => 'Transaction Type'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'right'],
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'ref', 'label' => 'Reference'],
            ],
            $rows
        );
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

    private function interestDisbursementsExportPayload(Request $request, Carbon $start, Carbon $end, ?int $branchId, string $rangeLabel): array
    {
        $accountType = $request->string('account_type')->toString();

        $rows = InterestDisbursement::query()
            ->when($branchId !== null, fn ($q) => $q->whereHas('items.user', fn ($u) => $u->where('users.branch_id', $branchId)))
            ->when($accountType !== '', fn ($q) => $q->where('account_type', $accountType))
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (InterestDisbursement $batch) {
                return [
                    $batch->created_at?->format('M d, Y H:i') ?? '—',
                    $batch->account_type === 'share-capital' ? 'Share Capital' : 'Savings',
                    (string) $batch->period_label,
                    rtrim(rtrim(number_format((float) $batch->rate, 4, '.', ''), '0'), '.').'% / '.$batch->interval,
                    (string) $batch->member_count,
                    (string) $batch->skipped_count,
                    $this->money($batch->total_amount),
                    ucfirst((string) $batch->trigger),
                    ucfirst((string) $batch->status),
                ];
            })->all();

        return $this->singleSectionPayload(
            'interest_disbursements',
            'Interest Disbursements Report',
            $rangeLabel,
            'interest-disbursements',
            'Interest Batches',
            [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'account', 'label' => 'Account'],
                ['key' => 'period', 'label' => 'Period'],
                ['key' => 'rate', 'label' => 'Rate'],
                ['key' => 'members', 'label' => 'Members', 'align' => 'right'],
                ['key' => 'skipped', 'label' => 'Skipped', 'align' => 'right'],
                ['key' => 'total', 'label' => 'Total', 'align' => 'right'],
                ['key' => 'trigger', 'label' => 'Trigger'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            $rows
        );
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

    private function buildReportSpreadsheetXml(array $payload): string
    {
        $worksheets = collect($payload['sections'])->map(function (array $section) {
            $header = '<Row>'.collect($section['columns'])
                ->map(fn (array $column) => $this->excelCell($column['label']))
                ->implode('').'</Row>';

            $body = collect($section['rows'])
                ->map(fn (array $row) => '<Row>'.collect($row)
                    ->map(fn (string $value) => $this->excelCell($value))
                    ->implode('').'</Row>')
                ->implode('');

            return '<Worksheet ss:Name="'.$this->worksheetName($section['title']).'"><Table>'.$header.$body.'</Table></Worksheet>';
        })->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<?mso-application progid="Excel.Sheet"?>'
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:o="urn:schemas-microsoft-com:office:office"'
            .' xmlns:x="urn:schemas-microsoft-com:office:excel"'
            .' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:html="http://www.w3.org/TR/REC-html40">'
            .$worksheets
            .'</Workbook>';
    }

    private function excelCell(string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return '<Cell><Data ss:Type="String">'.$escaped.'</Data></Cell>';
    }

    private function worksheetName(string $title): string
    {
        $name = str_replace(' ', '_', trim((string) preg_replace('/[^A-Za-z0-9 _\-]/', '', $title)));

        return mb_substr($name !== '' ? $name : 'Report', 0, 31);
    }

    private function money(null|int|float|string $value): string
    {
        return 'PHP '.number_format((float) $value, 2);
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
