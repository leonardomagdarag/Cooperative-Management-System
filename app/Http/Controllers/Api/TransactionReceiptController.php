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
        $isOwner = (int) $transaction->account?->user_id === (int) $user->id;
        abort_unless($user->can('transactions.view-all') || $user->can('transactions.view') || $isOwner, 403);
        abort_unless(BranchScope::isUnrestricted($user) || (int) $transaction->branch_id === (int) $user->branch_id, 403);
        abort_unless($transaction->status === 'completed', 422, 'Cannot reprint uncompleted transaction');

        $receipt = TransactionReceipt::create([
            'transaction_id' => $transaction->id,
            'reason' => 'reprint',
            'issued_by' => $user->id,
            'issued_to_member_id' => $transaction->account?->user_id,
            'certified' => false,
        ]);
        app(AuditLogService::class)->log('transaction.receipt_reprinted', 'Duplicate receipt issued.', 'transaction', $receipt, $user, ['transaction_id' => $transaction->id]);

        return response()->json([
            'message' => 'Duplicate receipt generated.',
            'data' => $receipt,
        ]);
    }

    public function reissue(Request $request, Transaction $transaction): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('receipts.reissue'), 403, 'You do not have permission to issue certified replacements.');
        abort_unless(BranchScope::isUnrestricted($user) || (int) $transaction->branch_id === (int) $user->branch_id, 403);
        abort_unless($transaction->status === 'completed', 422, 'Cannot reissue uncompleted transaction');

        $validated = $request->validate([
            'note' => 'required|string|min:5',
        ]);

        $receipt = TransactionReceipt::create([
            'transaction_id' => $transaction->id,
            'reason' => 'reissue',
            'issued_by' => $user->id,
            'issued_to_member_id' => $transaction->account?->user_id,
            'certified' => true,
            'note' => $validated['note'],
        ]);
        app(AuditLogService::class)->log('transaction.receipt_reissued', 'Certified replacement receipt issued.', 'transaction', $receipt, $user, ['transaction_id' => $transaction->id, 'reason' => $validated['note']]);

        return response()->json([
            'message' => 'Certified receipt generated.',
            'data' => $receipt,
        ]);
    }
}
