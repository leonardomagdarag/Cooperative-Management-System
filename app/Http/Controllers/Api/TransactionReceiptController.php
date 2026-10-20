<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionReceipt;
use App\Services\AuditLogService;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionReceiptController extends Controller
{
    public function reprint(Request $request, Transaction $transaction): JsonResponse
    {
        $user = Auth::user();
        $isOwner = (int) $this->recipientMemberId($transaction) === (int) $user->id;
        abort_unless($user->can('transactions.view-all') || $user->can('transactions.view') || $isOwner, 403);
        abort_unless(BranchScope::canAccessBranch($user, $transaction->branch_id), 403);
        abort_unless($transaction->status === 'completed', 422, 'Cannot reprint uncompleted transaction');

        $receipt = TransactionReceipt::create([
            'transaction_id' => $transaction->id,
            'reason' => 'reprint',
            'issued_by' => $user->id,
            'issued_to_member_id' => $this->recipientMemberId($transaction),
            'certified' => false,
        ]);
        app(AuditLogService::class)->log('transaction.receipt_reprinted', 'Duplicate receipt issued.', 'transaction', $receipt, $user, ['transaction_id' => $transaction->id]);

        return response()->json([
            'message' => 'Duplicate receipt generated.',
            'data' => $this->receiptPayload($receipt),
        ]);
    }

    public function reissue(Request $request, Transaction $transaction): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('receipts.reissue'), 403, 'You do not have permission to issue certified replacements.');
        abort_unless(BranchScope::canAccessBranch($user, $transaction->branch_id), 403);
        abort_unless($transaction->status === 'completed', 422, 'Cannot reissue uncompleted transaction');

        $validated = $request->validate([
            'note' => 'required|string|min:5',
        ]);

        $receipt = TransactionReceipt::create([
            'transaction_id' => $transaction->id,
            'reason' => 'reissue',
            'issued_by' => $user->id,
            'issued_to_member_id' => $this->recipientMemberId($transaction),
            'certified' => true,
            'note' => $validated['note'],
        ]);
        app(AuditLogService::class)->log('transaction.receipt_reissued', 'Certified replacement receipt issued.', 'transaction', $receipt, $user, ['transaction_id' => $transaction->id, 'reason' => $validated['note']]);

        return response()->json([
            'message' => 'Certified receipt generated.',
            'data' => $this->receiptPayload($receipt),
        ]);
    }

    /**
     * Everything the printable receipt needs, resolved server side so the
     * document reflects what was recorded rather than what the screen held.
     */
    private function receiptPayload(TransactionReceipt $receipt): array
    {
        $receipt->load([
            'transaction.account.user',
            'transaction.branch',
            'transaction.loan',
            'issuedBy',
            'issuedToMember',
        ]);

        $transaction = $receipt->transaction;
        $member = $transaction->account?->user ?? $receipt->issuedToMember;

        return [
            'id' => $receipt->id,
            'reason' => $receipt->reason,
            'certified' => (bool) $receipt->certified,
            'note' => $receipt->note,
            'issued_at' => $receipt->created_at->toISOString(),
            'issued_by' => $receipt->issuedBy?->name ?: null,
            'cooperative' => config('app.name'),
            'member' => [
                'name' => $member?->name ?: null,
                'member_number' => $member?->user_number,
                'account_number' => $transaction->account?->account_number,
            ],
            'transaction' => [
                'id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'type' => $transaction->transaction_type,
                'amount' => (string) $transaction->amount,
                'date' => $transaction->transaction_date?->format('Y-m-d'),
                'status' => $transaction->status,
                'branch' => $transaction->branch?->name,
                'loan_number' => $transaction->loan?->loan_number,
                'note' => $transaction->note,
            ],
        ];
    }

    /**
     * Transactions posted from collection runs carry only a loan_id, so the
     * owning account (and the member the receipt is issued to) is resolved
     * through the loan when the transaction has no account of its own.
     */
    private function recipientMemberId(Transaction $transaction): ?int
    {
        $memberId = $transaction->account?->user_id ?? $transaction->loan?->user_id;

        return $memberId !== null ? (int) $memberId : null;
    }
}
