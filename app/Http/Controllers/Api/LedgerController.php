<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BranchScope;
use App\Support\SearchFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LedgerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('transactions.view'), 403);

        $query = Transaction::with(['branch', 'account.user.personalData', 'loan.user.personalData', 'recordedBy']);

        BranchScope::applyToTransactions($query, Auth::user(), $request->input('branch_id'));

        // Category filter
        $category = $request->string('category')->toString();
        if ($category !== '' && $category !== 'all') {
            $query->where('transaction_type', $category);
        }

        // Search
        if ($search = SearchFilter::escapeLike($request->string('search')->toString())) {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('transaction_type', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('account.user.personalData', fn ($sub) => $sub->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('account.user', fn ($sub) => $sub->where('user_number', 'like', "%{$search}%"))
                    ->orWhereHas('loan.user.personalData', fn ($sub) => $sub->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('loan', fn ($sub) => $sub->where('loan_number', 'like', "%{$search}%"));
            });
        }

        // Sort
        $sortField = $request->string('sort', 'created_at')->toString();
        $sortDirection = $request->string('direction', 'desc')->toString();
        $allowedSorts = ['created_at', 'transaction_number', 'amount', 'status', 'transaction_type'];
        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $perPage = max(5, min((int) $request->integer('per_page', 10), 50));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($t) => [
                'id' => $t->id,
                'transaction_number' => $t->transaction_number,
                'transaction_type' => $t->transaction_type,
                'amount' => $t->amount,
                'status' => $t->status,
                'note' => $t->note,
                'transaction_date' => $t->transaction_date?->format('Y-m-d'),
                'account_holder' => $t->account?->user ? trim(($t->account->user->personalData->first_name ?? '').' '.($t->account->user->personalData->last_name ?? '')) : null,
                'user_number' => $t->account?->user?->user_number,
                'loan_number' => $t->loan?->loan_number,
                'branch_id' => $t->branch_id,
                'branch_name' => $t->branch?->name,
                'recorded_by' => $t->recordedBy ? trim(($t->recordedBy->personalData->first_name ?? '').' '.($t->recordedBy->personalData->last_name ?? '')) : null,
                'created_at' => $t->created_at->toISOString(),
            ]),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'can_edit' => Auth::user()?->can('transactions.update'),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('transactions.view'), 403);

        $range = $request->string('range', 'today')->toString();

        $transactionCountsQuery = Transaction::query()->where('status', 'completed')
            ->applyRange($range)
            ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_payment', 'loan_disbursement'])
            ->selectRaw('transaction_type, COUNT(*) as count')
            ->groupBy('transaction_type');

        BranchScope::applyToTransactions($transactionCountsQuery, Auth::user(), $request->input('branch_id'));

        $transactionCounts = $transactionCountsQuery->pluck('count', 'transaction_type');

        $loanCount = BranchScope::applyToLoans(
            Loan::query()->applyRange($range),
            Auth::user(),
            $request->input('branch_id')
        )->count();

        return response()->json([
            'range' => $range,
            'deposits' => (int) ($transactionCounts['deposit'] ?? 0),
            'withdrawals' => (int) ($transactionCounts['withdrawal'] ?? 0),
            'loan_payments' => (int) ($transactionCounts['loan_payment'] ?? 0),
            'loan_disbursements' => (int) ($transactionCounts['loan_disbursement'] ?? 0),
            'loan_count' => (int) $loanCount,
        ]);
    }

    public function export(Request $request)
    {
        abort_unless(Auth::user()?->can('transactions.export') || Auth::user()?->can('transactions.view'), 403);

        $query = Transaction::with(['account.user.personalData', 'loan', 'recordedBy']);

        BranchScope::applyToTransactions($query, Auth::user(), $request->input('branch_id'));

        $category = $request->string('category')->toString();
        if ($category !== '' && $category !== 'all') {
            $query->where('transaction_type', $category);
        }

        if ($search = SearchFilter::escapeLike($request->string('search')->toString())) {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('transaction_type', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('account.user.personalData', fn ($sub) => $sub->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('account.user', fn ($sub) => $sub->where('user_number', 'like', "%{$search}%"))
                    ->orWhereHas('loan', fn ($sub) => $sub->where('loan_number', 'like', "%{$search}%"));
            });
        }

        $transactions = $query->orderByDesc('created_at')->get();

        $filename = 'ledger-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($transactions) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Transaction #', 'Type', 'Amount', 'Status', 'Mode', 'Member', 'User #', 'Loan #', 'Note', 'Date', 'Recorded By']);

            foreach ($transactions as $t) {
                fputcsv($out, [
                    $t->transaction_number,
                    $t->transaction_type,
                    $t->amount,
                    $t->status,
                    $t->transaction_mode,
                    $t->account?->user ? trim(($t->account->user->personalData->first_name ?? '').' '.($t->account->user->personalData->last_name ?? '')) : '',
                    $t->account?->user?->user_number,
                    $t->loan?->loan_number,
                    $t->note,
                    $t->transaction_date?->format('Y-m-d'),
                    $t->recordedBy ? trim(($t->recordedBy->personalData->first_name ?? '').' '.($t->recordedBy->personalData->last_name ?? '')) : '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('transactions.create') || Auth::user()?->can('transactions.update'), 403);

        $validated = $request->validate([
            'transaction_type' => 'required|in:deposit,withdrawal,loan_payment,loan_disbursement,fee',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:500',
            'account_id' => 'required_if:transaction_type,deposit,withdrawal|nullable|exists:accounts,id',
            'loan_id' => 'required_if:transaction_type,loan_payment,loan_disbursement|nullable|exists:loans,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $transaction = DB::transaction(function () use ($validated) {
            $transaction = Transaction::create([
                'transaction_type' => $validated['transaction_type'],
                'amount' => $validated['amount'],
                'note' => $validated['note'] ?? null,
                'account_id' => $validated['account_id'] ?? null,
                'loan_id' => $validated['loan_id'] ?? null,
                'branch_id' => $validated['branch_id'] ?? $this->resolveTransactionBranch($validated),
                'transaction_date' => now(),
                // Ledger entries are posted immediately below, so they must
                // never enter the pending approval flow as well.
                'status' => 'completed',
                'recorded_by' => Auth::id(),
            ]);

            $this->applyBalanceEffect($transaction);

            return $transaction;
        });

        return response()->json(['message' => 'Transaction created.', 'data' => $transaction], 201);
    }

    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        abort_unless(Auth::user()?->can('transactions.update'), 403);

        $validated = $request->validate([
            'transaction_type' => 'required|in:deposit,withdrawal,loan_payment,loan_disbursement,fee',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:500',
            'account_id' => 'nullable|exists:accounts,id',
            'loan_id' => 'nullable|exists:loans,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        DB::transaction(function () use ($transaction, $validated) {
            // Reverse the previous balance effect before applying the new one.
            $this->applyBalanceEffect($transaction, reverse: true);

            $transaction->update([
                'transaction_type' => $validated['transaction_type'],
                'amount' => $validated['amount'],
                'note' => $validated['note'] ?? null,
                'account_id' => $validated['account_id'] ?? null,
                'loan_id' => $validated['loan_id'] ?? null,
                'branch_id' => $validated['branch_id'] ?? $this->resolveTransactionBranch($validated),
            ]);

            $this->applyBalanceEffect($transaction->fresh());
        });

        return response()->json(['message' => 'Transaction updated.', 'data' => $transaction->fresh()]);
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        abort_unless(Auth::user()?->can('transactions.void') || Auth::user()?->can('transactions.update'), 403);

        DB::transaction(function () use ($transaction) {
            $this->applyBalanceEffect($transaction, reverse: true);
            $transaction->delete();
        });

        return response()->json(['message' => 'Transaction deleted.']);
    }

    /**
     * Default a new transaction to the target member's branch, falling back to
     * the recording officer's branch when no account/loan is involved.
     */
    private function resolveTransactionBranch(array $validated): ?int
    {
        if (! empty($validated['account_id'])) {
            $userId = Account::whereKey($validated['account_id'])->value('user_id');
            $branchId = $userId ? User::whereKey($userId)->value('branch_id') : null;
            if ($branchId) {
                return (int) $branchId;
            }
        }

        if (! empty($validated['loan_id'])) {
            $branchId = Loan::whereKey($validated['loan_id'])->value('branch_id');
            if ($branchId) {
                return (int) $branchId;
            }
        }

        return Auth::user()?->branch_id;
    }

    /**
     * Keep account balances in sync with manual ledger entries.
     * Deposits and loan disbursements credit the account; withdrawals and
     * loan payments debit it.
     */
    private function applyBalanceEffect(Transaction $transaction, bool $reverse = false): void
    {
        if (! $transaction->account_id) {
            return;
        }

        $effect = match ($transaction->transaction_type) {
            'deposit', 'loan_disbursement' => (float) $transaction->amount,
            'withdrawal', 'loan_payment' => -(float) $transaction->amount,
            default => 0.0,
        };

        if ($effect == 0.0) {
            return;
        }

        if ($reverse) {
            $effect = -$effect;
        }

        $account = Account::whereKey($transaction->account_id)->lockForUpdate()->first();

        if (! $account) {
            return;
        }

        if ($effect < 0 && (float) $account->balance < abs($effect)) {
            abort(422, 'Insufficient balance to record this transaction.');
        }

        $account->increment('balance', $effect);
    }
}
