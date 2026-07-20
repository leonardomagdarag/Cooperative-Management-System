<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('transactions.view'), 403);

        $query = Expense::with('recordedBy.personalData');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%");
            });
        }

        if ($category = $request->string('category')->toString()) {
            if ($category !== 'all') {
                $query->where('category', $category);
            }
        }

        if ($from = $request->string('from')->toString()) {
            $query->whereDate('expense_date', '>=', $from);
        }

        if ($to = $request->string('to')->toString()) {
            $query->whereDate('expense_date', '<=', $to);
        }

        $query->orderByDesc('expense_date');

        $perPage = max(5, min((int) $request->integer('per_page', 15), 50));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Expense $e) => [
                'id' => $e->id,
                'category' => $e->category,
                'description' => $e->description,
                'amount' => (float) $e->amount,
                'expense_date' => $e->expense_date?->format('Y-m-d'),
                'recorded_by' => $e->recordedBy?->personalData
                    ? trim(($e->recordedBy->personalData->first_name ?? '').' '.($e->recordedBy->personalData->last_name ?? ''))
                    : null,
            ]),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'total_amount' => (float) Expense::query()->sum('amount'),
            'categories' => $this->categories(),
            'can_manage' => Auth::user()?->can('transactions.update'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('transactions.update'), 403);

        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
        ]);

        $expense = Expense::create([
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'expense_date' => $validated['expense_date'],
            'recorded_by' => Auth::id(),
        ]);

        app(AuditLogService::class)->log('expense.created', 'Expense recorded.', 'expense', $expense, Auth::user());

        return response()->json(['message' => 'Expense recorded.', 'data' => $expense], 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        abort_unless(Auth::user()?->can('transactions.update'), 403);

        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
        ]);

        $expense->update([
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'expense_date' => $validated['expense_date'],
        ]);

        app(AuditLogService::class)->log('expense.updated', 'Expense updated.', 'expense', $expense, Auth::user());

        return response()->json(['message' => 'Expense updated.', 'data' => $expense]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        abort_unless(Auth::user()?->can('transactions.update'), 403);

        app(AuditLogService::class)->log('expense.deleted', 'Expense deleted.', 'expense', $expense, Auth::user());
        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }

    private function categories(): array
    {
        return Expense::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }
}
