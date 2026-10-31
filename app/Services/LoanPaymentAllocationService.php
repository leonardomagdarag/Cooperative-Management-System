<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanPaymentAllocationService
{
    /** Validate and persist the installment portions covered by a payment. */
    public function record(Transaction $transaction, array $allocations, float $penaltyAmount): void
    {
        if ($allocations === []) {
            return;
        }

        $loan = Loan::whereKey($transaction->loan_id)->lockForUpdate()->firstOrFail();
        $this->validate($loan, $transaction, $allocations, $penaltyAmount);

        $now = now();
        DB::table('loan_payment_installment_allocations')->insert(array_map(fn (array $allocation) => [
            'transaction_id' => $transaction->id,
            'period' => $allocation['period'],
            'amount' => $allocation['amount'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $allocations));
    }

    public function validate(Loan $loan, Transaction $transaction, array $allocations, float $penaltyAmount): void
    {
        abort_unless($loan->loan_status === 'active' && $loan->disbursed_at !== null, 409, 'Loan is no longer open for payment.');

        $schedule = collect($loan->paymentSchedule() ?? [])->keyBy('period');
        $reservedAllocations = DB::table('loan_payment_installment_allocations as allocations')
            ->join('transactions', 'transactions.id', '=', 'allocations.transaction_id')
            ->where('transactions.loan_id', $loan->id)
            ->where('transactions.status', 'pending')
            ->where('transactions.id', '!=', $transaction->id)
            ->selectRaw('allocations.period, SUM(allocations.amount) as amount')
            ->groupBy('allocations.period')
            ->pluck('amount', 'period');
        $today = now()->startOfDay();
        $periods = [];
        $allocatedTotal = 0.0;

        foreach ($allocations as $allocation) {
            $period = (int) $allocation['period'];
            abort_if(isset($periods[$period]), 422, 'Each installment may only be selected once.');
            $periods[$period] = true;
            $installment = $schedule->get($period);
            abort_unless($installment, 422, 'A selected installment does not exist.');
            $dueDate = Carbon::createFromFormat('M d, Y', $installment['due_date'])->startOfDay();
            abort_if($dueDate->greaterThan($today), 422, 'Only installments due today or overdue can be paid here.');

            $remaining = max(
                0.0,
                (float) $installment['amount_due'] - (float) ($reservedAllocations[$period] ?? 0)
            );
            $amount = round((float) $allocation['amount'], 2);
            abort_if($amount > $remaining + 0.01, 422, 'An installment allocation exceeds its unpaid amount.');
            $allocatedTotal += $amount;
        }

        abort_if($penaltyAmount > (float) $loan->accruedPenalty() + 0.01, 422, 'Penalty allocation exceeds the current accrued penalty.');
        abort_if(abs(($allocatedTotal + $penaltyAmount) - (float) $transaction->amount) > 0.01, 422, 'Payment total must equal selected installment amounts plus penalty.');
    }
}
