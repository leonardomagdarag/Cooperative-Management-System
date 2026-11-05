<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Loan;
use App\Models\PrivilegedActionRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CountersignService;
use App\Services\SecurityOtpService;
use App\Support\BranchScope;
use App\Support\FinancialSettings;
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

        $query = Transaction::with(['branch', 'account.user.personalData', 'loan.user.personalData', 'recordedBy.personalData', 'approvedBy.personalData', 'reversalOf']);

        BranchScope::applyToTransactions($query, Auth::user(), $request->input('branch_id'));

        // Category filter
        $category = $request->string('category')->toString();
        if ($category !== '' && $category !== 'all') {
            $query->where('transaction_type', $category);
        }

        if ($request->boolean('corrections')) {
            $query->whereNotNull('reversal_of_id');
        }

        $status = $request->string('status')->toString();
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
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

        if ($from = $request->string('from')->toString()) {
            $query->whereDate('transaction_date', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->whereDate('transaction_date', '<=', $to);
        }

        // Sort
        $sortField = $request->string('sort', 'created_at')->toString();
        $sortDirection = $request->string('direction', 'desc')->toString();
        $allowedSorts = ['created_at', 'transaction_date', 'transaction_number', 'amount', 'status', 'transaction_type'];
        if ($sortField === 'member') {
            $query->orderByRaw('COALESCE(
                (SELECT pd.first_name FROM accounts a JOIN users u ON u.id = a.user_id JOIN personal_data pd ON pd.id = u.personal_data_id WHERE a.id = transactions.account_id),
                (SELECT pd.first_name FROM loans l JOIN users u ON u.id = l.user_id JOIN personal_data pd ON pd.id = u.personal_data_id WHERE l.id = transactions.loan_id)
            ) '.($sortDirection === 'asc' ? 'ASC' : 'DESC'));
        } elseif (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $query->orderBy('transactions.id');

        $perPage = max(5, min((int) $request->integer('per_page', 10), 50));
        $paginator = $query->paginate($perPage);
        $transactionIds = $paginator->getCollection()
            ->flatMap(fn ($transaction) => [$transaction->id, $transaction->reversal_of_id])
            ->filter()
            ->unique();
        $installmentAllocations = DB::table('loan_payment_installment_allocations')
            ->whereIn('transaction_id', $transactionIds)
            ->orderBy('period')
            ->get()
            ->groupBy('transaction_id');

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
                'fee_amount' => $t->fee_amount,
                'status' => $t->status,
                'note' => $t->note,
                'transaction_date' => $t->transaction_date?->format('Y-m-d'),
                'account_holder' => $t->account?->user ? trim(($t->account->user->personalData->first_name ?? '').' '.($t->account->user->personalData->last_name ?? '')) : null,
                'user_number' => $t->account?->user?->user_number,
                'loan_number' => $t->loan?->loan_number,
                'installment_allocations' => $installmentAllocations
                    ->get($t->reversal_of_id ?? $t->id, collect())
                    ->map(fn ($allocation) => [
                        'period' => (int) $allocation->period,
                        'amount' => (float) $allocation->amount,
                        'is_interest_extension' => (bool) ($t->loan?->interest_extension_penalty
                            && (int) $allocation->period > (int) $t->loan->term_months),
                    ])
                    ->values(),
                'branch_id' => $t->branch_id,
                'branch_name' => $t->branch?->name,
                'recorded_by' => $t->recordedBy ? trim(($t->recordedBy->personalData->first_name ?? '').' '.($t->recordedBy->personalData->last_name ?? '')) : null,
                'approved_by' => $t->approvedBy?->personalData ? trim(($t->approvedBy->personalData->first_name ?? '').' '.($t->approvedBy->personalData->last_name ?? '')) : null,
                'approved_at' => $t->approved_at?->toISOString(),
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
        $transactionIds = $transactions
            ->flatMap(fn ($transaction) => [$transaction->id, $transaction->reversal_of_id])
            ->filter()
            ->unique();
        $installmentAllocations = DB::table('loan_payment_installment_allocations')
            ->whereIn('transaction_id', $transactionIds)
            ->orderBy('period')
            ->get()
            ->groupBy('transaction_id');

        $filename = 'ledger-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($transactions, $installmentAllocations) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Transaction #', 'Type', 'Amount', 'Fee', 'Total', 'Status', 'Mode', 'Member', 'User #', 'Loan #', 'Installment allocations', 'Reversal Of', 'Note', 'Date', 'Recorded By']);

            foreach ($transactions as $t) {
                $allocationLabel = $installmentAllocations
                    ->get($t->reversal_of_id ?? $t->id, collect())
                    ->map(fn ($allocation) => sprintf(
                        '%s #%d: %.2f',
                        $t->loan?->interest_extension_penalty && (int) $allocation->period > (int) $t->loan->term_months
                            ? 'Post-term interest installment'
                            : 'Installment',
                        $allocation->period,
                        $allocation->amount
                    ))
                    ->implode('; ');
                fputcsv($out, [
                    $t->transaction_number,
                    $t->transaction_type,
                    $t->amount,
                    $t->fee_amount,
                    (float) $t->amount + ($t->transaction_type === 'loan_disbursement' ? -(float) $t->fee_amount : (float) $t->fee_amount),
                    $t->status,
                    $t->transaction_mode,
                    $t->account?->user ? trim(($t->account->user->personalData->first_name ?? '').' '.($t->account->user->personalData->last_name ?? '')) : '',
                    $t->account?->user?->user_number,
                    $t->loan?->loan_number,
                    $allocationLabel,
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
            'fee_amount' => FinancialSettings::feeAmount($type, (float) $validated['amount']),
            'penalty_amount' => $type === 'loan_payment'
                ? min((float) Loan::findOrFail($validated['loan_id'])->accruedPenalty(), (float) $validated['amount'])
                : 0,
            'note' => $validated['note'] ?? null,
            'account_id' => $type === 'loan_payment' ? null : ($validated['account_id'] ?? null),
            'loan_id' => $validated['loan_id'] ?? null,
            'branch_id' => $validated['branch_id'] ?? $this->resolveTransactionBranch($validated),
            'transaction_date' => now(),
            'status' => 'pending',
            'recorded_by' => Auth::id(),
        ]);

        app(AuditLogService::class)->log(
            'ledger.transaction_created',
            'Manual ledger transaction created.',
            'transaction',
            $transaction,
            $user,
            [
                'transaction_type' => $transaction->transaction_type,
                'amount' => (float) $transaction->amount,
                'status' => $transaction->status,
                'account_id' => $transaction->account_id,
                'loan_id' => $transaction->loan_id,
            ]
        );

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
        $pending = DB::transaction(function () use ($transaction, $validated, $user) {
            $lockedTransaction = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedTransaction->status !== 'pending', 409, 'Only pending transactions can be edited.');

            return app(CountersignService::class)->request(
                'transaction.update',
                [
                    'transaction_id' => $lockedTransaction->id,
                    'transaction_number' => $lockedTransaction->transaction_number,
                    'requested_by' => $user->id,
                    'changes' => [
                        'transaction_type' => $validated['transaction_type'],
                        'amount' => $validated['amount'],
                        'fee_amount' => FinancialSettings::feeAmount($validated['transaction_type'], (float) $validated['amount']),
                        'note' => $validated['note'] ?? null,
                        'account_id' => $validated['transaction_type'] === 'loan_payment' ? null : ($validated['account_id'] ?? null),
                        'loan_id' => $validated['loan_id'] ?? null,
                        'branch_id' => $validated['branch_id'] ?? $this->resolveTransactionBranch($validated),
                    ],
                ],
                $user
            );
        });

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

        DB::transaction(function () use ($transaction, $user) {
            $lockedTransaction = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedTransaction->status !== 'pending', 409, 'Only pending transactions can be deleted.');
            abort_if(
                $this->hasActiveUpdateRequest($lockedTransaction->id),
                409,
                'Cannot delete a transaction with a pending update request.'
            );

            app(AuditLogService::class)->log(
                'ledger.transaction_deleted',
                'Pending manual ledger transaction deleted.',
                'transaction',
                $lockedTransaction,
                $user,
                [
                    'transaction_type' => $lockedTransaction->transaction_type,
                    'amount' => (float) $lockedTransaction->amount,
                    'status' => $lockedTransaction->status,
                    'account_id' => $lockedTransaction->account_id,
                    'loan_id' => $lockedTransaction->loan_id,
                ]
            );

            $lockedTransaction->delete();
        });

        return response()->json(['message' => 'Transaction deleted.']);
    }

    private function hasActiveUpdateRequest(int $transactionId): bool
    {
        $activeKey = 'transaction.update:'.$transactionId;

        return PrivilegedActionRequest::query()
            ->where(function ($query) use ($activeKey, $transactionId) {
                $query->where('active_key', $activeKey)
                    ->orWhere(function ($legacy) use ($transactionId) {
                        $legacy->whereNull('active_key')
                            ->where('action_type', 'transaction.update')
                            ->whereJsonContains('payload->transaction_id', $transactionId);
                    });
            })
            ->where(function ($query) {
                $query->whereIn('status', ['approved', 'executing'])
                    ->orWhere(fn ($pending) => $pending
                        ->where('status', 'pending')
                        ->where('expires_at', '>', now()));
            })
            ->exists();
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

            $loan = null;
            if ($transaction->loan_id) {
                $loan = Loan::whereKey($transaction->loan_id)->lockForUpdate()->firstOrFail();
                if ($transaction->transaction_type === 'loan_disbursement') {
                    abort_if($loan->completedPayments()->exists(), 422, 'Cannot reverse disbursement of a loan with recorded payments.');
                }
            }

            // Roll back account balance
            if ($transaction->account_id) {
                $account = Account::withTrashed()->whereKey($transaction->account_id)->lockForUpdate()->first();
                abort_if(! $account, 404, 'Associated account not found.');
                abort_if($account->trashed(), 422, 'Cannot reverse transaction on a closed account.');

                if (in_array($transaction->transaction_type, ['deposit', 'loan_disbursement', 'interest'], true)) {
                    $credit = $transaction->transaction_type === 'loan_disbursement'
                        ? (float) $transaction->amount - (float) $transaction->fee_amount
                        : (float) $transaction->amount;
                    abort_if((float) $account->balance < $credit, 422, 'Cannot reverse credit: insufficient current balance.');
                    $account->decrement('balance', $credit);
                } elseif (in_array($transaction->transaction_type, ['withdrawal', 'loan_payment'], true)) {
                    $account->increment('balance', (float) $transaction->amount + (float) $transaction->fee_amount);
                }
            }

            $reversal = Transaction::create([
                'transaction_type' => $transaction->transaction_type,
                'transaction_mode' => $transaction->transaction_mode,
                'account_id' => $transaction->account_id,
                'loan_id' => $transaction->loan_id,
                'amount' => $transaction->amount,
                'penalty_amount' => $transaction->penalty_amount,
                'fee_amount' => $transaction->fee_amount,
                'transaction_date' => now(),
                'status' => 'completed',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'recorded_by' => $user->id,
                'branch_id' => $transaction->branch_id,
                'note' => 'Reversal of '.$transaction->transaction_number,
                'reversal_of_id' => $transaction->id,
            ]);

            if ($loan && $transaction->transaction_type === 'loan_payment') {
                if ($loan->loan_status === 'paid_off' && $loan->remainingBalance() > 0) {
                    $loan->update(['loan_status' => 'active']);
                }
            } elseif ($loan && $transaction->transaction_type === 'loan_disbursement') {
                $loan->update(['loan_status' => 'approved', 'disbursed_at' => null, 'due_date' => null]);
            }

            app(AuditLogService::class)->log('transaction.reversed', 'Reversal entry posted.', 'transaction', $reversal, $user, ['reversal_of_id' => $transaction->id]);

            return $reversal;
        });

        return response()->json(['message' => 'Transaction reversed successfully.', 'data' => $reversal]);
    }

    private function authorizeReversal(?User $user, Transaction $transaction): void
    {
        abort_unless($user?->can('transactions.reversal'), 403, 'Missing transactions.reversal permission.');
        abort_unless(BranchScope::canAccessBranch($user, $transaction->branch_id), 403);
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
}
