<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Support\LedgerWriter;
use Database\Seeders\Support\MemberRoster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Writes the ledger that sits underneath the accounts and loans.
 *
 * Two rules shape everything here:
 *
 *  1. Amounts are never invented. Instalments come from Loan::paymentSchedule()
 *     and a closed loan's payments sum to exactly its totalPayable(), because a
 *     member's statement and the loan record have to agree to the centavo.
 *  2. Rows are inserted in date order against a running balance, so a payment
 *     is only ever recorded when the member's savings could actually carry it.
 *     Balances are re-derived from the finished ledger at the end rather than
 *     incremented, which makes the stored balance a fact about the ledger
 *     instead of a number that has to be maintained by hand.
 */
class TransactionSeeder extends Seeder
{
    /** Counter staff: the officer who books counter transactions. */
    private const TELLER = 'USR000004';

    /** The accountant records loan disbursements and payments. */
    private const LOAN_OFFICER = 'USR000004';

    public function run(): void
    {
        $ledger = new LedgerWriter;
        $members = $this->rosteredMembers();

        if ($members->isEmpty()) {
            $this->command?->error('No rostered members found. Please run UserSeeder first.');

            return;
        }

        $this->ensureLoans();
        $this->ensureAccounts($members);

        $teller = $ledger->officer(self::TELLER);
        $loanReviewer = $ledger->officer(self::LOAN_OFFICER);

        foreach (MemberRoster::all() as $member) {
            $user = $members[$member['user_number']] ?? null;

            if (! $user) {
                continue;
            }

            $savings = Account::query()
                ->where('user_id', $user->id)
                ->where('account_type', 'savings')
                ->first();

            if (! $savings) {
                continue;
            }

            $loan = Loan::query()->where('user_id', $user->id)->first();
            $installments = $loan ? $this->installments($loan, (int) ($member['loan']['missed_payments'] ?? 0)) : [];

            $this->savingsHistory($ledger, $savings, $member, $this->disbursedSavingsAmount($loan), $teller?->id);

            if ($loan) {
                $this->loanActivity($ledger, $savings, $loan, $installments, $teller?->id, $loanReviewer?->id);
            }
        }

        $this->pendingQueue($ledger, $members, $teller?->id);
        $this->rejectedHistory($ledger, $members, $teller?->id);
        $this->reversalHistory($ledger, $members, $teller?->id);
        $this->recomputeAll();

        $this->assertBalances();

        $this->command?->info('Ledger: '.Transaction::query()->count().' transactions.');
    }

    /**
     * @return Collection<string, User>
     */
    private function rosteredMembers()
    {
        $userNumbers = collect(MemberRoster::all())->pluck('user_number');

        return User::query()
            ->memberUsers()
            ->whereIn('user_number', $userNumbers)
            ->get()
            ->keyBy('user_number');
    }

    private function ensureLoans(): void
    {
        if (Loan::query()->count() === 0) {
            $this->call(LoanSeeder::class);
        }
    }

    /**
     * @param  Collection<string, User>  $members
     */
    private function ensureAccounts($members): void
    {
        if (Account::query()->whereIn('user_id', $members->pluck('id'))->count() === 0) {
            $this->call(AccountSeeder::class);
        }
    }

    /**
     * The instalments this loan should have been paid against, as
     * [period, due date, amount] rows taken straight from the model's schedule.
     *
     * A loan that has been disbursed for `n` months has `min(term, n)` due
     * dates behind it; the roster's missed-payment count is subtracted to leave
     * the instalments that were actually settled. A closed loan is settled in
     * full, and a loan that has not been disbursed has nothing at all.
     *
     * @return list<array{period: int, date: Carbon, amount: float}>
     */
    private function installments(Loan $loan, int $missed): array
    {
        if (! in_array($loan->loan_status, ['active', 'paid_off'], true)) {
            return [];
        }

        $schedule = $loan->paymentSchedule();

        if (! $schedule) {
            return [];
        }

        $start = $loan->approved_at ?? $loan->applied_at;

        if (! $start) {
            return [];
        }

        $term = (int) $loan->term_months;
        $elapsedMonths = (int) $start->diffInMonths(Carbon::now());
        $duePeriods = min($term, $elapsedMonths);

        if ($loan->loan_status === 'paid_off') {
            $duePeriods = $term;
            $missed = 0;
        }

        $payablePeriods = max(0, $duePeriods - $missed);

        $rows = [];
        foreach (array_slice($schedule, 0, $payablePeriods) as $instalment) {
            $rows[] = [
                'period' => (int) $instalment['period'],
                'date' => $start->copy()->addMonths((int) $instalment['period']),
                'amount' => (float) $instalment['payment'],
            ];
        }

        return $rows;
    }

    /**
     * Whether a loan has actually been released to the member's savings account,
     * and for how much.
     *
     * Cash loan disbursements leave `account_id = null` and hand physical cash
     * to the borrower, so only a loan explicitly released to savings credits
     * the member's savings ledger.
     */
    private function disbursedSavingsAmount(?Loan $loan): float
    {
        if (! $loan || ! in_array($loan->loan_status, ['active', 'paid_off'], true) || ! $loan->account_id) {
            return 0.0;
        }

        return (float) $loan->loan_amount;
    }

    /**
     * Monthly savings contributions and the withdrawals members draw against
     * them, sized so the account lands on the roster's target balance.
     *
     * Only `net` — the target balance, less what the account already held on the
     * day the member joined and less any loan released into it — is genuinely
     * retained each month. The contribution is then grossed up to cover the
     * withdrawals falling due before the *next* one, so the balance never dips
     * below zero on its way to the target. Loan repayments are funded externally
     * and never debit a member's savings account.
     *
     * @param  array<string, mixed>  $member
     */
    private function savingsHistory(
        LedgerWriter $ledger,
        Account $savings,
        array $member,
        float $disbursedToSavings,
        ?int $teller
    ): void {
        $joinedAt = MemberRoster::joinedAt($member);
        $savingsPlan = $member['savings'];
        $target = (float) $savingsPlan['target_balance'];
        $monthlySpend = (float) $savingsPlan['monthly_spend'];
        $now = Carbon::now();

        // Only the contributions that have actually been made count towards
        // reaching the target; the balance is measured as of today.
        $dates = [];

        for ($month = 1; $month <= 240; $month++) {
            $date = $joinedAt->copy()->addMonths($month)->setTime(9, 30);

            if ($date->greaterThan($now)) {
                break;
            }

            $dates[] = $date;
        }

        $monthsContributed = count($dates);

        if ($monthsContributed === 0) {
            return;
        }

        $opening = $this->openingBalance($savings, $joinedAt);
        $netPerMonth = ($target - $opening - $disbursedToSavings) / $monthsContributed;

        if ($netPerMonth <= 0) {
            throw new \RuntimeException(sprintf(
                'Savings target of %.2f for %s leaves nothing after the %.2f opening balance and %.2f of loan releases.',
                $target,
                $member['user_number'],
                $opening,
                $disbursedToSavings
            ));
        }

        $this->assertAffordable($member, $netPerMonth + $monthlySpend, $monthlySpend);

        foreach ($dates as $index => $contributionDate) {
            $withdrawalDate = $contributionDate->copy()->addDays(4)->setTime(14, 0);
            $withdrawal = 0.0;

            // A withdrawal only exists if it has already been drawn, and the
            // contribution may only fund what actually left the account.
            if (! $withdrawalDate->greaterThan($now)) {
                $withdrawal = $this->withdrawalFor($member['user_number'], $index + 1, $monthlySpend);
            }

            $this->postOnce($ledger, $savings, [
                'transaction_type' => 'deposit',
                'amount' => round($netPerMonth + $withdrawal, 2),
                'transaction_date' => $contributionDate,
                'recorded_by' => $teller,
                'transaction_mode' => 'counter',
                'note' => 'Monthly savings contribution',
            ]);

            if ($withdrawal > 0) {
                $this->postOnce($ledger, $savings, [
                    'transaction_type' => 'withdrawal',
                    'amount' => $withdrawal,
                    'transaction_date' => $withdrawalDate,
                    'recorded_by' => $teller,
                    'transaction_mode' => 'counter',
                    'note' => $this->withdrawalNote($index + 1),
                ]);
            }
        }
    }

    /**
     * What the account held on the day the member joined.
     *
     * Measured as of the join date rather than as of today, so re-running the
     * seeder reads the same opening balance and produces the same history.
     */
    private function openingBalance(Account $savings, Carbon $joinedAt): float
    {
        $balance = 0.0;

        foreach ($savings->transactions()
            ->where('status', 'completed')
            ->whereDate('transaction_date', '<=', $joinedAt->toDateString())
            ->get(['transaction_type', 'amount']) as $row) {
            $balance += LedgerWriter::balanceEffect($row->transaction_type, (float) $row->amount);
        }

        return $balance;
    }

    /**
     * The withdrawal a member makes in a given month.
     *
     * Members do not withdraw the same amount every month, and some months they
     * do not touch the account at all, so the pattern is varied but derived
     * deterministically from the member and the month — a re-seed reproduces it
     * exactly instead of rolling new numbers.
     */
    private function withdrawalFor(string $userNumber, int $month, float $monthlySpend): float
    {
        if ($monthlySpend <= 0) {
            return 0.0;
        }

        $hash = abs(crc32($userNumber.'|'.$month));

        // Roughly a third of months are left alone.
        if ($hash % 100 < 34) {
            return 0.0;
        }

        $factor = 0.6 + (($hash >> 8) % 100) / 100;

        return round($monthlySpend * $factor, 2);
    }

    /**
     * Instalments falling due in `(from, to]`.
     *
     * Loan due dates and contribution dates rarely share a day of the month, so
     * instalments are matched against the stretch of time a contribution
     * actually covers rather than against a calendar month.
     *
     * @param  list<array{period: int, date: Carbon, amount: float}>  $installments
     */
    private function paymentsBetween(array $installments, Carbon $from, Carbon $to): float
    {
        $total = 0.0;

        foreach ($installments as $instalment) {
            if ($instalment['date']->greaterThan($from) && $instalment['date']->lessThanOrEqualTo($to)) {
                $total += $instalment['amount'];
            }
        }

        return $total;
    }

    private function withdrawalNote(int $month): string
    {
        $notes = [
            'Emergency withdrawal',
            'Partial withdrawal for farm inputs',
            'Withdrawal for household expenses',
            'Withdrawal towards school fees',
            'Withdrawal for medical expenses',
        ];

        return $notes[$month % count($notes)];
    }

    /**
     * Disbursement and the instalments the member has actually settled.
     *
     * Only a loan that has been released is posted. A loan that is still
     * pending review, or that has been signed off but is still waiting for
     * release, leaves no trace on the ledger.
     *
     * Loan disbursements default to physical cash (`account_id = null`) unless
     * the loan was explicitly released to the member's savings ledger. Loan
     * repayments are always funded externally (`account_id = null`) and carry
     * their corresponding `loan_payment_installment_allocations` row.
     *
     * @param  list<array{period: int, date: Carbon, amount: float}>  $installments
     */
    private function loanActivity(
        LedgerWriter $ledger,
        Account $savings,
        Loan $loan,
        array $installments,
        ?int $teller,
        ?int $loanOfficer
    ): void {
        if (! $loan->approved_at || ! in_array($loan->loan_status, ['active', 'paid_off'], true)) {
            return;
        }

        $disbursedToSavings = $loan->account_id !== null;
        $disbursementAccount = $disbursedToSavings ? $savings : null;
        $disbursementNote = $disbursedToSavings
            ? 'Loan disbursed to savings account.'
            : 'Loan disbursed as physical cash.';

        $this->postOnce($ledger, $disbursementAccount, [
            'transaction_type' => 'loan_disbursement',
            'loan_id' => $loan->id,
            'branch_id' => $loan->branch_id,
            'amount' => (float) $loan->loan_amount,
            'transaction_date' => $loan->disbursed_at ?? $loan->approved_at,
            'recorded_by' => $loanOfficer,
            'transaction_mode' => 'counter',
            'note' => $disbursementNote,
        ]);

        foreach ($installments as $instalment) {
            $payment = $this->postOnce($ledger, null, [
                'transaction_type' => 'loan_payment',
                'loan_id' => $loan->id,
                'branch_id' => $loan->branch_id,
                'amount' => $instalment['amount'],
                'penalty_amount' => 0.0,
                'transaction_date' => $instalment['date'],
                'recorded_by' => $teller,
                'transaction_mode' => 'counter',
                'note' => 'Instalment '.$instalment['period'].' of '.$loan->term_months,
            ]);

            if ($payment) {
                DB::table('loan_payment_installment_allocations')->updateOrInsert(
                    [
                        'transaction_id' => $payment->id,
                        'period' => $instalment['period'],
                    ],
                    [
                        'amount' => $instalment['amount'],
                        'created_at' => $instalment['date'],
                        'updated_at' => $instalment['date'],
                    ]
                );
            }
        }
    }

    /**
     * A handful of requests waiting in the approval queue.
     *
     * Counter transactions made by an officer are booked as completed the moment
     * they are made, whereas online or bank-transfer requests wait for approval.
     *
     * @param  Collection<string, User>  $members
     */
    private function pendingQueue(LedgerWriter $ledger, $members, ?int $teller): void
    {
        $proofPath = $this->ensureProofPlaceholder();

        $queue = [
            ['user' => 'USR000009', 'type' => 'deposit', 'amount' => 5000, 'ago_days' => 2, 'mode' => 'bank_transfer', 'note' => 'Additional savings deposit via bank transfer', 'proof' => $proofPath],
            ['user' => 'USR000015', 'type' => 'deposit', 'amount' => 2000, 'ago_days' => 1, 'mode' => 'online', 'note' => 'Savings top-up paid online', 'proof' => $proofPath],
            ['user' => 'USR000011', 'type' => 'withdrawal', 'amount' => 15000, 'ago_days' => 4, 'mode' => 'bank_transfer', 'note' => 'Withdrawal request for school fees | Bank: Land Bank of the Philippines · 1892-0441-29 (Jean Leslie Velasco)'],
        ];

        foreach ($queue as $item) {
            $user = $members[$item['user']] ?? null;

            if (! $user) {
                continue;
            }

            $savings = Account::query()
                ->where('user_id', $user->id)
                ->where('account_type', 'savings')
                ->first();

            if (! $savings) {
                continue;
            }

            $date = Carbon::now()->subDays($item['ago_days'])->setTime(16, 45);

            $this->postOnce($ledger, $savings, [
                'transaction_type' => $item['type'],
                'amount' => $item['amount'],
                'transaction_date' => $date,
                'recorded_by' => $user->id,
                'transaction_mode' => $item['mode'],
                'proof_of_payment_path' => $item['proof'] ?? null,
                'proof_of_payment_uploaded_at' => isset($item['proof']) ? $date : null,
                'note' => $item['note'],
            ], 'pending');
        }

        // One external loan payment submitted via bank transfer and awaiting verification.
        $arjay = $members['USR000007'] ?? null;
        $arjayLoan = $arjay ? Loan::query()->where('user_id', $arjay->id)->where('loan_status', 'active')->first() : null;

        if ($arjay && $arjayLoan) {
            $schedule = $arjayLoan->paymentSchedule() ?? [];
            $nextUnpaid = collect($schedule)->first(fn (array $row) => (float) $row['amount_due'] > 0.01);

            if ($nextUnpaid) {
                $date = Carbon::now()->subDay()->setTime(15, 20);
                $installmentAmount = round((float) $nextUnpaid['amount_due'], 2);
                $penaltyAmount = round((float) $arjayLoan->accruedPenalty(), 2);
                $totalAmount = round($installmentAmount + $penaltyAmount, 2);

                $pendingPayment = $this->postOnce($ledger, null, [
                    'transaction_type' => 'loan_payment',
                    'loan_id' => $arjayLoan->id,
                    'branch_id' => $arjayLoan->branch_id,
                    'amount' => $totalAmount,
                    'penalty_amount' => $penaltyAmount,
                    'transaction_date' => $date,
                    'recorded_by' => $arjay->id,
                    'transaction_mode' => 'bank_transfer',
                    'proof_of_payment_path' => $proofPath,
                    'proof_of_payment_uploaded_at' => $date,
                    'note' => 'Bank transfer payment for overdue instalment #'.$nextUnpaid['period'],
                ], 'pending');

                if ($pendingPayment) {
                    DB::table('loan_payment_installment_allocations')->updateOrInsert(
                        [
                            'transaction_id' => $pendingPayment->id,
                            'period' => (int) $nextUnpaid['period'],
                        ],
                        [
                            'amount' => $installmentAmount,
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]
                    );
                }
            }
        }
    }

    /**
     * Historical requests that were turned down during officer review over the
     * past six months. Rejected rows never move an account balance.
     *
     * @param  Collection<string, User>  $members
     */
    private function rejectedHistory(LedgerWriter $ledger, $members, ?int $reviewer): void
    {
        $proofPath = $this->ensureProofPlaceholder();

        $rejected = [
            [
                'user' => 'USR000018',
                'type' => 'deposit',
                'amount' => 3500,
                'ago_days' => 95,
                'mode' => 'bank_transfer',
                'proof' => $proofPath,
                'note' => 'Bank transfer savings deposit',
                'rejection_reason' => 'Uploaded transfer slip was blurred and reference number could not be matched against the bank statement.',
            ],
            [
                'user' => 'USR000024',
                'type' => 'withdrawal',
                'amount' => 8000,
                'ago_days' => 52,
                'mode' => 'bank_transfer',
                'proof' => null,
                'note' => 'Withdrawal request | Bank: BDO Unibank · 0041-2890-11 (Kevin Paul Navarro)',
                'rejection_reason' => 'Destination bank account number returned an invalid beneficiary name during verification.',
            ],
        ];

        foreach ($rejected as $item) {
            $user = $members[$item['user']] ?? null;
            $savings = $user ? Account::query()->where('user_id', $user->id)->where('account_type', 'savings')->first() : null;

            if (! $user || ! $savings) {
                continue;
            }

            $date = Carbon::now()->subDays($item['ago_days'])->setTime(11, 15);

            $this->postOnce($ledger, $savings, [
                'transaction_type' => $item['type'],
                'amount' => $item['amount'],
                'transaction_date' => $date,
                'recorded_by' => $user->id,
                'transaction_mode' => $item['mode'],
                'proof_of_payment_path' => $item['proof'],
                'proof_of_payment_uploaded_at' => $item['proof'] ? $date : null,
                'note' => $item['note'],
                'rejection_reason' => $item['rejection_reason'],
            ], 'rejected');
        }
    }

    /**
     * A historical mistyped counter entry that was reversed the same afternoon
     * through the cooperative's reversal workflow, so the Corrections filter on
     * the ledger shows a realistic audit trail while leaving net balances unchanged.
     *
     * @param  Collection<string, User>  $members
     */
    private function reversalHistory(LedgerWriter $ledger, $members, ?int $teller): void
    {
        $user = $members['USR000008'] ?? null;
        $savings = $user ? Account::query()->where('user_id', $user->id)->where('account_type', 'savings')->first() : null;

        if (! $user || ! $savings) {
            return;
        }

        $mistypedDate = Carbon::now()->subMonths(3)->startOfMonth()->day(18)->setTime(10, 20);
        $reversalDate = $mistypedDate->copy()->addMinutes(25);

        $original = $this->postOnce($ledger, $savings, [
            'transaction_type' => 'deposit',
            'amount' => 4500.00,
            'transaction_date' => $mistypedDate,
            'recorded_by' => $teller,
            'transaction_mode' => 'counter',
            'note' => 'Counter deposit (encoded under wrong passbook number)',
        ]);

        if (! $original) {
            return;
        }

        $this->postOnce($ledger, $savings, [
            'transaction_type' => 'deposit',
            'amount' => 4500.00,
            'transaction_date' => $reversalDate,
            'recorded_by' => $teller,
            'transaction_mode' => 'counter',
            'reversal_of_id' => $original->id,
            'note' => 'Reversal of '.$original->transaction_number,
        ]);
    }

    private function ensureProofPlaceholder(): string
    {
        $path = 'transaction-proofs/sample-transfer-slip.png';

        if (! Storage::disk('public')->exists($path)) {
            $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
            Storage::disk('public')->put($path, $bytes);
        }

        return $path;
    }

    /**
     * Post a row unless the same row is already on the ledger.
     *
     * Identity is the account (or loan when account_id is null), the type, the
     * business date and the note — not the amount. A member makes one savings
     * contribution per month, so a re-seed has to recognise that row even
     * though the derived amount could differ, while a withdrawal queued for
     * today must not be mistaken for the one the pattern already put there.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function postOnce(LedgerWriter $ledger, ?Account $account, array $attributes, string $status = 'completed'): ?Transaction
    {
        $date = $attributes['transaction_date'];

        $existing = Transaction::query()
            ->when(
                $account !== null,
                fn ($q) => $q->where('account_id', $account->id),
                fn ($q) => $q->whereNull('account_id')->where('loan_id', $attributes['loan_id'] ?? null)
            )
            ->where('transaction_type', $attributes['transaction_type'])
            ->where('note', $attributes['note'] ?? null)
            ->whereDate('transaction_date', $date->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        $attributes['account_id'] = $account?->id;
        $attributes['transaction_date'] = $date;

        return match ($status) {
            'pending' => $ledger->queue($attributes),
            'rejected' => $ledger->reject($attributes),
            default => $ledger->post($attributes),
        };
    }

    private function recomputeAll(): void
    {
        $ledger = new LedgerWriter;

        Account::query()->whereNotNull('user_id')->chunk(50, function ($accounts) use ($ledger) {
            foreach ($accounts as $account) {
                $ledger->recomputeBalance($account);
            }
        });
    }

    /**
     * The invariants the rest of the app assumes, checked rather than hoped for.
     */
    private function assertBalances(): void
    {
        $ledger = new LedgerWriter;

        foreach (Account::query()->whereNotNull('user_id')->get() as $account) {
            if ((float) $account->balance < 0) {
                throw new \RuntimeException("Account {$account->id} ended up with a negative balance of {$account->balance}.");
            }

            $running = 0.0;
            $rows = Transaction::query()
                ->where('account_id', $account->id)
                ->where('status', 'completed')
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get(['transaction_type', 'amount', 'transaction_date', 'reversal_of_id']);

            foreach ($rows as $row) {
                $running += LedgerWriter::balanceEffect(
                    $row->transaction_type,
                    (float) $row->amount,
                    $row->reversal_of_id !== null
                );

                if ($running < -0.005) {
                    throw new \RuntimeException(sprintf(
                        'Account %d goes negative on %s: %s of %.2f against a balance of %.2f.',
                        $account->id,
                        $row->transaction_date?->format('Y-m-d'),
                        $row->transaction_type,
                        $row->amount,
                        $running
                    ));
                }
            }
        }

        foreach (Loan::query()->where('loan_status', 'paid_off')->get() as $loan) {
            $paid = (float) $loan->totalPaid();
            $payable = $loan->totalPayable();

            if (abs($paid - $payable) > 0.005) {
                throw new \RuntimeException(sprintf(
                    'Loan %s is marked paid off but has paid %.2f of %.2f.',
                    $loan->loan_number,
                    $paid,
                    $payable
                ));
            }
        }

        $future = Transaction::query()
            ->where('status', 'completed')
            ->where('transaction_date', '>', Carbon::now()->addDay())
            ->count();

        if ($future > 0) {
            throw new \RuntimeException("{$future} completed transactions are dated in the future.");
        }
    }

    /**
     * Guard against a contribution that no plausible income could support.
     *
     * @param  array<string, mixed>  $member
     */
    private function assertAffordable(array $member, float $contribution, float $monthlySpend): void
    {
        $income = (float) $member['employment']['monthly_income'];
        $outgoings = $contribution + $monthlySpend;

        if ($income > 0 && $outgoings > $income * 0.6) {
            throw new \RuntimeException(sprintf(
                'Savings plan for %s commits %.2f of a %.2f monthly income.',
                $member['user_number'],
                $outgoings,
                $income
            ));
        }
    }
}
