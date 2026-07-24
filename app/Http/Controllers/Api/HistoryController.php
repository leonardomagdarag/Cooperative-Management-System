<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $userAccounts = $user->accounts->pluck('id');

        $query = Transaction::with(['account', 'loan', 'recordedBy'])
            ->whereIn('account_id', $userAccounts);

        // Category filter
        $category = $request->string('category')->toString();
        if ($category !== '' && $category !== 'all') {
            $query->where('transaction_type', $category);
        }

        // Status filter
        $status = $request->string('status')->toString();
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        // Date filter
        $dateFilter = $request->string('date')->toString();
        switch ($dateFilter) {
            case 'today':
                $query->whereDate('transaction_date', today());
                break;
            case 'week':
                $query->whereBetween('transaction_date', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('transaction_date', now()->month)
                    ->whereYear('transaction_date', now()->year);
                break;
            case 'year':
                $query->whereYear('transaction_date', now()->year);
                break;
        }

        // Search
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortDirection = $request->string('direction', 'desc')->toString();
        $query->orderBy('transaction_date', $sortDirection)
            ->orderBy('created_at', $sortDirection);

        $perPage = max(5, min((int) $request->integer('per_page', 15), 50));
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
                'account' => $t->account ? [
                    'id' => $t->account->id,
                    'account_number' => $t->account->account_number,
                    'account_type' => $t->account->account_type,
                ] : null,
                'loan' => $t->loan ? [
                    'id' => $t->loan->id,
                    'loan_number' => $t->loan->loan_number,
                ] : null,
                'recorded_by' => $t->recordedBy ? [
                    'id' => $t->recordedBy->id,
                    'name' => trim(($t->recordedBy->personalData->first_name ?? '') . ' ' . ($t->recordedBy->personalData->last_name ?? '')),
                ] : null,
                'created_at' => $t->created_at->toISOString(),
            ]),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
