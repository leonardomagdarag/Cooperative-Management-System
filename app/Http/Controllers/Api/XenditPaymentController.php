<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Loan;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\LoanPaymentAllocationService;
use App\Services\XenditService;
use App\Support\BranchScope;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class XenditPaymentController extends Controller
{
    public function __construct(
        protected XenditService $xendit,
        protected AuditLogService $auditLog
    ) {}

    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => $this->xendit->isEnabled() && $this->xendit->isConfigured(),
            'mode' => $this->xendit->mode(),
        ]);
    }

    public function createDepositCheckout(Request $request): JsonResponse
    {
        return $this->createCheckout($request, 'deposit');
    }

    public function createLoanPaymentCheckout(Request $request): JsonResponse
    {
        return $this->createCheckout($request, 'loan_payment');
    }

    private function createCheckout(Request $request, string $requiredServiceType): JsonResponse
    {
        $user = Auth::user();
        abort_unless(
            $user && ($user->can('transactions.create') || $user->can('services.create')),
            403,
            'Unauthorized to submit payments.'
        );
        if (! $this->xendit->isEnabled()) {
            return response()->json(['message' => 'Online payments via Xendit are currently disabled.', 'code' => 'xendit_disabled'], 422);
        }
        if (! $this->xendit->isConfigured()) {
            return response()->json(['message' => 'Xendit is not configured. Please contact support.', 'code' => 'xendit_unconfigured'], 422);
        }

        $validated = $request->validate([
            'service_type' => 'required|in:deposit,loan_payment',
            'amount' => 'required|numeric|min:1',
            'installment_allocations' => 'required_if:service_type,loan_payment|array|min:1',
            'installment_allocations.*.period' => 'required|integer|min:1',
            'installment_allocations.*.amount' => 'required|numeric|min:0.01',
            'penalty_amount' => 'required_if:service_type,loan_payment|numeric|min:0',
            'account_type' => 'required_if:service_type,deposit|in:savings,share-capital',
            'loan_id' => 'required_if:service_type,loan_payment|exists:loans,id',
            'member_id' => 'nullable|exists:users,id',
            'user_id' => 'nullable|exists:users,id',
            'note' => 'nullable|string|max:500',
            'client_request_id' => 'nullable|string|min:8|max:100',
        ]);

        $serviceType = $validated['service_type'];
        abort_unless($serviceType === $requiredServiceType, 404);
        if ($serviceType === 'deposit' && (! SystemSetting::get('service_deposit_enabled', true) || ! SystemSetting::get('service_deposit_online_enabled', true))) {
            return response()->json(['message' => 'Online deposits are temporarily unavailable.'], 422);
        }
        if ($serviceType === 'loan_payment' && (! SystemSetting::get('service_loan_payment_enabled', true) || ! SystemSetting::get('service_loan_payment_online_enabled', true))) {
            return response()->json(['message' => 'Online loan payments are temporarily unavailable.'], 422);
        }

        $targetUserId = $validated['member_id'] ?? $validated['user_id'] ?? $user->id;
        $canManage = (bool) ($user->is_officer || $user->hasRole('admin') || $user->hasAnyRole(PermissionCatalog::officerRoles()));
        if ((int) $targetUserId !== (int) $user->id && ! $canManage) {
            return response()->json(['message' => 'You can only initiate payments for your own account.'], 403);
        }

        $targetUser = User::findOrFail($targetUserId);
        $account = null;
        $loan = null;
        if ($serviceType === 'deposit') {
            $account = Account::query()->where('user_id', $targetUserId)->where('account_type', $validated['account_type'])->first();
            if (! $account) {
                return response()->json(['message' => 'Selected account not found.'], 404);
            }
        } else {
            $loan = Loan::findOrFail($validated['loan_id']);
            if ((int) $loan->user_id !== (int) $targetUserId) {
                return response()->json(['message' => 'The selected loan does not belong to this member.'], 422);
            }
            if ($loan->loan_status !== 'active' || $loan->disbursed_at === null) {
                return response()->json(['message' => 'This loan is not currently active for payment.'], 422);
            }
            if ((float) $validated['amount'] > (float) $loan->remainingBalance() + 0.01) {
                return response()->json(['message' => 'Payment amount exceeds the remaining loan balance.'], 422);
            }
            $account = null;
        }

        $targetBranchId = $loan?->branch_id ?? $targetUser->branch_id;
        abort_unless(
            BranchScope::canAccessBranch($user, $targetBranchId),
            403,
            'You can only manage members in your branch.'
        );

        $existing = null;
        if (! empty($validated['client_request_id'])) {
            $existing = Transaction::query()->where('client_request_id', $validated['client_request_id'])->first();
            if ($existing && ($existing->payment_gateway !== 'xendit'
                || (int) $existing->recorded_by !== (int) $user->id
                || $existing->transaction_type !== $serviceType
                || abs((float) $existing->amount - (float) $validated['amount']) > 0.01
                || (int) $existing->account_id !== (int) ($account?->id ?? 0)
                || (int) $existing->loan_id !== (int) ($loan?->id ?? 0))) {
                return response()->json(['message' => 'This payment request ID has already been used for a different transaction.'], 409);
            }
            if ($existing?->status === 'completed') {
                return response()->json(['message' => 'This payment request has already been completed.', 'transaction_number' => $existing->transaction_number], 409);
            }
            if ($existing && filled($existing->xendit_payment_id)) {
                return response()->json([
                    'message' => 'Xendit has already received this payment. The transaction cannot be retried; contact the cooperative for resolution.',
                    'transaction_number' => $existing->transaction_number,
                ], 409);
            }
            if ($existing && $existing->xendit_session_id && $existing->status === 'pending') {
                try {
                    $session = $this->xendit->getSession($existing->xendit_session_id, $existing->xendit_mode);
                    if (($session['status'] ?? null) === 'ACTIVE' && ! empty($session['payment_link_url'])) {
                        return response()->json([
                            'message' => 'Returning existing checkout session.',
                            'checkout_url' => $session['payment_link_url'],
                            'checkout_session_id' => $existing->xendit_session_id,
                            'transaction_number' => $existing->transaction_number,
                        ]);
                    }
                    if (($session['status'] ?? null) === 'COMPLETED') {
                        $this->xendit->verifyAndCompletePayment($existing);
                        $existing->refresh();
                        if ($existing->status === 'completed') {
                            return response()->json(['message' => 'This payment request has already been completed.', 'transaction_number' => $existing->transaction_number], 409);
                        }
                        if ($existing->status === 'rejected' && filled($existing->xendit_payment_id)) {
                            return response()->json(['message' => 'Xendit verified the payment, but the transaction was rejected. Contact the cooperative for resolution.', 'transaction_number' => $existing->transaction_number], 409);
                        }
                        if (filled($existing->xendit_payment_id)) {
                            return response()->json(['message' => 'Payment verified and awaiting manager or chairman approval.', 'transaction_number' => $existing->transaction_number], 409);
                        }
                    }
                    if (! in_array($session['status'] ?? null, ['EXPIRED', 'CANCELED'], true)) {
                        return response()->json(['message' => 'The existing Xendit payment session is still being processed. Check its status before retrying.'], 409);
                    }
                } catch (Throwable) {
                    return response()->json(['message' => 'Could not confirm the existing Xendit session. Please check its status before retrying.'], 422);
                }
            }
        }

        $mode = $this->xendit->mode();
        $transaction = $existing ?? DB::transaction(function () use ($account, $loan, $validated, $serviceType, $targetUser, $user) {
            $penaltyAmount = $loan
                ? (float) ($validated['penalty_amount'] ?? min((float) $loan->accruedPenalty(), (float) $validated['amount']))
                : 0.0;
            $transaction = Transaction::create([
                'account_id' => $account?->id,
                'loan_id' => $loan?->id,
                'branch_id' => $targetUser->branch_id,
                'client_request_id' => $validated['client_request_id'] ?? null,
                'transaction_type' => $serviceType,
                'transaction_mode' => 'online',
                'payment_gateway' => 'xendit',
                'amount' => $validated['amount'],
                'penalty_amount' => $penaltyAmount,
                'status' => 'pending',
                'note' => $validated['note'] ?? null,
                'transaction_date' => now(),
                'recorded_by' => $user->id,
            ]);
            if ($loan && ! empty($validated['installment_allocations'])) {
                app(LoanPaymentAllocationService::class)->record($transaction, $validated['installment_allocations'], $penaltyAmount);
            }

            return $transaction;
        });
        if ($existing) {
            $transaction->update([
                'status' => 'pending',
                'rejection_reason' => null,
                'xendit_session_id' => null,
                'xendit_mode' => null,
                'xendit_payment_id' => null,
            ]);
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        $returnUrl = "{$baseUrl}/services?txn={$transaction->transaction_number}";
        $itemName = $serviceType === 'deposit'
            ? 'Deposit - '.($validated['account_type'] === 'savings' ? 'Savings Account' : 'Share Capital')
            : 'Loan Payment - '.($loan->loan_number ?? 'Loan');

        try {
            $sessionParams = [
                'reference_id' => $transaction->transaction_number,
                'amount' => $transaction->amount,
                'description' => "{$itemName} for cooperative transaction {$transaction->transaction_number}",
                'metadata' => [
                    'transaction_id' => (string) $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'service_type' => $serviceType,
                    'user_id' => (string) $targetUserId,
                ],
            ];
            // Xendit requires HTTPS return URLs. Local HTTP development can omit them;
            // the member can return to the open application and check payment status.
            if (str_starts_with($baseUrl, 'https://')) {
                $sessionParams['success_return_url'] = $returnUrl.'&payment=returned';
                $sessionParams['cancel_return_url'] = $returnUrl.'&payment=cancelled';
            }
            $session = $this->xendit->createSession($sessionParams);

            $sessionId = $session['payment_session_id'] ?? $session['session_id'] ?? $session['id'] ?? null;
            $checkoutUrl = $session['payment_link_url'] ?? null;
            if (! $sessionId || ! $checkoutUrl) {
                throw new \RuntimeException('Xendit returned an incomplete payment session.');
            }

            $transaction->update(['xendit_session_id' => $sessionId, 'xendit_mode' => $mode]);
            $this->auditLog->log(
                'service.xendit.checkout_created',
                "Created Xendit payment session for transaction {$transaction->transaction_number}.",
                'transaction',
                $transaction,
                $user
            );

            return response()->json([
                'message' => 'Xendit checkout session created.',
                'checkout_url' => $checkoutUrl,
                'checkout_session_id' => $sessionId,
                'transaction_number' => $transaction->transaction_number,
                'transaction_id' => $transaction->id,
                'amount' => (float) $transaction->amount,
            ], 201);
        } catch (Throwable $exception) {
            $transaction->update([
                'status' => 'rejected',
                'rejection_reason' => 'Checkout initialization error: '.$exception->getMessage(),
            ]);

            return response()->json(['message' => 'Unable to initialize online payment: '.$exception->getMessage()], 500);
        }
    }

    public function verify(string $transactionNumber): JsonResponse
    {
        $user = Auth::user();
        $transaction = Transaction::query()->where('transaction_number', $transactionNumber)->with(['account.user', 'loan'])->firstOrFail();
        $isOwner = (int) $transaction->recorded_by === (int) $user->id
            || ($transaction->account && (int) $transaction->account->user_id === (int) $user->id)
            || ($transaction->loan && (int) $transaction->loan->user_id === (int) $user->id);
        $isOfficer = (bool) ($user->is_officer || $user->hasRole('admin') || $user->hasAnyRole(PermissionCatalog::officerRoles()));
        abort_unless($isOwner || $isOfficer, 403, 'Unauthorized to view this transaction.');
        $transactionBranchId = $transaction->branch_id
            ?? $transaction->loan?->branch_id
            ?? $transaction->loan?->user?->branch_id
            ?? $transaction->account?->user?->branch_id;
        abort_unless(
            BranchScope::canAccessBranch($user, $transactionBranchId),
            403,
            'You can only access transactions in your branch.'
        );

        if (! $transaction->xendit_session_id) {
            return response()->json(['message' => 'This transaction is not linked to a Xendit payment session.', 'status' => $transaction->status], 422);
        }

        try {
            $result = $this->xendit->verifyAndCompletePayment($transaction);
            $transaction->refresh();

            return response()->json([
                'status' => $transaction->status,
                'is_paid' => (bool) ($result['is_paid'] ?? $transaction->status === 'completed'),
                'approval_required' => (bool) ($result['approval_required'] ?? false),
                'message' => $result['message'] ?? ($transaction->status === 'completed' ? 'Payment successfully verified and completed.' : 'Payment is still pending.'),
                'transaction' => [
                    'id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'transaction_type' => $transaction->transaction_type,
                    'amount' => (float) $transaction->amount,
                    'status' => $transaction->status,
                    'payment_gateway' => $transaction->payment_gateway,
                    'payment_method_used' => $transaction->payment_method_used,
                    'transaction_date' => $transaction->transaction_date?->toISOString(),
                    'approved_at' => $transaction->approved_at?->toISOString(),
                    'note' => $transaction->note,
                ],
            ]);
        } catch (Throwable $exception) {
            return response()->json(['message' => 'Verification failed: '.$exception->getMessage(), 'status' => $transaction->status], 422);
        }
    }
}
