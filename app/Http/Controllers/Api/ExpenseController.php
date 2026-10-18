<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\PrivilegedActionRequest;
use App\Services\AuditLogService;
use App\Services\CountersignService;
use App\Support\BranchScope;
use App\Support\SearchFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('transactions.view'), 403);

        $query = Expense::with(['recordedBy.personalData', 'branch']);

        BranchScope::applyToExpenses($query, Auth::user(), $request->input('branch_id'));

        if ($search = SearchFilter::escapeLike($request->string('search')->toString())) {
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

        $pendingRequests = PrivilegedActionRequest::query()
            ->whereIn('action_type', ['expense.update', 'expense.delete'])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->get();

        $pendingMap = [];
        foreach ($pendingRequests as $pr) {
            if (! empty($pr->payload['expense_id'])) {
                $pendingMap[$pr->payload['expense_id']] = [
                    'request_id' => $pr->id,
                    'action_type' => $pr->action_type,
                ];
            }
        }

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
                'branch_id' => $e->branch_id,
                'branch_name' => $e->branch?->name,
                'pending_request' => $pendingMap[$e->id] ?? null,
            ]),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'total_amount' => (float) BranchScope::applyToExpenses(
                Expense::query(),
                Auth::user(),
                $request->input('branch_id')
            )->sum('amount'),
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
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $expense = Expense::create([
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'expense_date' => $validated['expense_date'],
            'recorded_by' => Auth::id(),
            'branch_id' => $validated['branch_id'] ?? Auth::user()?->branch_id,
        ]);

        app(AuditLogService::class)->log('expense.created', 'Expense recorded.', 'expense', $expense, Auth::user());

        return response()->json(['message' => 'Expense recorded.', 'data' => $expense], 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        abort_unless(Auth::user()?->can('transactions.update'), 403);

        $hasPending = PrivilegedActionRequest::query()
            ->whereIn('action_type', ['expense.update', 'expense.delete'])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->get()
            ->contains(fn ($req) => ($req->payload['expense_id'] ?? null) == $expense->id);

        abort_if($hasPending, 409, 'This expense already has a pending approval request.');

        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        // Expense edits are privileged, mirroring ledger transaction edits
        // (see LedgerController::update). They never mutate directly: a
        // countersign request is recorded and the change is applied only once
        // an active chairman holding oversight.approve approves it.
        $pending = app(CountersignService::class)->request(
            'expense.update',
            [
                'expense_id' => $expense->id,
                'requested_by' => Auth::id(),
                'changes' => [
                    'category' => $validated['category'],
                    'description' => $validated['description'] ?? null,
                    'amount' => $validated['amount'],
                    'expense_date' => $validated['expense_date'],
                    'branch_id' => $validated['branch_id'] ?? $expense->branch_id,
                ],
            ],
            Auth::user()
        );

        app(AuditLogService::class)->log(
            'expense.update_requested',
            'Expense edit submitted for chairman approval.',
            'expense',
            $expense,
            Auth::user(),
            ['request_id' => $pending->id]
        );

        return response()->json([
            'message' => 'Expense edit is awaiting chairman approval.',
            'request_id' => $pending->id,
            'status' => 'pending',
        ], 202);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        abort_unless(Auth::user()?->can('transactions.update'), 403);

        $hasPending = PrivilegedActionRequest::query()
            ->whereIn('action_type', ['expense.update', 'expense.delete'])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->get()
            ->contains(fn ($req) => ($req->payload['expense_id'] ?? null) == $expense->id);

        abort_if($hasPending, 409, 'This expense already has a pending approval request.');

        // Expense deletions are privileged, mirroring expense updates.
        // They never mutate directly: a countersign request is recorded
        // and the expense is deleted only once an active chairman holding
        // oversight.approve approves it.
        $pending = app(CountersignService::class)->request(
            'expense.delete',
            [
                'expense_id' => $expense->id,
                'requested_by' => Auth::id(),
                'category' => $expense->category,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'expense_date' => $expense->expense_date?->format('Y-m-d'),
                'branch_id' => $expense->branch_id,
            ],
            Auth::user()
        );

        app(AuditLogService::class)->log(
            'expense.delete_requested',
            'Expense deletion submitted for chairman approval.',
            'expense',
            $expense,
            Auth::user(),
            ['request_id' => $pending->id]
        );

        return response()->json([
            'message' => 'Expense deletion is awaiting chairman approval.',
            'request_id' => $pending->id,
            'status' => 'pending',
        ], 202);
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
