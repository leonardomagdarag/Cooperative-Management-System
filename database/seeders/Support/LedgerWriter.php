<?php

namespace Database\Seeders\Support;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Writes seed ledger entries the same way the application would.
 *
 * Every balance-affecting row has to be posted through here so the seeded data
 * obeys the rules LedgerController::applyBalanceEffect() enforces at runtime:
 *
 *  - deposits and loan disbursements credit an account;
 *  - withdrawals and loan payments debit it;
 *  - membership fees never move a balance at all;
 *  - only `completed` rows count, so `pending` and `rejected` rows must not
 *    be included when a balance is derived.
 *
 * It also allocates transaction numbers from the row's own transaction_date.
 * The model hook falls back to today's date, which would give months of
 * backdated history the same 2026 stamp and make the ledger look fabricated.
 */
final class LedgerWriter
{
    /**
     * Last sequence number handed out per `TXNyymmdd` prefix.
     *
     * @var array<string, int>
     */
    private array $sequences = [];

    /**
     * @var array<string, User|null>
     */
    private array $officers = [];

    /**
     * Resolve a seeded officer by user number.
     */
    public function officer(string $userNumber): ?User
    {
        return $this->officers[$userNumber] ??= User::query()
            ->where('user_number', $userNumber)
            ->first();
    }

    /**
     * Allocate the next transaction number for a given business date.
     */
    public function nextTransactionNumber(Carbon $date): string
    {
        $prefix = 'TXN'.$date->format('Ymd');

        if (! isset($this->sequences[$prefix])) {
            $this->sequences[$prefix] = (int) (self::highestSequence($prefix) ?? 0);
        }

        $this->sequences[$prefix]++;

        return $prefix.str_pad((string) $this->sequences[$prefix], 6, '0', STR_PAD_LEFT);
    }

    /**
     * Forget cached sequence numbers, e.g. after a bulk wipe.
     */
    public function resetSequences(): void
    {
        $this->sequences = [];
    }

    /**
     * Post a completed ledger entry.
     *
     * @param  array<string, mixed>  $attributes
     *                                            account_id, transaction_type, amount, transaction_date
     *                                            (plus optional loan_id, recorded_by, approved_by, note)
     */
    public function post(array $attributes): Transaction
    {
        return $this->write($attributes, 'completed');
    }

    /**
     * Post an entry that still sits in the approval queue.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function queue(array $attributes): Transaction
    {
        return $this->write($attributes, 'pending');
    }

    /**
     * The signed effect a completed entry has on an account balance.
     */
    public static function balanceEffect(string $type, float $amount): float
    {
        return match ($type) {
            'deposit', 'loan_disbursement' => $amount,
            'withdrawal', 'loan_payment' => -$amount,
            default => 0.0,
        };
    }

    /**
     * Derive an account balance from its completed ledger and persist it.
     */
    public function recomputeBalance(Account $account): float
    {
        $balance = 0.0;

        $rows = Transaction::query()
            ->where('account_id', $account->id)
            ->where('status', 'completed')
            ->orderBy('id')
            ->get(['transaction_type', 'amount']);

        foreach ($rows as $row) {
            $balance += self::balanceEffect($row->transaction_type, (float) $row->amount);
        }

        $account->forceFill(['balance' => round($balance, 2)])->save();

        return round($balance, 2);
    }

    /**
     * Share capital held by a member at a point in time.
     *
     * Loan eligibility is judged against the balance on the application date,
     * not today's balance, so a member who qualified on a small subscription
     * is not retroactively judged by contributions made years later.
     */
    public function shareCapitalAt(User $user, Carbon $asOf): float
    {
        $account = Account::query()
            ->where('user_id', $user->id)
            ->where('account_type', 'share-capital')
            ->first();

        if (! $account) {
            return 0.0;
        }

        $deposits = Transaction::query()
            ->where('account_id', $account->id)
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->where('transaction_type', 'deposit')
                    ->orWhere('transaction_type', 'loan_disbursement');
            })
            ->where('transaction_date', '<=', $asOf)
            ->sum('amount');

        $debits = Transaction::query()
            ->where('account_id', $account->id)
            ->where('status', 'completed')
            ->whereIn('transaction_type', ['withdrawal', 'loan_payment'])
            ->where('transaction_date', '<=', $asOf)
            ->sum('amount');

        return round((float) $deposits - (float) $debits, 2);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(array $attributes, string $status): Transaction
    {
        $account = Account::query()->find($attributes['account_id']);
        $date = $attributes['transaction_date'] instanceof Carbon
            ? $attributes['transaction_date']
            : Carbon::parse($attributes['transaction_date']);

        $attributes['transaction_number'] ??= $this->nextTransactionNumber($date);

        // Branch scoping is enforced on transactions, so every seeded row needs
        // the branch of the account it touches, not just the member's branch.
        $attributes['branch_id'] ??= $account?->user?->branch_id;

        if ($status === 'completed') {
            $attributes['approved_by'] ??= $this->officer('USR000004')?->id;
            $attributes['approved_at'] ??= $date;
        }

        $attributes['status'] = $status;

        return Transaction::query()->create($attributes);
    }

    protected static function highestSequence(string $prefix): ?int
    {
        return DB::table('transactions')
            ->where('transaction_number', 'like', $prefix.'%')
            ->orderByDesc(DB::raw('CAST(SUBSTRING(transaction_number, 12) AS UNSIGNED)'))
            ->value(DB::raw('CAST(SUBSTRING(transaction_number, 12) AS UNSIGNED)'));
    }
}
