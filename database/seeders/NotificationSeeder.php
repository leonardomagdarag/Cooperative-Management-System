<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class NotificationSeeder extends Seeder
{
    /**
     * Notifications that correspond to something the member actually did.
     *
     * Every row is tied to a real account, loan or transaction from the other
     * seeders, so the bell icon agrees with the rest of the dashboard. Nothing
     * is invented: a member is told about a loan they hold or a payment that
     * settled it, and the amounts are the amounts on the ledger.
     */
    public function run(): void
    {
        $created = 0;

        $created += $this->loanReminders();
        $created += $this->loanDecisions();
        $created += $this->settledPayments();
        $created += $this->withdrawalUpdates();
        $created += $this->welcomeMessages();

        $this->command?->info('Created '.$created.' notifications from seeded activity.');
    }

    /**
     * Upcoming instalments on loans that are still running.
     *
     * The reminder window follows the application's own 7/3/1 day schedule, so a
     * member is notified on a day that maps to a real due date rather than at
     * an arbitrary point in the month.
     */
    private function loanReminders(): int
    {
        $created = 0;
        $now = Carbon::now();

        foreach (Loan::query()->with('user')->where('loan_status', 'active')->get() as $loan) {
            $schedule = $loan->paymentSchedule();

            if (! $schedule || ! $loan->user) {
                continue;
            }

            $settled = $this->settledPeriods($loan);
            $unpaid = array_values(array_filter(
                $schedule,
                fn (array $row) => ! in_array($row['period'], $settled, true)
            ));

            $next = $unpaid[0] ?? null;

            if (! $next) {
                continue;
            }

            $dueDate = Carbon::parse($next['due_date']);
            $days = (int) $now->startOfDay()->diffInDays($dueDate->copy()->startOfDay(), false);

            // A loan that is already behind is a different kind of message, but
            // the notifications table keys both off the same `loan_due` type.
            if ($days < 0) {
                if ($this->alreadySent($loan->user, 'loan_due', $loan, 'Loan Instalment Overdue')) {
                    continue;
                }

                $this->put($loan->user, 'loan_due', 'Loan Instalment Overdue', sprintf(
                    'Your instalment of ₱%s for %s was due on %s. Please settle it at the counter to avoid penalties.',
                    number_format((float) $next['payment'], 2),
                    $loan->loan_number,
                    $dueDate->format('F j, Y')
                ), 'urgent', $loan);

                $created++;

                continue;
            }

            foreach ([7, 3, 1] as $window) {
                if ($days !== $window) {
                    continue;
                }

                if ($this->alreadySent($loan->user, 'loan_due', $loan)) {
                    continue;
                }

                $this->put($loan->user, 'loan_due', 'Loan Payment Due Soon', sprintf(
                    'Your instalment of ₱%s for %s is due in %d day(s), on %s.',
                    number_format((float) $next['payment'], 2),
                    $loan->loan_number,
                    $days,
                    $dueDate->format('F j, Y')
                ), match ($window) {
                    1 => 'urgent',
                    3 => 'high',
                    default => 'normal',
                }, $loan);

                $created++;
            }
        }

        return $created;
    }

    /**
     * The outcome of a loan application that has already been decided.
     *
     * The loan's `review_status` says how the committee treated the application
     * and its `loan_status` says how far it got, so an approval notice is only
     * sent to a member whose loan was actually endorsed and released.
     */
    private function loanDecisions(): int
    {
        $created = 0;

        $endorsed = Loan::query()
            ->with('user')
            ->where('review_status', 'endorsed_for_approval')
            ->whereIn('loan_status', ['approved', 'active', 'paid_off'])
            ->get();

        foreach ($endorsed as $loan) {
            if (! $loan->user || $this->alreadySent($loan->user, 'loan_approved', $loan)) {
                continue;
            }

            $this->put($loan->user, 'loan_approved', 'Loan Application Approved', sprintf(
                'Your %s loan of ₱%s (%s) has been approved and endorsed. Please visit the cooperative to receive your release.',
                strtolower($loan->loan_type_label),
                number_format((float) $loan->loan_amount, 2),
                $loan->loan_number
            ), 'high', $loan, read: true);

            $created++;
        }

        $declined = Loan::query()
            ->with('user')
            ->where('review_status', 'review_rejected')
            ->get();

        foreach ($declined as $loan) {
            if (! $loan->user || $this->alreadySent($loan->user, 'loan_rejected', $loan)) {
                continue;
            }

            $this->put($loan->user, 'loan_rejected', 'Loan Application Not Approved', sprintf(
                'We regret to inform you that your %s loan of ₱%s (%s) was not endorsed. %s',
                strtolower($loan->loan_type_label),
                number_format((float) $loan->loan_amount, 2),
                $loan->loan_number,
                $loan->reviewer_note ?: 'Please discuss the requirements with the loan officer.'
            ), 'normal', $loan, read: true);

            $created++;
        }

        return $created;
    }

    /**
     * A receipt for the most recent instalment each member actually paid.
     */
    private function settledPayments(): int
    {
        $created = 0;

        $latest = Transaction::query()
            ->where('transaction_type', 'loan_payment')
            ->where('status', 'completed')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('loan_id')
            ->map(fn ($rows) => $rows->first());

        foreach ($latest as $loanId => $payment) {
            $loan = Loan::with('user')->find($loanId);

            if (! $loan?->user || $this->alreadySent($loan->user, 'loan_payment', $loan)) {
                continue;
            }

            $remaining = (float) $loan->totalPayable() - (float) $loan->totalPaid();

            $this->put($loan->user, 'loan_payment', 'Payment Received', sprintf(
                'We have received your payment of ₱%s for %s. Remaining balance: ₱%s.',
                number_format((float) $payment->amount, 2),
                $loan->loan_number,
                number_format(max(0.0, $remaining), 2)
            ), 'normal', $loan, read: true);

            $created++;
        }

        return $created;
    }

    /**
     * A member's own withdrawal request, still sitting in the queue.
     *
     * The notifications table has no withdrawal-specific type, so the request
     * is filed as a general notice pointing at the member's own transactions.
     */
    private function withdrawalUpdates(): int
    {
        $created = 0;

        $pending = Transaction::query()
            ->with('account.user')
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        foreach ($pending as $withdrawal) {
            $user = $withdrawal->account?->user;

            if (! $user || $this->alreadySent($user, 'general', $withdrawal)) {
                continue;
            }

            $this->put($user, 'general', 'Withdrawal Awaiting Approval', sprintf(
                'Your withdrawal request of ₱%s submitted on %s is awaiting the manager\'s approval.',
                number_format((float) $withdrawal->amount, 2),
                Carbon::parse($withdrawal->created_at)->format('F j, Y')
            ), 'normal', $withdrawal);

            $created++;
        }

        return $created;
    }

    /**
     * A short welcome for members who have just joined, dated to the day their
     * membership began so the notice is not newer than the member.
     *
     * The cooperative counts membership from the user's creation date, which the
     * UserSeeder backdates to the join day.
     */
    private function welcomeMessages(): int
    {
        $created = 0;

        foreach (User::query()->role('member')->get() as $member) {
            if (! $member->created_at || $this->alreadySent($member, 'general', null, 'Welcome to Settlers Credit Cooperative')) {
                continue;
            }

            $sent = $member->created_at->copy()->addDay();

            $notification = Notification::create([
                'user_id' => $member->id,
                'type' => 'general',
                'title' => 'Welcome to Settlers Credit Cooperative',
                'message' => 'Thank you for joining. Your savings and share capital accounts are open, '
                    .'and you can apply for a loan once you have been a member for one year.',
                'priority' => 'normal',
                'action_url' => '/dashboard',
                'is_read' => true,
                'read_at' => $sent->copy()->addDay(),
            ]);

            $notification->forceFill(['created_at' => $sent, 'updated_at' => $sent])->save();

            $created++;
        }

        return $created;
    }

    /**
     * Instalment periods already covered by a completed payment.
     *
     * The ledger records an amount and a date rather than a period number, so
     * the period is recovered by matching each settled date back to the
     * schedule.
     *
     * @return list<int>
     */
    private function settledPeriods(Loan $loan): array
    {
        $dates = Transaction::query()
            ->where('loan_id', $loan->id)
            ->where('transaction_type', 'loan_payment')
            ->where('status', 'completed')
            ->pluck('transaction_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $periods = [];

        foreach ($loan->paymentSchedule() ?? [] as $row) {
            $due = Carbon::parse($row['due_date'])->toDateString();

            if (in_array($due, $dates, true)) {
                $periods[] = (int) $row['period'];
            }
        }

        return $periods;
    }

    private function alreadySent(User $user, string $type, ?object $related, ?string $title = null): bool
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->when($title, fn ($query) => $query->where('title', $title))
            ->when($related, fn ($query) => $query
                ->where('related_id', $related->id)
                ->where('related_type', $related instanceof Loan ? Loan::class : Transaction::class))
            ->exists();
    }

    private function put(
        User $user,
        string $type,
        string $title,
        string $message,
        string $priority,
        object $related,
        bool $read = false
    ): void {
        Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'priority' => $priority,
            'action_url' => $related instanceof Loan ? '/loans' : '/transactions',
            'related_id' => $related->id,
            'related_type' => $related instanceof Loan ? Loan::class : Transaction::class,
            'is_read' => $read,
            'read_at' => $read ? Carbon::now() : null,
        ]);
    }
}
