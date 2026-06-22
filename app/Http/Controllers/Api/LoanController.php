<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LoanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $canViewAll = $user && ($user->is_officer || $user->hasRole('admin'));

        $query = Loan::with(['user.personalData']);

        if (! $canViewAll) {
            $query->where('user_id', $user->id);
        }

        // Search
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhere('loan_type', 'like', "%{$search}%")
                    ->orWhere('loan_amount', 'like', "%{$search}%")
                    ->orWhere('loan_status', 'like', "%{$search}%")
                    ->orWhereHas('user.personalData', fn ($sub) => $sub->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn ($sub) => $sub->where('user_number', 'like', "%{$search}%"));
            });
        }

        // Sort
        $sortField = $request->string('sort', 'created_at')->toString();
        $sortDirection = $request->string('direction', 'desc')->toString();
        $allowedSorts = ['created_at', 'loan_number', 'loan_amount', 'loan_status', 'loan_type'];
        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $perPage = max(5, min((int) $request->integer('per_page', 10), 50));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($l) => [
                'id' => $l->id,
                'loan_number' => $l->loan_number,
                'loan_type' => $l->loan_type,
                'loan_amount' => $l->loan_amount,
                'interest_rate' => $l->interest_rate,
                'term_months' => $l->term_months,
                'payment_frequency' => $l->payment_frequency,
                'loan_status' => $l->loan_status,
                'applied_at' => $l->applied_at?->format('Y-m-d'),
                'due_date' => $l->due_date,
                'borrower' => $l->user?->personalData ? [
                    'name' => trim($l->user->personalData->first_name . ' ' . $l->user->personalData->last_name),
                    'user_number' => $l->user->user_number,
                ] : null,
            ]),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'can_encode' => $user?->hasRole('admin') && $user?->can('loans.encode'),
        ]);
    }

    public function show(Loan $loan): JsonResponse
    {
        Gate::authorize('view', $loan);

        $loan->load([
            'user.personalData',
            'approvedBy.personalData',
            'completedPayments',
            'groupMembers.personalData',
            'documents.uploadedBy.personalData',
        ]);

        return response()->json([
            'id' => $loan->id,
            'loan_number' => $loan->loan_number,
            'loan_type' => $loan->loan_type,
            'loan_amount' => $loan->loan_amount,
            'interest_rate' => $loan->interest_rate,
            'term_months' => $loan->term_months,
            'payment_frequency' => $loan->payment_frequency,
            'loan_status' => $loan->loan_status,
            'applied_at' => $loan->applied_at?->format('Y-m-d'),
            'approved_at' => $loan->approved_at?->format('Y-m-d'),
            'due_date' => $loan->due_date,
            'borrower' => $loan->user?->personalData ? [
                'name' => trim($loan->user->personalData->first_name . ' ' . $loan->user->personalData->last_name),
                'user_number' => $loan->user->user_number,
            ] : null,
            'approved_by' => $loan->approvedBy?->personalData ? [
                'name' => trim($loan->approvedBy->personalData->first_name . ' ' . $loan->approvedBy->personalData->last_name),
            ] : null,
            'payments' => $loan->completedPayments->map(fn ($p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'payment_date' => $p->payment_date?->format('Y-m-d'),
                'status' => $p->status,
            ]),
            'documents' => $loan->documents->map(fn ($d) => [
                'id' => $d->id,
                'original_name' => $d->original_name,
                'mime_type' => $d->mime_type,
                'file_size' => $d->file_size,
                'url' => asset('storage/' . $d->storage_path),
            ]),
        ]);
    }

    public function searchMembers(Request $request): JsonResponse
    {
        $search = $request->string('q')->toString();

        if ($search === '') {
            return response()->json(['data' => []]);
        }

        $members = User::query()
            ->memberUsers()
            ->with('personalData')
            ->where(function ($query) use ($search) {
                $query->where('user_number', 'like', "%{$search}%")
                    ->orWhereHas('personalData', function ($personalDataQuery) use ($search) {
                        $personalDataQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('mid_name', 'like', "%{$search}%");
                    });
            })
            ->orderBy('user_number')
            ->limit(8)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => trim(($m->personalData->first_name ?? '') . ' ' . ($m->personalData->last_name ?? '')),
                'user_number' => $m->user_number,
            ]);

        return response()->json(['data' => $members]);
    }

    public function loanProducts(): JsonResponse
    {
        $products = LoanProduct::query()->active()->orderBy('sort_order')->orderBy('name')->get()
            ->mapWithKeys(fn (LoanProduct $product) => [
                $product->code => [
                    'code' => $product->code,
                    'name' => $product->name,
                    'base_type' => $product->base_type,
                    'interest_rate' => (float) $product->interest_rate,
                    'max_term' => (int) $product->max_term,
                ],
            ]);

        if ($products->isEmpty()) {
            $products = collect([
                'business' => ['code' => 'business', 'name' => 'Business Loan', 'base_type' => 'business', 'interest_rate' => 3.0, 'max_term' => 12],
                'group' => ['code' => 'group', 'name' => 'Group Loan', 'base_type' => 'group', 'interest_rate' => 1.5, 'max_term' => 6],
                'special' => ['code' => 'special', 'name' => 'Special Loan', 'base_type' => 'special', 'interest_rate' => 2.0, 'max_term' => 12],
            ]);
        }

        return response()->json(['data' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('encode', Loan::class);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'loan_product' => 'required|string',
            'loan_type' => 'required|in:group,business,special',
            'loan_status' => 'required|in:pending,approved,active,paid_off',
            'loan_amount' => 'required|numeric|min:1',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'term_months' => 'required|integer|min:1|max:360',
            'payment_frequency' => 'required|in:weekly,bi-weekly,monthly,quarterly,annually',
            'applied_at' => 'required|date|before_or_equal:today',
        ]);

        $isApproved = in_array($validated['loan_status'], ['approved', 'active', 'paid_off'], true);
        $appliedAt = \Carbon\Carbon::parse($validated['applied_at'])->startOfDay();

        $loan = Loan::create([
            'user_id' => $validated['user_id'],
            'loan_type' => $validated['loan_type'],
            'loan_amount' => $validated['loan_amount'],
            'interest_rate' => $validated['interest_rate'],
            'term_months' => $validated['term_months'],
            'payment_frequency' => $validated['payment_frequency'],
            'loan_status' => $validated['loan_status'],
            'applied_at' => $appliedAt,
            'approved_at' => $isApproved ? $appliedAt : null,
            'approved_by' => $isApproved ? Auth::id() : null,
            'due_date' => $isApproved ? $appliedAt->copy()->addMonths($validated['term_months'])->toDateString() : null,
        ]);

        return response()->json([
            'message' => 'Loan encoded. Reference: ' . $loan->loan_number,
            'data' => $loan,
        ], 201);
    }
}
