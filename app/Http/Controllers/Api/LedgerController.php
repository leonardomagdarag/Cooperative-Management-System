<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CountersignService;
use App\Services\SecurityOtpService;
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

        $query = Transaction::with(['branch', 'account.user.personalData', 'loan.user.personalData', 'recordedBy', 'reversalOf']);

        BranchScope::applyToTransactions($query, Auth::user(), $request->input('branch_id'));

        // Category filter
        $category = $request->string('category')->toString();
        if ($category !== '' && $category !== 'all') {
            $query->where('transaction_type', $category);
        }

        if ($request->boolean('corrections')) {
            $query->whereNotNull('reversal_of_id');
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
                'account_id' => $t->account_id,
                'loan_id' => $t->loan_id,
                'reversal_of_id' => $t->reversal_of_id,
                'reversal_of_transaction_number' => $t->reversalOf?->transaction_number,
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

        $query = Transaction::with(['account.user.personalData', 'loan', 'recordedBy', 'reversalOf']);

        BranchScope::applyToTransactions($query, Auth::user(), $request->input('branch_id'));

        $category = $request->string('category')->toString();
        if ($category !== '' && $category !== 'all') {
            $query->where('transaction_type', $category);
        }

        if ($request->boolean('corrections')) {
            $query->whereNotNull('reversal_of_id');
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
            fputcsv($out, ['Transaction #', 'Type', 'Amount', 'Status', 'Mode', 'Member', 'User #', 'Loan #', 'Reversal Of', 'Note', 'Date', 'Recorded By']);

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
                    $t->reversalOf?->transaction_number,
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
        $user = Auth::user();
        abort_unless($user?->can('transactions.create') || $user?->can('transactions.update'), 403);

        $validated = $request->validate([
            'transaction_type' => 'required|in:deposit,withdrawal,loan_payment,loan_disbursement,fee',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:500',
            'account_id' => 'required_if:transaction_type,deposit,withdrawal|nullable|exists:accounts,id',
            'loan_id' => 'required_if:transaction_type,loan_payment,loan_disbursement|nullable|exists:loans,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $type = $validated['transaction_type'];
        if ($type === 'deposit') {
            abort_unless($user?->can('deposits.create'), 403, 'Missing deposits.create permission.');
        }
        if ($type === 'withdrawal') {
            abort_unless($user?->can('withdrawals.create'), 403, 'Missing withdrawals.create permission.');
        }
        if (in_array($type, ['loan_payment', 'loan_disbursement'])) {
            abort_unless($user?->can('loans.create') || $user?->can('loans.encode') || $user?->can('loans.update'), 403, 'Missing loan management permission.');
        }

        $transaction = Transaction::create([
            'transaction_type' => $validated['transaction_type'],
            'amount' => $validated['amount'],
            'note' => $validated['note'] ?? null,
            'account_id' => $validated['account_id'] ?? null,
            'loan_id' => $validated['loan_id'] ?? null,
            'branch_id' => $validated['branch_id'] ?? $this->resolveTransactionBranch($validated),
            'transaction_date' => now(),
            'status' => 'pending',
            'recorded_by' => Auth::id(),
        ]);

        return response()->json(['message' => 'Transaction created.', 'data' => $transaction], 201);
    }

    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user?->can('transactions.update'), 403);

        if ($transaction->status !== 'pending') {
            abort(409, 'Only pending transactions can be edited.');
        }

        $validated = $request->validate([
            'transaction_type' => 'required|in:deposit,withdrawal,loan_payment,loan_disbursement,fee',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:500',
            'account_id' => 'nullable|exists:accounts,id',
            'loan_id' => 'nullable|exists:loans,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $type = $validated['transaction_type'];
        if ($type === 'deposit') {
            abort_unless($user?->can('deposits.create') || $user?->can('deposits.approve'), 403, 'Missing deposits permission.');
        }
        if ($type === 'withdrawal') {
            abort_unless($user?->can('withdrawals.create') || $user?->can('withdrawals.approve'), 403, 'Missing withdrawals permission.');
        }
        if (in_array($type, ['loan_payment', 'loan_disbursement'])) {
            abort_unless($user?->can('loans.create') || $user?->can('loans.encode') || $user?->can('loans.update') || $user?->can('loans.approve'), 403, 'Missing loan management permission.');
        }

        // Edits are privileged: they never mutate directly. A countersign
        // request is recorded and the change is applied only after an active
        // chairman with oversight.approve countersigns it (see CountersignService).
        $pending = app(CountersignService::class)->request(
            'transaction.update',
            [
                'transaction_id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'requested_by' => $user->id,
                'changes' => [
                    'transaction_type' => $validated['transaction_type'],
                    'amount' => $validated['amount'],
                    'note' => $validated['note'] ?? null,
                    'account_id' => $validated['account_id'] ?? null,
                    'loan_id' => $validated['loan_id'] ?? null,
                    'branch_id' => $validated['branch_id'] ?? $this->resolveTransactionBranch($validated),
                ],
            ],
            $user
        );

        app(AuditLogService::class)->log(
            'transaction.update_requested',
            'Transaction edit submitted for chairman approval.',
            'transaction',
            $transaction,
            $user,
            ['request_id' => $pending->id]
        );

        return response()->json([
            'message' => "Transaction {$transaction->transaction_number} edit is awaiting chairman approval.",
            'request_id' => $pending->id,
            'status' => 'pending',
        ], 202);
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user?->can('transactions.update'), 403);

        $type = $transaction->transaction_type;
        if ($type === 'deposit') {
            abort_unless($user?->can('deposits.create') || $user?->can('deposits.approve'), 403, 'Missing deposits permission.');
        }
        if ($type === 'withdrawal') {
            abort_unless($user?->can('withdrawals.create') || $user?->can('withdrawals.approve'), 403, 'Missing withdrawals permission.');
        }
        if (in_array($type, ['loan_payment', 'loan_disbursement'])) {
            abort_unless($user?->can('loans.create') || $user?->can('loans.encode') || $user?->can('loans.update') || $user?->can('loans.approve'), 403, 'Missing loan management permission.');
        }

        if ($transaction->status !== 'pending') {
            abort(409, 'Only pending transactions can be deleted.');
        }

        $transaction->delete();

        return response()->json(['message' => 'Transaction deleted.']);
    }

    public function sendReversalOtp(Request $request, Transaction $transaction, SecurityOtpService $otp): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeReversal($user, $transaction);

        $challenge = $otp->issueChallenge($user, $otp->ledgerReversalPurpose(), ['transaction_id' => $transaction->id]);
        $otp->sendOtpMail($user, $otp->ledgerReversalPurpose(), $challenge['otp_code']);

        return response()->json([
            'message' => 'A verification code was sent to your account email.',
            'expires_at' => $challenge['challenge']->expires_at->toISOString(),
        ]);
    }

    public function reverse(Request $request, Transaction $transaction, SecurityOtpService $otp): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeReversal($user, $transaction);

        $validated = $request->validate(['otp_code' => ['required', 'digits:6']]);
        abort_unless(
            $otp->verifyChallenge($user, $otp->ledgerReversalPurpose(), $validated['otp_code'], ['transaction_id' => $transaction->id]),
            422,
            'Invalid or expired verification code.'
        );

        $reversal = DB::transaction(function () use ($transaction, $user) {
            $transaction = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($transaction->status !== 'completed') {
                abort(409, 'Only completed transactions can be reversed.');
            }
            abort_if($transaction->reversal_of_id !== null, 409, 'A reversal cannot itself be reversed.');
            abort_if($transaction->reversal()->exists(), 409, 'This transaction has already been reversed.');

            // Roll back account balance
            if ($transaction->account_id) {
                $account = Account::whereKey($transaction->account_id)->lockForUpdate()->firstOrFail();
                if (in_array($transaction->transaction_type, ['deposit', 'loan_disbursement'], true)) {
                    abort_if((float) $account->balance < (float) $transaction->amount, 422, 'Cannot reverse credit: insufficient current balance.');
                    $account->decrement('balance', $transaction->amount);
                } elseif (in_array($transaction->transaction_type, ['withdrawal', 'loan_payment'], true)) {
                    $account->increment('balance', $transaction->amount);
                }
            }

            $reversal = Transaction::create([
                'transaction_type' => $transaction->transaction_type,
                'transaction_mode' => $transaction->transaction_mode,
                'account_id' => $transaction->account_id,
                'loan_id' => $transaction->loan_id,
                'amount' => $transaction->amount,
                'transaction_date' => now(),
                'status' => 'completed',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'recorded_by' => $user->id,
                'branch_id' => $transaction->branch_id,
                'note' => 'Reversal of '.$transaction->transaction_number,
                'reversal_of_id' => $transaction->id,
            ]);

            if ($transaction->loan_id && $transaction->transaction_type === 'loan_payment') {
                $loan = Loan::whereKey($transaction->loan_id)->lockForUpdate()->firstOrFail();
                if ($loan->loan_status === 'paid_off' && $loan->remainingBalance() > 0) {
                    $loan->update(['loan_status' => 'active']);
                }
            }

            app(AuditLogService::class)->log('transaction.reversed', 'Reversal entry posted.', 'transaction', $reversal, $user, ['reversal_of_id' => $transaction->id]);

            return $reversal;
        });

        return response()->json(['message' => 'Transaction reversed successfully.', 'data' => $reversal]);
    }

    private function authorizeReversal(?User $user, Transaction $transaction): void
    {
        abort_unless($user?->can('transactions.reversal'), 403, 'Missing transactions.reversal permission.');
        abort_unless(BranchScope::isUnrestricted($user) || (int) $transaction->branch_id === (int) $user->branch_id, 403);
        abort_unless($transaction->status === 'completed', 409, 'Only completed transactions can be reversed.');
        abort_if($transaction->reversal_of_id !== null || $transaction->reversal()->exists(), 409, 'This transaction has already been reversed.');
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
