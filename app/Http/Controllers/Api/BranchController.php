<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('branches.view'), 403);

        $query = Branch::query()
            ->withCount([
                'users',
                'transactions',
                'loans',
            ]);

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $branches = $query->orderBy('is_head_office', 'desc')
            ->orderBy('name')
            ->get();

        $memberCounts = User::query()
            ->memberUsers()
            ->whereIn('branch_id', $branches->pluck('id'))
            ->get()
            ->groupBy('branch_id')
            ->map->count();

        return response()->json([
            'data' => $branches->map(fn ($b) => [
                'id' => $b->id,
                'code' => $b->code,
                'name' => $b->name,
                'is_head_office' => $b->is_head_office,
                'address' => $b->address,
                'contact_number' => $b->contact_number,
                'email' => $b->email,
                'is_active' => $b->is_active,
                'users_count' => $b->users_count,
                'transactions_count' => $b->transactions_count,
                'loans_count' => $b->loans_count,
                'members_count' => (int) ($memberCounts[$b->id] ?? 0),
                'created_at' => $b->created_at?->toISOString(),
            ]),
        ]);
    }

    public function options(): JsonResponse
    {
        abort_unless(Auth::user()?->can('members.view'), 403);

        return response()->json([
            'data' => Branch::query()->active()->orderBy('is_head_office', 'desc')->orderBy('name')->get()
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'is_head_office' => $b->is_head_office]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('branches.create'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('branches', 'name')],
            'is_head_office' => 'nullable|boolean',
            'address' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:120',
            'is_active' => 'nullable|boolean',
        ]);

        $branch = Branch::create($validated);

        app(AuditLogService::class)->log(
            'branch.created',
            'Branch created: '.$branch->name.'.',
            'branch',
            $branch,
            Auth::user()
        );

        return response()->json(['message' => 'Branch created successfully.', 'id' => $branch->id], 201);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        abort_unless(Auth::user()?->can('branches.update'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('branches', 'name')->ignore($branch->id)],
            'is_head_office' => 'nullable|boolean',
            'address' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:120',
            'is_active' => 'nullable|boolean',
        ]);

        $branch->update($validated);

        app(AuditLogService::class)->log(
            'branch.updated',
            'Branch updated: '.$branch->name.'.',
            'branch',
            $branch,
            Auth::user()
        );

        return response()->json(['message' => 'Branch updated successfully.']);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        abort_unless(Auth::user()?->can('branches.delete'), 403);

        if ($branch->is_head_office) {
            return response()->json(['message' => 'The Head Office branch cannot be deleted.'], 422);
        }

        if ($branch->users()->exists()) {
            return response()->json(['message' => 'Cannot delete branch because it has assigned users or members.'], 422);
        }

        if ($branch->loans()->whereIn('loan_status', ['pending', 'approved', 'active'])->exists()) {
            return response()->json(['message' => 'Cannot delete branch because it has active or pending loans.'], 422);
        }

        if ($branch->transactions()->where('status', 'pending')->exists()) {
            return response()->json(['message' => 'Cannot delete branch because it has pending transactions.'], 422);
        }

        if ($branch->memberApplications()->where('status', 'pending')->exists()) {
            return response()->json(['message' => 'Cannot delete branch because it has pending member applications.'], 422);
        }

        // Soft-delete the branch; linked records keep a nullable branch_id.
        $branch->delete();

        app(AuditLogService::class)->log(
            'branch.deleted',
            'Branch deleted: '.$branch->name.'.',
            'branch',
            $branch,
            Auth::user()
        );

        return response()->json(['message' => 'Branch deleted.']);
    }
}
