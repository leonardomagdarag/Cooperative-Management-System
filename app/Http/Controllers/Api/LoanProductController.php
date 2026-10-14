<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LoanProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = LoanProduct::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $products,
            'can_manage' => Auth::user()?->can('settings.update'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:2', 'max:50', 'alpha_dash', Rule::unique('loan_products', 'code')],
            'name' => 'required|string|min:3|max:120',
            'description' => 'nullable|string|max:500',
            'base_type' => 'required|in:group,business,special',
            'icon' => 'nullable|string|max:50',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'max_amount' => 'required|numeric|min:500|max:5000000',
            'max_term' => 'required|integer|min:1|max:120',
            'is_active' => 'boolean',
            'requires_no_existing_loan' => 'boolean',
            'minimum_share_capital' => 'nullable|numeric|min:0|max:5000000',
            'minimum_membership_years' => 'required|integer|min:0|max:50',
            'required_co_makers' => 'required|integer|min:0|max:20',
            'requires_interview' => 'boolean',
            'requires_special_eligibility' => 'boolean',
            'sort_order' => 'required|integer|min:0|max:9999',
        ]);

        $product = LoanProduct::create([
            'code' => strtolower(trim($validated['code'])),
            'name' => trim($validated['name']),
            'description' => trim($validated['description'] ?? ''),
            'base_type' => $validated['base_type'],
            'icon' => trim($validated['icon'] ?? 'wallet'),
            'interest_rate' => (float) $validated['interest_rate'],
            'max_amount' => (float) $validated['max_amount'],
            'max_term' => (int) $validated['max_term'],
            'is_active' => $validated['is_active'] ?? true,
            'requires_no_existing_loan' => $validated['requires_no_existing_loan'] ?? false,
            'minimum_share_capital' => (float) ($validated['minimum_share_capital'] ?? 0),
            'minimum_membership_years' => (int) $validated['minimum_membership_years'],
            'required_co_makers' => (int) $validated['required_co_makers'],
            'requires_interview' => $validated['requires_interview'] ?? false,
            'requires_special_eligibility' => $validated['requires_special_eligibility'] ?? false,
            'sort_order' => (int) $validated['sort_order'],
        ]);

        app(AuditLogService::class)->log('settings.loan_product_created', 'Loan product created.', 'system_setting', $product, Auth::user(), ['product_code' => $product->code, 'product_name' => $product->name]);

        return response()->json(['message' => 'Loan product created.', 'data' => $product], 201);
    }

    public function update(Request $request, LoanProduct $loanProduct): JsonResponse
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:2', 'max:50', 'alpha_dash', Rule::unique('loan_products', 'code')->ignore($loanProduct->id)],
            'name' => 'required|string|min:3|max:120',
            'description' => 'nullable|string|max:500',
            'base_type' => 'required|in:group,business,special',
            'icon' => 'nullable|string|max:50',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'max_amount' => 'required|numeric|min:500|max:5000000',
            'max_term' => 'required|integer|min:1|max:120',
            'is_active' => 'boolean',
            'requires_no_existing_loan' => 'boolean',
            'minimum_share_capital' => 'nullable|numeric|min:0|max:5000000',
            'minimum_membership_years' => 'required|integer|min:0|max:50',
            'required_co_makers' => 'required|integer|min:0|max:20',
            'requires_interview' => 'boolean',
            'requires_special_eligibility' => 'boolean',
            'sort_order' => 'required|integer|min:0|max:9999',
        ]);

        $loanProduct->update([
            'code' => strtolower(trim($validated['code'])),
            'name' => trim($validated['name']),
            'description' => trim($validated['description'] ?? ''),
            'base_type' => $validated['base_type'],
            'icon' => trim($validated['icon'] ?? 'wallet'),
            'interest_rate' => (float) $validated['interest_rate'],
            'max_amount' => (float) $validated['max_amount'],
            'max_term' => (int) $validated['max_term'],
            'is_active' => $validated['is_active'] ?? true,
            'requires_no_existing_loan' => $validated['requires_no_existing_loan'] ?? false,
            'minimum_share_capital' => (float) ($validated['minimum_share_capital'] ?? 0),
            'minimum_membership_years' => (int) $validated['minimum_membership_years'],
            'required_co_makers' => (int) $validated['required_co_makers'],
            'requires_interview' => $validated['requires_interview'] ?? false,
            'requires_special_eligibility' => $validated['requires_special_eligibility'] ?? false,
            'sort_order' => (int) $validated['sort_order'],
        ]);

        app(AuditLogService::class)->log('settings.loan_product_updated', 'Loan product updated.', 'system_setting', $loanProduct, Auth::user(), ['product_code' => $loanProduct->code, 'product_name' => $loanProduct->name]);

        return response()->json(['message' => 'Loan product updated.', 'data' => $loanProduct->fresh()]);
    }

    public function toggle(LoanProduct $loanProduct): JsonResponse
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $loanProduct->update(['is_active' => ! $loanProduct->is_active]);

        app(AuditLogService::class)->log('settings.loan_product_toggled', 'Loan product active state toggled.', 'system_setting', $loanProduct, Auth::user(), ['product_code' => $loanProduct->code, 'is_active' => $loanProduct->is_active]);

        return response()->json([
            'message' => $loanProduct->name.($loanProduct->fresh()->is_active ? ' enabled.' : ' disabled.'),
            'data' => $loanProduct->fresh(),
        ]);
    }

    public function destroy(LoanProduct $loanProduct): JsonResponse
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $loanProduct->delete();

        app(AuditLogService::class)->log('settings.loan_product_deleted', 'Loan product deleted.', 'system_setting', $loanProduct, Auth::user(), ['product_code' => $loanProduct->code, 'product_name' => $loanProduct->name]);

        return response()->json(['message' => 'Loan product deleted.']);
    }
}
