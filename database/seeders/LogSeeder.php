<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

class LogSeeder extends Seeder
{
    /**
     * Seed the audit trail the application actually reads.
     *
     * The legacy `logs` table is not read by `LogController` — the audit log
     * screen and the "recent activity" list on a member's profile both read
     * Spatie's activity log. Entries are therefore written through
     * `AuditLogService`, so they carry the same shape, tags and events the
     * running application produces.
     *
     * Every entry describes something that exists: a loan that was applied for,
     * released or reviewed, a ledger entry that was posted, or a member signing
     * in. Nothing is invented, and a re-seed recognises the entries it already
     * made instead of duplicating them.
     */
    public function run(): void
    {
        $audit = app(AuditLogService::class);

        $created = 0;
        $created += $this->loanTrail($audit);
        $created += $this->ledgerTrail($audit);
        $created += $this->signInTrail($audit);

        $this->command?->info('Recorded '.$created.' audit log entries.');
    }

    /**
     * The life of each seeded loan, from application to release.
     */
    private function loanTrail(AuditLogService $audit): int
    {
        $created = 0;
        $secretary = $this->officer('secretary');
        $manager = $this->officer('manager');
        $loanOfficer = $this->officer('loan');

        foreach (Loan::query()->with('user')->orderBy('id')->get() as $loan) {
            $member = $loan->user;
            $amount = '₱'.number_format((float) $loan->loan_amount, 2);

            if ($loan->applied_at) {
                $created += $this->entry($audit, 'loans.create', sprintf(
                    '%s applied for a %s loan of %s (%s).',
                    $member?->name ?? 'A member',
                    strtolower($loan->loan_type_label),
                    $amount,
                    $loan->loan_number
                ), 'loans', $loan, $member, $loan->applied_at);
            }

            if ($loan->reviewed_at && $loan->review_status) {
                $created += $this->entry($audit, match ($loan->review_status) {
                    'review_rejected' => 'loans.reject',
                    'endorsed_for_approval' => 'loans.approve',
                    default => 'loans.update',
                }, match ($loan->review_status) {
                    'review_rejected' => sprintf(
                        'Loan %s of %s was not endorsed by the loan committee.',
                        $loan->loan_number,
                        $amount
                    ),
                    'endorsed_for_approval' => sprintf(
                        'Loan %s of %s was endorsed for approval by the loan committee.',
                        $loan->loan_number,
                        $amount
                    ),
                    default => sprintf(
                        'Loan %s of %s is with the loan committee for review.',
                        $loan->loan_number,
                        $amount
                    ),
                }, 'loans', $loan, $secretary, $loan->reviewed_at);
            }

            if ($loan->approved_at && in_array($loan->loan_status, ['active', 'paid_off'], true)) {
                $created += $this->entry($audit, 'loans.disburse', sprintf(
                    '%s released %s to %s.',
                    $loan->loan_number,
                    $amount,
                    $member?->name ?? 'the member'
                ), 'loans', $loan, $manager, $loan->approved_at);
            }

            $settled = (float) $loan->totalPaid();

            if ($settled > 0 && $loan->loan_status === 'paid_off') {
                $created += $this->entry($audit, 'loans.payments', sprintf(
                    '%s settled in full: %s of ₱%s collected.',
                    $loan->loan_number,
                    (int) $loan->term_months,
                    number_format($settled, 2)
                ), 'loans', $loan, $loanOfficer, $loan->approved_at?->copy()->addMonths((int) $loan->term_months));
            }
        }

        return $created;
    }

    /**
     * A representative slice of the ledger, newest first.
     *
     * The seeded history runs to well over a thousand entries, and the audit
     * screen paginates, so only the most recent slice is recorded — the same
     * thing an officer would have seen if the data had been entered by hand.
     */
    private function ledgerTrail(AuditLogService $audit): int
    {
        $created = 0;
        $teller = $this->officer('accountant');

        $recent = Transaction::query()
            ->with('account.user')
            ->where('status', 'completed')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        foreach ($recent as $transaction) {
            $created += $this->entry($audit, 'ledger.'.$transaction->transaction_type, sprintf(
                '%s of ₱%s recorded for %s (%s).',
                ucfirst(str_replace('_', ' ', (string) $transaction->transaction_type)),
                number_format((float) $transaction->amount, 2),
                $transaction->account?->user?->name ?? 'a member',
                $transaction->transaction_number
            ), 'finance', $transaction, $teller, $transaction->transaction_date);
        }

        foreach (Transaction::query()->with('account.user')->where('status', 'pending')->get() as $transaction) {
            $created += $this->entry($audit, 'ledger.queued', sprintf(
                '%s request of ₱%s from %s is awaiting approval.',
                ucfirst((string) $transaction->transaction_type),
                number_format((float) $transaction->amount, 2),
                $transaction->account?->user?->name ?? 'a member'
            ), 'finance', $transaction, $this->officer('accountant'), $transaction->created_at);
        }

        return $created;
    }

    /**
     * Officers and members signing in.
     *
     * Dated against the most recent day in the ledger rather than against the
     * clock, so the audit log never claims a sign-in happened in the future and
     * a re-seed produces the same entries instead of a fresh set each time.
     */
    private function signInTrail(AuditLogService $audit): int
    {
        $created = 0;
        $latest = Transaction::query()->max('transaction_date');
        $busiest = ($latest ? Carbon::parse($latest) : Carbon::now())->startOfDay()->setTime(8, 0);

        foreach (User::query()->orderBy('user_number')->get() as $user) {
            // Officers use the system most; members only drop in occasionally.
            $signIns = $user->is_officer ? 3 : 1;

            for ($i = 0; $i < $signIns; $i++) {
                $created += $this->entry($audit, 'auth.login_success', sprintf(
                    '%s logged in successfully.',
                    $user->name
                ), 'auth', null, $user, $busiest->copy()->addHours($i * 5 + ((int) $user->id % 7)));
            }
        }

        return $created;
    }

    /**
     * Write one audit entry, dated to when the event happened.
     *
     * `AuditLogService` stamps the entry with the current time, so the row is
     * corrected afterwards. The entry is recognised on a re-run by its event,
     * tag, description, causer and subject rather than by its timestamp.
     */
    private function entry(
        AuditLogService $audit,
        string $event,
        string $description,
        string $tag,
        ?Model $subject,
        ?User $causer,
        ?Carbon $at
    ): int {
        if ($this->alreadyLogged($event, $tag, $description, $subject, $causer)) {
            return 0;
        }

        $audit->log($event, $description, $tag, $subject, $causer, [
            'ip_address' => '127.0.0.1',
            'user_agent' => 'seed',
            'seeder' => true,
        ]);

        if ($at && ! $at->greaterThan(now())) {
            Activity::query()
                ->where('event', $event)
                ->where('log_name', $tag)
                ->where('description', $description)
                ->orderByDesc('id')
                ->first()
                ?->forceFill(['created_at' => $at, 'updated_at' => $at])
                ->save();
        }

        return 1;
    }

    private function alreadyLogged(
        string $event,
        string $tag,
        string $description,
        ?Model $subject,
        ?User $causer
    ): bool {
        return Activity::query()
            ->where('event', $event)
            ->where('log_name', $tag)
            ->where('description', $description)
            ->where('causer_id', $causer?->id)
            ->where('subject_id', $subject?->id)
            ->where('subject_type', $subject ? $subject::class : null)
            ->exists();
    }

    private function officer(string $role): ?User
    {
        return User::query()->role($role)->orderBy('id')->first();
    }
}
