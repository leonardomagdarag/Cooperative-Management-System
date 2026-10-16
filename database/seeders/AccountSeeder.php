<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Support\LedgerWriter;
use Database\Seeders\Support\MemberRoster;
use Illuminate\Database\Seeder;

/**
 * Opens each member's savings and share-capital accounts.
 *
 * This seeder owns everything that happened *before* the member started saving
 * on a regular basis: the membership fee collected on joining, the opening share
 * capital subscription, and the annual additional subscriptions that build it
 * up. Ongoing monthly savings, withdrawals and loan movements belong to
 * TransactionSeeder, which runs later and re-derives every balance from the
 * full ledger.
 *
 * Owning the whole share-capital subscription series here means LoanSeeder can
 * read a real share-capital balance — and a real balance *as of the application
 * date* — when it checks product eligibility, instead of trusting a hand-written
 * figure.
 */
class AccountSeeder extends Seeder
{
    /** The cooperative's standard membership fee. */
    private const MEMBERSHIP_FEE = 500.00;

    public function run(): void
    {
        $members = User::query()
            ->memberUsers()
            ->whereIn('user_number', collect(MemberRoster::all())->pluck('user_number'))
            ->get()
            ->keyBy('user_number');

        if ($members->isEmpty()) {
            $this->command?->error('No rostered members found. Please run UserSeeder first.');

            return;
        }

        $ledger = new LedgerWriter;
        $accountant = $ledger->officer('USR000004');

        foreach (MemberRoster::all() as $member) {
            $user = $members[$member['user_number']] ?? null;

            if (! $user) {
                continue;
            }

            $joinedAt = MemberRoster::joinedAt($member);

            $savings = $this->account($user, 'savings', $joinedAt);
            $shareCapital = $this->account($user, 'share-capital', $joinedAt);

            // The membership fee is charged once, on the savings account, and
            // never debits a balance — it is recorded income, not a movement.
            $this->once($ledger, $savings, [
                'transaction_type' => 'fee',
                'amount' => self::MEMBERSHIP_FEE,
                'transaction_date' => $joinedAt,
                'recorded_by' => $accountant?->id,
                'note' => 'Membership fee on joining',
            ]);

            // A small opening savings deposit so the account is not sitting at
            // zero from the day the member joined.
            $this->once($ledger, $savings, [
                'transaction_type' => 'deposit',
                'amount' => 500,
                'transaction_date' => $joinedAt->copy()->addMinutes(30),
                'recorded_by' => $accountant?->id,
                'note' => 'Opening savings deposit',
            ]);

            $this->shareCapitalSeries($ledger, $shareCapital, $member, $joinedAt, $accountant?->id);
        }

        $this->recomputeAll();

        $this->command?->info('Opened savings and share-capital accounts for '.$members->count().' members.');
    }

    /**
     * Find or open a member's account of the given type.
     *
     * Accounts are opened on the day the member joined, not on the day the
     * seeder happens to run, so account age lines up with the transaction
     * history underneath it.
     */
    private function account(User $user, string $type, Carbon $openedAt): Account
    {
        $existing = Account::query()
            ->where('user_id', $user->id)
            ->where('account_type', $type)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Account::withoutTimestamps(function () use ($user, $type, $openedAt) {
            $account = new Account;
            $account->forceFill([
                'user_id' => $user->id,
                'account_type' => $type,
                'balance' => 0,
            ]);
            $account->created_at = $openedAt;
            $account->updated_at = $openedAt;
            $account->save();

            return $account;
        });
    }

    /**
     * Post an entry unless an identical one already exists.
     *
     * The business date is part of the identity: a member pays a membership
     * fee once, but subscribes additional share capital on every anniversary,
     * and those rows share a note.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function once(LedgerWriter $ledger, Account $account, array $attributes): void
    {
        $date = $attributes['transaction_date'] instanceof Carbon
            ? $attributes['transaction_date']
            : Carbon::parse($attributes['transaction_date']);

        $alreadyPosted = Transaction::query()
            ->where('account_id', $account->id)
            ->where('transaction_type', $attributes['transaction_type'])
            ->where('note', $attributes['note'])
            ->whereDate('transaction_date', $date->toDateString())
            ->exists();

        if ($alreadyPosted) {
            return;
        }

        $ledger->post(array_merge($attributes, [
            'account_id' => $account->id,
            'transaction_date' => $date,
            'transaction_mode' => $attributes['transaction_mode'] ?? 'counter',
        ]));
    }

    /**
     * The opening subscription plus one additional subscription per completed
     * membership year, dated on each anniversary.
     *
     * @param  array<string, mixed>  $member
     */
    private function shareCapitalSeries(
        LedgerWriter $ledger,
        Account $account,
        array $member,
        Carbon $joinedAt,
        ?int $recordedBy
    ): void {
        $plan = $member['share_capital'];
        $opening = (float) $plan['opening'];
        $annual = (float) $plan['annual_top_up'];

        if ($opening > 0) {
            $this->once($ledger, $account, [
                'transaction_type' => 'deposit',
                'amount' => $opening,
                'transaction_date' => $joinedAt->copy()->addMinutes(45),
                'recorded_by' => $recordedBy,
                'note' => 'Initial share capital subscription',
            ]);
        }

        if ($annual <= 0) {
            return;
        }

        $anniversary = $joinedAt->copy()->addYear();

        while ($anniversary->isPast()) {
            $this->once($ledger, $account, [
                'transaction_type' => 'deposit',
                'amount' => $annual,
                'transaction_date' => $anniversary,
                'recorded_by' => $recordedBy,
                'note' => 'Annual additional share capital subscription',
            ]);

            $anniversary = $anniversary->copy()->addYear();
        }
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
}
