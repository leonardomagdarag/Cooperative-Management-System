<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LedgerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::with(['account.user.personalData', 'loan.user.personalData', 'recordedBy']);

        // Category filter
        $category = $request->string('category')->toString();
        if ($category !== '' && $category !== 'all') {
            $query->where('transaction_type', $category);
        }

        // Search
        if ($search = $request->string('search')->toString()) {
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
                'account_holder' => $t->account?->user ? trim(($t->account->user->personalData->first_name ?? '') . ' ' . ($t->account->user->personalData->last_name ?? '')) : null,
                'user_number' => $t->account?->user?->user_number,
                'loan_number' => $t->loan?->loan_number,
                'recorded_by' => $t->recordedBy ? trim(($t->recordedBy->personalData->first_name ?? '') . ' ' . ($t->recordedBy->personalData->last_name ?? '')) : null,
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
        $range = $request->string('range', 'today')->toString();

        $transactionCounts = Transaction::query()->where('status', 'completed')
            ->applyRange($range)
            ->whereIn('transaction_type', ['deposit', 'withdrawal', 'loan_payment'])
            ->selectRaw('transaction_type, COUNT(*) as count')
            ->groupBy('transaction_type')
            ->pluck('count', 'transaction_type');

        $loanCount = Loan::query()->applyRange($range)->count();

        return response()->json([
            'range' => $range,
            'deposit_count' => (int) ($transactionCounts['deposit'] ?? 0),
            'withdrawal_count' => (int) ($transactionCounts['withdrawal'] ?? 0),
            'loan_payment_count' => (int) ($transactionCounts['loan_payment'] ?? 0),
            'loan_count' => (int) $loanCount,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('transactions.update'), 403);

        $validated = $request->validate([
            'transaction_type' => 'required|in:deposit,withdrawal,loan_payment,loan_disbursement,fee',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:500',
            'account_id' => 'required_if:transaction_type,deposit,withdrawal|nullable|exists:accounts,id',
            'loan_id' => 'required_if:transaction_type,loan_payment,loan_disbursement|nullable|exists:loans,id',
        ]);

        $transaction = Transaction::create([
            'transaction_type' => $validated['transaction_type'],
            'amount' => $validated['amount'],
            'note' => $validated['note'] ?? null,
            'account_id' => $validated['account_id'] ?? null,
            'loan_id' => $validated['loan_id'] ?? null,
            'transaction_date' => now(),
            'recorded_by' => Auth::id(),
        ]);

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
        ]);

        $transaction->update([
            'transaction_type' => $validated['transaction_type'],
            'amount' => $validated['amount'],
            'note' => $validated['note'] ?? null,
            'account_id' => $validated['account_id'] ?? null,
            'loan_id' => $validated['loan_id'] ?? null,
        ]);

        return response()->json(['message' => 'Transaction updated.', 'data' => $transaction->fresh()]);
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        abort_unless(Auth::user()?->can('transactions.update'), 403);

        $transaction->delete();

        return response()->json(['message' => 'Transaction deleted.']);
    }
}
