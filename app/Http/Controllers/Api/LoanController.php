<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Support\SearchFilter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LoanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $canViewAll = $user && ($user->is_officer || $user->hasRole('admin'));

        $query = Loan::with(['user.personalData', 'reviewer.personalData', 'groupMembers.personalData']);

        if (! $canViewAll) {
            $query->where('user_id', $user->id);
        }

        // Search
        if ($search = SearchFilter::escapeLike($request->string('search')->toString())) {
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
                'created_at' => $l->created_at->format('M d, Y'),
                'due_date' => $l->due_date,
                'borrower' => $l->user?->personalData ? [
                    'name' => trim($l->user->personalData->first_name.' '.$l->user->personalData->last_name),
                    'user_number' => $l->user->user_number,
                ] : null,
                'review_status' => $l->review_status,
                'review_status_label' => $l->review_status_label,
                'interview_status' => $l->interview_status,
                'interview_schedule' => $l->interview_schedule?->format('Y-m-d\TH:i'),
                'review_notes' => $l->review_notes,
                'reviewer' => $l->reviewer?->personalData ? trim($l->reviewer->personalData->first_name.' '.$l->reviewer->personalData->last_name) : null,
                'co_makers' => $l->groupMembers->map(fn ($m) => [
                    'name' => $m->personalData ? trim($m->personalData->first_name.' '.$m->personalData->last_name) : ($m->user_number ?? "Member #{$m->id}"),
                    'user_number' => $m->user_number,
                    'has_approved' => (bool) $m->pivot->has_approved,
                ])->values(),
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
            'payment_schedule' => $loan->paymentSchedule(),
            'borrower' => $loan->user?->personalData ? [
                'name' => trim($loan->user->personalData->first_name.' '.$loan->user->personalData->last_name),
                'user_number' => $loan->user->user_number,
            ] : null,
            'approved_by' => $loan->approvedBy?->personalData ? [
                'name' => trim($loan->approvedBy->personalData->first_name.' '.$loan->approvedBy->personalData->last_name),
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
                'url' => asset('storage/'.$d->storage_path),
            ]),
            'group_members' => $loan->groupMembers->map(fn ($gm) => [
                'id' => $gm->id,
                'name' => trim(($gm->personalData->first_name ?? '').' '.($gm->personalData->last_name ?? '')),
                'user_number' => $gm->user_number,
                'has_approved' => (bool) $gm->pivot?->has_approved,
            ]),
        ]);
    }

    public function searchMembers(Request $request): JsonResponse
    {
        $search = SearchFilter::escapeLike($request->string('q')->toString());

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
                'name' => trim(($m->personalData->first_name ?? '').' '.($m->personalData->last_name ?? '')),
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
        $appliedAt = Carbon::parse($validated['applied_at'])->startOfDay();

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
            'message' => 'Loan encoded. Reference: '.$loan->loan_number,
            'data' => $loan,
        ], 201);
    }

    public function update(Request $request, Loan $loan): JsonResponse
    {
        Gate::authorize('encode', Loan::class);

        $validated = $request->validate([
            'loan_type' => 'required|in:group,business,special',
            'loan_amount' => 'required|numeric|min:1',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'term_months' => 'required|integer|min:1|max:360',
            'payment_frequency' => 'required|in:weekly,bi-weekly,monthly,quarterly,annually',
            'loan_status' => 'required|in:pending,approved,active,paid_off',
            'due_date' => 'nullable|date',
        ]);

        $loan->update([
            'loan_type' => $validated['loan_type'],
            'loan_amount' => $validated['loan_amount'],
            'interest_rate' => $validated['interest_rate'],
            'term_months' => $validated['term_months'],
            'payment_frequency' => $validated['payment_frequency'],
            'loan_status' => $validated['loan_status'],
            'due_date' => $validated['due_date'] ?? $loan->due_date,
        ]);

        app(AuditLogService::class)->log('loan.updated', 'Loan record updated.', 'loan', $loan, Auth::user());

        return response()->json(['message' => 'Loan updated.', 'data' => $loan->fresh()]);
    }

    public function disburse(Loan $loan): JsonResponse
    {
        $user = Auth::user();
        Gate::authorize('finalApprove');

        if ($loan->loan_status === 'active') {
            return response()->json(['message' => 'Loan is already active.'], 422);
        }

        if (! in_array($loan->loan_status, ['approved', 'active'], true)) {
            return response()->json(['message' => 'Only approved loans can be disbursed.'], 422);
        }

        $account = $loan->user?->accounts()->where('account_type', 'savings')->first();
        if (! $account) {
            return response()->json(['message' => 'Borrower has no savings account.'], 422);
        }

        DB::transaction(function () use ($loan, $account, $user) {
            Transaction::create([
                'account_id' => $account->id,
                'loan_id' => $loan->id,
                'transaction_type' => 'loan_disbursement',
                'amount' => $loan->loan_amount,
                'status' => 'completed',
                'transaction_mode' => 'counter',
                'transaction_date' => now(),
                'recorded_by' => $user->id,
            ]);

            $account->increment('balance', $loan->loan_amount);

            $loan->update([
                'loan_status' => 'active',
                'account_id' => $account->id,
                'approved_by' => $loan->approved_by ?? $user->id,
                'approved_at' => $loan->approved_at ?? now(),
                'due_date' => now()->addMonths((int) $loan->term_months)->toDateString(),
            ]);

            app(AuditLogService::class)->log('loan.disbursed', 'Loan funds released.', 'loan', $loan, $user);
        });

        if ($loan->user) {
            app(NotificationService::class)->notifyAccountActivity(
                $loan->user,
                'Loan Disbursed',
                'Your loan '.$loan->loan_number.' funds of ₱'.number_format($loan->loan_amount, 2).' have been released to your savings account.',
                'high'
            );
        }

        return response()->json(['message' => 'Loan disbursed.', 'data' => $loan->fresh()]);
    }

    public function close(Loan $loan): JsonResponse
    {
        Gate::authorize('finalApprove', $loan);

        if ($loan->loan_status === 'paid_off') {
            return response()->json(['message' => 'Loan is already closed.'], 422);
        }

        $outstanding = (float) $loan->remainingBalance();
        if ($outstanding > 0) {
            return response()->json([
                'message' => 'Loan cannot be closed because it still has an outstanding balance of ₱'.number_format($outstanding, 2).'.',
            ], 422);
        }

        $loan->update(['loan_status' => 'paid_off']);

        app(AuditLogService::class)->log('loan.closed', 'Loan marked as paid off.', 'loan', $loan, Auth::user());

        return response()->json(['message' => 'Loan closed.', 'data' => $loan->fresh()]);
    }

    public function destroy(Loan $loan): JsonResponse
    {
        Gate::authorize('delete', $loan);

        app(AuditLogService::class)->log('loan.deleted', 'Loan record deleted.', 'loan', $loan, Auth::user());
        $loan->delete();

        return response()->json(['message' => 'Loan deleted.']);
    }

    public function review(Request $request, Loan $loan): JsonResponse
    {
        $user = Auth::user();
        Gate::authorize('review', $loan);

        $validated = $request->validate([
            'action' => 'required|in:start_review,endorse,reject,interview_complete,approve,return_for_review',
            'notes' => 'nullable|string|max:1000',
            'interview_schedule' => 'nullable|date|after:now',
        ]);

        $action = $validated['action'];

        switch ($action) {
            case 'start_review':
                $loan->update(['review_status' => 'under_review', 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
                app(NotificationService::class)->notifyLoanUnderReview($loan);
                break;

            case 'endorse':
                $loan->update(['review_status' => 'endorsed_for_approval', 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
                app(NotificationService::class)->notifyLoanEndorsedForApproval($loan);
                break;

            case 'reject':
                $loan->update([
                    'review_status' => 'review_rejected',
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                    'review_notes' => $validated['notes'] ?? null,
                ]);
                app(NotificationService::class)->notifyLoanReviewRejected($loan, $validated['notes'] ?? null);
                break;

            case 'interview_complete':
                $loan->update([
                    'interview_status' => 'completed',
                    'interview_notes' => $validated['notes'] ?? null,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);
                if ($validated['interview_schedule'] ?? null) {
                    $loan->update(['interview_schedule' => $validated['interview_schedule']]);
                }
                break;

            case 'approve':
                Gate::authorize('finalApprove', $loan);
                if ($loan->review_status !== 'endorsed_for_approval') {
                    return response()->json(['message' => 'Loan must be endorsed before it can be approved.'], 422);
                }
                $loan->update([
                    'loan_status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);
                app(NotificationService::class)->notifyLoanApproved($loan);
                break;

            case 'return_for_review':
                Gate::authorize('finalApprove', $loan);
                if ($loan->review_status !== 'endorsed_for_approval') {
                    return response()->json(['message' => 'Only endorsed loans can be returned for review.'], 422);
                }
                $loan->update([
                    'review_status' => 'under_review',
                    'review_notes' => $validated['notes'] ?? null,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);
                break;
        }

        app(AuditLogService::class)->log('loan.review', "Loan review action: {$action}", 'loan', $loan, $user);

        return response()->json(['message' => "Loan review action '{$action}' processed.", 'data' => $loan]);
    }

    public function uploadDocument(Request $request, Loan $loan): JsonResponse
    {
        Gate::authorize('uploadDocument', $loan);

        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:10240'],
        ]);

        $upload = $validated['document'];
        $path = $upload->store('loan-documents/'.$loan->id, 'public');

        $document = $loan->documents()->create([
            'original_name' => $upload->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $upload->getMimeType(),
            'file_size' => $upload->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        if ($loan->user) {
            app(NotificationService::class)->notifyAccountActivity(
                $loan->user,
                'New document uploaded',
                'A supporting document was added to your loan '.$loan->loan_number.'.',
                'normal'
            );
        }

        return response()->json([
            'message' => 'Loan document uploaded successfully.',
            'document' => [
                'id' => $document->id,
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'url' => asset('storage/'.$document->storage_path),
                'uploaded_at' => $document->created_at->toISOString(),
            ],
        ], 201);
    }
}
