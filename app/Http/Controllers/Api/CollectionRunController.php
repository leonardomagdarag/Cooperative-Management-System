<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CollectionRun;
use App\Models\CollectionRunItem;
use App\Models\Loan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CollectionRunService;
use App\Services\CountersignService;
use App\Support\BranchScope;
use App\Support\SearchFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CollectionRunController extends Controller
{
    public function __construct(private CollectionRunService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('collections.view-all') || $user->can('collections.view-own'), 403);

        $query = CollectionRun::with('loanOfficer.personalData', 'branch');

        if (! BranchScope::isUnrestricted($user)) {
            $query->where('branch_id', $user->branch_id);
        }

        if (! $user->can('collections.view-all')) {
            $query->where('loan_officer_id', $user->id);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $this->applyTableFilters($query, $request);

        $runs = $query->paginate(max(5, min($request->integer('per_page', 15), 50)));

        return response()->json($runs);
    }

    private function applyTableFilters(Builder $query, Request $request, string $defaultSort = 'collection_date'): void
    {
        if ($search = SearchFilter::escapeLike($request->string('search')->toString())) {
            $query->where(function ($builder) use ($search) {
                $builder->where('run_number', 'like', "%{$search}%")
                    ->orWhereHas('loanOfficer.personalData', fn ($person) => $person->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            });
        }
        if ($from = $request->string('from')->toString()) {
            $query->whereDate('collection_date', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->whereDate('collection_date', '<=', $to);
        }

        $sort = $request->string('sort', $defaultSort)->toString();
        $direction = $request->string('direction', 'desc')->toString() === 'asc' ? 'asc' : 'desc';
        if ($sort === 'officer') {
            $query->orderBy(User::select('personal_data.first_name')->join('personal_data', 'personal_data.id', '=', 'users.personal_data_id')
                ->whereColumn('users.id', 'collection_runs.loan_officer_id')->limit(1), $direction);
        } else {
            $query->orderBy(in_array($sort, ['collection_date', 'run_number', 'status', 'created_at', 'submitted_at', 'current_stage', 'item_count', 'total_amount'], true) ? $sort : $defaultSort, $direction);
        }
        $query->orderBy('collection_runs.id');

    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('collections.create'), 403);

        $validated = $request->validate([
            'collection_date' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $branchId = BranchScope::resolveBranchId($user, $validated['branch_id'] ?? null) ?? $user->branch_id;
        abort_unless($branchId, 422, 'A branch is required to create a collection run.');
        $run = $this->service->createRun($user, $validated['collection_date'], $branchId);

        return response()->json(['data' => $run], 201);
    }

    public function routeList(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('collections.create'), 403);

        $routeDate = $request->filled('date')
            ? Carbon::parse($request->input('date'))->toDateString()
            : today()->toDateString();

        // Loans already claimed by an in-flight run for this date stay off the
        // worklist so the same loan cannot be collected twice in one day. Items
        // in the viewer's own draft are kept: the page shows them as Collected.
        $claimedRunIds = CollectionRun::query()
            ->whereDate('collection_date', $routeDate)
            ->whereIn('status', ['draft', 'submitted', 'in_progress', 'partially_applied'])
            ->where(function ($query) use ($user) {
                $query->where('status', '!=', 'draft')
                    ->orWhere('loan_officer_id', '!=', $user->id);
            })
            ->pluck('id');
        $claimedLoanIds = $claimedRunIds->isEmpty()
            ? collect()
            : CollectionRunItem::query()->whereIn('collection_run_id', $claimedRunIds)->pluck('loan_id');

        $loansQuery = Loan::with(['branch', 'user.personalData.address', 'user.personalData.contact']);
        BranchScope::applyToLoans($loansQuery, $user);
        $loans = $loansQuery
            ->whereIn('loan_status', ['approved', 'active'])
            ->when($claimedLoanIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $claimedLoanIds))
            ->get()
            ->map(function ($loan) {
                $metrics = $loan->getCollectionMetrics();
                $person = $loan->user->personalData;
                $address = $person?->address;

                return [
                    'id' => $loan->id,
                    'branch_id' => $loan->branch_id,
                    'branch_name' => $loan->branch?->name,
                    'loan_number' => $loan->loan_number,
                    'member_id' => $loan->user_id,
                    'member_name' => $person ? trim($person->first_name.' '.$person->last_name) : 'Unknown',
                    'contact_number' => $person?->contact?->phone_no ?? null,
                    'address' => $address ? trim(collect([
                        $address->street,
                        $address->barangay,
                        $address->city_municipality,
                        $address->province,
                        $address->region,
                    ])->filter()->implode(', ')) : null,
                    'amount_due' => $metrics['amount_due'],
                    'days_overdue' => $metrics['days_overdue'],
                ];
            })
            ->filter(fn (array $loan) => $loan['amount_due'] > 0)
            ->values();

        return response()->json(['data' => $loans]);
    }

    public function availableOfficers(): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('collections.approve') || $user->can('oversight.approve'), 403);

        $officers = User::query()
            ->where('account_status', 'active')
            ->with(['personalData', 'branch'])
            ->get()
            ->filter(fn (User $officer) => $officer->can('collections.create'))
            ->filter(fn (User $officer) => BranchScope::isUnrestricted($user)
                || BranchScope::canAccessBranch($officer, $user->branch_id))
            ->values()
            ->map(fn (User $officer) => [
                'id' => $officer->id,
                'branch_id' => $officer->branch_id,
                'name' => trim(($officer->personalData->first_name ?? '').' '.($officer->personalData->last_name ?? '')),
                'branch_name' => $officer->branch?->name,
            ]);

        return response()->json(['data' => $officers]);
    }

    public function show(CollectionRun $run): JsonResponse
    {
        $user = Auth::user();
        $stages = $run->stages_snapshot ?? [];
        $currentStage = is_array($stages) ? ($stages[(int) $run->current_stage] ?? null) : null;
        $canReviewCurrentStage = in_array($run->status, ['submitted', 'in_progress'], true)
            && is_array($currentStage)
            && ! empty($currentStage['permission'])
            && $user->can($currentStage['permission']);

        abort_unless(
            $user->can('collections.view-all')
                || ($user->can('collections.view-own') && (int) $run->loan_officer_id === (int) $user->id)
                || $canReviewCurrentStage,
            403,
        );
        abort_unless(BranchScope::canAccessBranch($user, $run->branch_id), 403);

        $run->load(['items.member.personalData', 'items.loan', 'items.account', 'loanOfficer.personalData', 'approvals.decidedBy.personalData']);
        $run->items->each(fn (CollectionRunItem $item) => $item->setAttribute(
            'current_amount_due',
            (float) ($item->loan?->getCollectionMetrics()['amount_due'] ?? 0),
        ));

        return response()->json(['data' => $run]);
    }

    public function addItem(Request $request, CollectionRun $run): JsonResponse
    {
        $user = Auth::user();
        abort_unless(BranchScope::canAccessBranch($user, $run->branch_id), 403);
        abort_unless($user->can('collections.create') && $run->loan_officer_id === $user->id, 403);

        $validated = $request->validate([
            'member_id' => 'required|exists:users,id',
            'loan_id' => 'required|exists:loans,id',
            'account_id' => 'nullable|exists:accounts,id',
            'amount' => 'required|numeric|min:1',
            'note' => 'nullable|string',
            'visited' => 'boolean',
        ]);

        $item = $this->service->addItem($run, $validated);
        app(AuditLogService::class)->log('collections.item_added', 'Collection item added to draft run.', 'collection_run', $run, $user, [
            'item_id' => $item->id,
            'member_id' => $item->member_id,
            'loan_id' => $item->loan_id,
            'amount' => $item->amount,
        ]);

        return response()->json(['data' => $item], 201);
    }

    public function removeItem(CollectionRun $run, CollectionRunItem $item): JsonResponse
    {
        $user = Auth::user();
        abort_unless(BranchScope::canAccessBranch($user, $run->branch_id), 403);
        abort_unless($user->can('collections.create') && $run->loan_officer_id === $user->id, 403);
        abort_unless($item->collection_run_id === $run->id, 422);

        $this->service->removeItem($item);
        app(AuditLogService::class)->log('collections.item_removed', 'Collection item removed from draft run.', 'collection_run', $run, $user, [
            'item_id' => $item->id,
            'member_id' => $item->member_id,
            'loan_id' => $item->loan_id,
            'amount' => $item->amount,
        ]);

        return response()->json(['message' => 'Item removed']);
    }

    public function updateItem(Request $request, CollectionRun $run, CollectionRunItem $item): JsonResponse
    {
        $user = Auth::user();
        abort_unless(BranchScope::canAccessBranch($user, $run->branch_id), 403);
        abort_unless($user->can('collections.create') && (int) $run->loan_officer_id === (int) $user->id, 403);
        abort_unless((int) $item->collection_run_id === (int) $run->id, 404);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:1000',
            'visited' => 'required|boolean',
        ]);
        $item = $this->service->updateItem($item, $validated);
        app(AuditLogService::class)->log('collections.item_updated', 'Draft collection item updated.', 'collection_run', $run, $user, [
            'item_id' => $item->id,
            'amount' => $item->amount,
            'visited' => $item->visited,
        ]);

        return response()->json(['data' => $item, 'message' => 'Collection item updated.']);
    }

    public function submit(Request $request, CollectionRun $run): JsonResponse
    {
        $user = Auth::user();
        abort_unless(BranchScope::canAccessBranch($user, $run->branch_id), 403);
        abort_unless($user->can('collections.submit') || $user->can('collections.approve') || $user->can('oversight.approve'), 403);

        $validated = $request->validate([
            'late_reason' => 'nullable|string',
        ]);

        $run = $this->service->submitRun($run, $user, $validated['late_reason'] ?? null);
        app(AuditLogService::class)->log('collections.run_submitted', 'Collection run submitted for approval.', 'collection_run', $run, $user, [
            'run_number' => $run->run_number,
            'item_count' => $run->item_count,
            'total_amount' => $run->total_amount,
            'is_late' => $run->is_late,
        ]);

        return response()->json(['data' => $run, 'message' => 'Run submitted successfully']);
    }

    public function queues(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('collections.review') || $user->can('collections.approve') || $user->can('collections.view-all'), 403);

        $stagesSetting = SystemSetting::where('key', 'collections.approval_stages')->first();
        $stages = $stagesSetting ? (is_string($stagesSetting->value) ? json_decode($stagesSetting->value, true) : $stagesSetting->value) : [];
        $stages = is_array($stages) ? $stages : [];

        $permissionsHeld = [];
        foreach ($stages as $index => $stage) {
            if ($user->can($stage['permission'])) {
                $permissionsHeld[] = $index;
            }
        }

        $query = CollectionRun::with('loanOfficer.personalData', 'branch')
            ->whereIn('status', ['submitted', 'in_progress'])
            ->whereIn('current_stage', $permissionsHeld);
        if (! BranchScope::isUnrestricted($user)) {
            $query->where('branch_id', $user->branch_id);
        }

        $this->applyTableFilters($query, $request, 'submitted_at');
        $runs = $query->paginate(max(5, min($request->integer('per_page', 15), 50)));

        $stalled = collect();
        if ($user->can('oversight.approve')) {
            $pendingRuns = CollectionRun::with('loanOfficer.personalData', 'branch')
                ->whereIn('status', ['submitted', 'in_progress']);
            if (! BranchScope::isUnrestricted($user)) {
                $pendingRuns->where('branch_id', $user->branch_id);
            }

            $activeUsers = User::query()->with('branch')->where('account_status', 'active')->get();
            $stalled = $pendingRuns->get()->filter(function (CollectionRun $run) use ($stages, $activeUsers) {
                $stage = $stages[(int) $run->current_stage] ?? null;
                if (! is_array($stage) || empty($stage['permission'])) {
                    return true;
                }

                return ! $activeUsers->contains(fn (User $candidate) => $candidate->can($stage['permission'])
                    && BranchScope::canAccessBranch($candidate, $run->branch_id)
                );
            })->values();
        }

        return response()->json([
            'data' => $runs->items(),
            'current_page' => $runs->currentPage(),
            'last_page' => $runs->lastPage(),
            'per_page' => $runs->perPage(),
            'total' => $runs->total(),
            'stalled' => $stalled,
        ]);
    }

    public function updateWorkflow(Request $request, CountersignService $countersign): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('collections.workflow.update'), 403);
        $validated = $request->validate([
            'approval_stages' => 'required|array|min:1',
            'approval_stages.*.key' => 'required|string|max:100',
            'approval_stages.*.label' => 'required|string|max:150',
            'approval_stages.*.permission' => 'required|string|max:150',
        ]);
        $this->service->validateStages($validated['approval_stages']);
        $pending = $countersign->request('settings.update', [
            'settings' => ['collections.approval_stages' => $validated['approval_stages']],
        ], $user);

        return response()->json(['message' => 'Workflow update requested and requires chairman countersign.', 'request_id' => $pending->id], 202);
    }

    public function approveStage(Request $request, CollectionRun $run): JsonResponse
    {
        $user = Auth::user();
        abort_unless(BranchScope::canAccessBranch($user, $run->branch_id), 403);

        $validated = $request->validate([
            'action' => 'required|in:approved,rejected',
            'note' => 'nullable|string',
        ]);

        $run = $this->service->approveStage($run, $user, $validated['action'], $validated['note'] ?? null);
        app(AuditLogService::class)->log('collections.stage_decided', 'Collection approval stage decided.', 'collection_run', $run, $user, [
            'action' => $validated['action'],
            'stage' => $run->current_stage,
            'status' => $run->status,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json(['data' => $run, 'message' => 'Stage '.$validated['action']]);
    }

    public function retryFailures(Request $request, CollectionRun $run): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('collections.approve'), 403, 'Only users with collection approval rights can retry failures.');
        abort_unless(BranchScope::canAccessBranch($user, $run->branch_id), 403);

        $validated = $request->validate(['reason' => 'required|string|min:5|max:1000']);

        $retriedRun = $this->service->retryFailedItems($run, $user, $validated['reason']);

        return response()->json([
            'message' => 'Failed items retried.',
            'data' => $retriedRun,
        ]);
    }

    public function replacement(Request $request, CollectionRun $run): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('collections.approve') || $user->can('oversight.approve'), 403);
        abort_unless(BranchScope::canAccessBranch($user, $run->branch_id), 403);

        $validated = $request->validate(['loan_officer_id' => 'nullable|integer|exists:users,id']);
        $officerId = $validated['loan_officer_id'] ?? $run->loan_officer_id;
        $officer = User::find($officerId);
        abort_unless($officer, 422, 'Select an active loan officer for the replacement run.');

        $replacement = $this->service->createReplacementRun($run, $user, $officer);

        return response()->json(['data' => $replacement, 'message' => 'Linked replacement run created.'], 201);
    }

    public function reassignOrphaned(Request $request, CollectionRun $run): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('collections.view-all') && ($user->can('collections.approve') || $user->can('oversight.approve')), 403);
        abort_unless(BranchScope::canAccessBranch($user, $run->branch_id), 403);

        $validated = $request->validate([
            'loan_officer_id' => 'required|integer|exists:users,id',
            'reason' => 'required|string|min:5|max:1000',
        ]);
        $officer = User::findOrFail($validated['loan_officer_id']);
        $run = $this->service->reassignOrphanedRun($run, $user, $officer, $validated['reason']);

        return response()->json(['data' => $run, 'message' => 'Orphaned run reassigned.']);
    }

    public function voidOrphaned(Request $request, CollectionRun $run): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('collections.view-all') && ($user->can('collections.approve') || $user->can('oversight.approve')), 403);
        abort_unless(BranchScope::canAccessBranch($user, $run->branch_id), 403);

        $validated = $request->validate(['reason' => 'required|string|min:5|max:1000']);
        $run = $this->service->voidOrphanedRun($run, $user, $validated['reason']);

        return response()->json(['data' => $run, 'message' => 'Orphaned run voided.']);
    }
}
