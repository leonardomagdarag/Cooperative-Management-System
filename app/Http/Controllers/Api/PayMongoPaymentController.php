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
use App\Services\PayMongoService;
use App\Support\PermissionCatalog;
use App\Support\FinancialSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class PayMongoPaymentController extends Controller
{
    public function __construct(
        protected PayMongoService $payMongoService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * List enabled PayMongo payment methods for checkout selector.
     */
    public function methods(): JsonResponse
    {
        $enabled = $this->payMongoService->isEnabled() && $this->payMongoService->isConfigured();
        $methodsConfig = config('paymongo.supported_methods', []);
        $enabledKeys = $this->payMongoService->getEnabledMethods();

        $activeMethods = [];
        foreach ($enabledKeys as $key) {
            if (isset($methodsConfig[$key])) {
                $activeMethods[] = array_merge(['key' => $key], $methodsConfig[$key]);
            }
        }

        return response()->json([
            'enabled' => $enabled,
            'mode' => $this->payMongoService->getMode(),
            'methods' => $activeMethods,
        ]);
    }

    /**
     * Initiate PayMongo Hosted Checkout session.
     */
    public function createCheckout(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $this->payMongoService->isEnabled()) {
            return response()->json([
                'message' => 'Online payment via PayMongo is currently disabled.',
                'code' => 'paymongo_disabled',
            ], 422);
        }

        if (! $this->payMongoService->isConfigured()) {
            return response()->json([
                'message' => 'PayMongo gateway is not properly configured. Please contact support.',
                'code' => 'paymongo_unconfigured',
            ], 422);
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
            'payment_methods' => 'nullable|array',
            'payment_methods.*' => 'string|in:gcash,paymaya,grab_pay,shopeepay,card,qrph,dob,billease',
            'return_url' => 'nullable|string',
        ]);

        $serviceType = $validated['service_type'];

        // Check service availability
        if ($serviceType === 'deposit') {
            if (! SystemSetting::get('service_deposit_enabled', true) || ! SystemSetting::get('service_deposit_online_enabled', true)) {
                return response()->json(['message' => 'Online deposits are temporarily unavailable.'], 422);
            }
        } elseif ($serviceType === 'loan_payment') {
            if (! SystemSetting::get('service_loan_payment_enabled', true) || ! SystemSetting::get('service_loan_payment_online_enabled', true)) {
                return response()->json(['message' => 'Online loan payments are temporarily unavailable.'], 422);
            }
        }

        $targetUserId = $validated['member_id'] ?? $validated['user_id'] ?? $user->id;

        // Permissions check: only officers can initiate payment for another member
        $canManage = (bool) ($user->is_officer || $user->hasRole('admin') || $user->hasAnyRole(PermissionCatalog::officerRoles()));
        if ((int) $targetUserId !== (int) $user->id && ! $canManage) {
            return response()->json(['message' => 'You can only initiate payments for your own account.'], 403);
        }

        $targetUser = User::findOrFail($targetUserId);
        $targetBranchId = $targetUser->branch_id;

        $account = null;
        $loan = null;

        if ($serviceType === 'deposit') {
            $account = Account::where('user_id', $targetUserId)
                ->where('account_type', $validated['account_type'])
                ->first();

            if (! $account) {
                return response()->json(['message' => "Selected {$validated['account_type']} account not found."], 404);
            }
        } elseif ($serviceType === 'loan_payment') {
            $loan = Loan::findOrFail($validated['loan_id']);

            if ((int) $loan->user_id !== (int) $targetUserId) {
                return response()->json(['message' => 'The selected loan does not belong to this member.'], 422);
            }

            if ($loan->loan_status !== 'active' || $loan->disbursed_at === null) {
                return response()->json(['message' => 'This loan is not currently active for payment.'], 422);
            }

            $remainingBalance = (float) $loan->remainingBalance();
            if ((float) $validated['amount'] > $remainingBalance + 0.01) {
                return response()->json([
                    'message' => 'Payment amount exceeds the remaining loan balance of ₱'.number_format($remainingBalance, 2),
                ], 422);
            }

            // Repayments are external payments and are not tied to savings.
            $account = null;
        }

        // Idempotency check with client_request_id
        if (! empty($validated['client_request_id'])) {
            $existingTxn = Transaction::where('client_request_id', $validated['client_request_id'])->first();
            if ($existingTxn && $existingTxn->paymongo_checkout_session_id) {
                try {
                    $existingSession = $this->payMongoService->getCheckoutSession($existingTxn->paymongo_checkout_session_id);

                    return response()->json([
                        'message' => 'Returning existing checkout session.',
                        'checkout_url' => $existingSession['attributes']['checkout_url'] ?? null,
                        'checkout_session_id' => $existingTxn->paymongo_checkout_session_id,
                        'transaction_number' => $existingTxn->transaction_number,
                    ]);
                } catch (Throwable) {
                    // If session expired or failed, proceed to recreate
                }
            }
        }

        // Create transaction in pending state
        $transaction = DB::transaction(function () use ($account, $loan, $validated, $serviceType, $user, $targetBranchId) {
            $penaltyAmount = $loan
                ? (float) ($validated['penalty_amount'] ?? min((float) $loan->accruedPenalty(), (float) $validated['amount']))
                : 0.0;
            $feeAmount = FinancialSettings::feeAmount($serviceType, (float) $validated['amount']);
            $transaction = Transaction::create([
                'account_id' => $account?->id,
                'loan_id' => $loan?->id,
                'branch_id' => $targetBranchId,
                'client_request_id' => $validated['client_request_id'] ?? null,
                'transaction_type' => $serviceType,
                'transaction_mode' => 'online',
                'payment_gateway' => 'paymongo',
                'amount' => $validated['amount'],
                'penalty_amount' => $penaltyAmount,
                'fee_amount' => $feeAmount,
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

        // Determine return URL
        $frontendBase = config('app.url');
        $successUrl = $validated['return_url'] ?? "{$frontendBase}/services?payment=success&txn={$transaction->transaction_number}";
        $cancelUrl = $validated['return_url'] ?? "{$frontendBase}/services?payment=cancelled&txn={$transaction->transaction_number}";

        $itemName = $serviceType === 'deposit'
            ? 'Deposit - '.($validated['account_type'] === 'savings' ? 'Savings Account' : 'Share Capital')
            : 'Loan Payment - '.($loan->loan_number ?? 'Loan');

        try {
            $session = $this->payMongoService->createCheckoutSession([
                'name' => $itemName,
                'amount' => round((float) $transaction->amount + (float) $transaction->fee_amount, 2),
                'description' => "Cooperative transaction {$transaction->transaction_number}",
                'reference_number' => $transaction->transaction_number,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'payment_method_types' => $validated['payment_methods'] ?? null,
                'metadata' => [
                    'transaction_id' => (string) $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'service_type' => $serviceType,
                    'user_id' => (string) $targetUserId,
                ],
            ]);

            $sessionId = $session['id'] ?? null;
            $checkoutUrl = $session['attributes']['checkout_url'] ?? null;

            if (empty($sessionId) || empty($checkoutUrl)) {
                throw new \RuntimeException('Invalid session response from PayMongo.');
            }

            $transaction->update(['paymongo_checkout_session_id' => $sessionId]);

            $this->auditLogService->log(
                'service.paymongo.checkout_created',
                "Created PayMongo checkout session for transaction {$transaction->transaction_number}.",
                'transaction',
                $transaction,
                $user
            );

            return response()->json([
                'message' => 'PayMongo checkout session created.',
                'checkout_url' => $checkoutUrl,
                'checkout_session_id' => $sessionId,
                'transaction_number' => $transaction->transaction_number,
                'transaction_id' => $transaction->id,
                'amount' => (float) $transaction->amount,
            ], 201);
        } catch (Throwable $e) {
            $transaction->update([
                'status' => 'rejected',
                'rejection_reason' => 'Checkout initialization error: '.$e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to initialize online payment: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Authoritative payment verification.
     * Never trusts frontend confirmation alone (Requirement 9 & 10).
     */
    public function verify(Request $request, string $transactionNumber): JsonResponse
    {
        $user = Auth::user();

        $transaction = Transaction::where('transaction_number', $transactionNumber)
            ->with(['account.user', 'loan'])
            ->firstOrFail();

        // Access control: only owner or authorized officer
        $isOwner = (int) $transaction->recorded_by === (int) $user->id
            || ($transaction->account && (int) $transaction->account->user_id === (int) $user->id)
            || ($transaction->loan && (int) $transaction->loan->user_id === (int) $user->id);

        $isOfficer = (bool) ($user->is_officer || $user->hasRole('admin') || $user->hasAnyRole(PermissionCatalog::officerRoles()));

        if (! $isOwner && ! $isOfficer) {
            return response()->json(['message' => 'Unauthorized to view this transaction.'], 403);
        }

        if (empty($transaction->paymongo_checkout_session_id)) {
            return response()->json([
                'message' => 'This transaction is not linked to a PayMongo checkout session.',
                'status' => $transaction->status,
                'transaction' => $transaction,
            ], 422);
        }

        try {
            $result = $this->payMongoService->verifyAndCompletePayment($transaction);

            $transaction->refresh();

            return response()->json([
                'status' => $transaction->status,
                'is_paid' => $transaction->status === 'completed',
                'message' => $transaction->status === 'completed'
                    ? 'Payment successfully verified and completed.'
                    : ($result['message'] ?? 'Payment pending completion on PayMongo.'),
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
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Verification failed: '.$e->getMessage(),
                'status' => $transaction->status,
            ], 422);
        }
    }
}
