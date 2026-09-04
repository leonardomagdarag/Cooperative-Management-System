<?php

namespace App\Services;

use App\Jobs\SendAnnouncementEmailJob;
use App\Jobs\SendEmailJob;
use App\Jobs\SendSecurityAlertJob;
use App\Mail\EmailVerificationMail;
use App\Mail\LoanDueNotification;
use App\Mail\LoanStatusNotification;
use App\Mail\WithdrawalStatusNotification;
use App\Models\Announcement;
use App\Models\Loan;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Throwable;

class NotificationService
{
    private function resolveUserDashboardUrl(User $user): string
    {
        return $this->routeIfExists('dashboard', '/dashboard');
    }

    private function routeIfExists(string $routeName, string $fallback, array $parameters = []): string
    {
        return Route::has($routeName) ? route($routeName, $parameters) : $fallback;
    }

    /**
     * Safely dispatch an email job with sync fallback.
     */
    private function safeSendEmail(string $toEmail, $mailable, string $tag = 'email.sent'): void
    {
        if (! $toEmail || config('mail.default') === 'log') {
            return;
        }

        try {
            SendEmailJob::dispatch($toEmail, $mailable, $tag);
        } catch (Throwable $e) {
            Log::warning("Failed to dispatch email job, falling back to sync: {$e->getMessage()}");
            try {
                Mail::to($toEmail)->send($mailable);
            } catch (Throwable $e2) {
                Log::error("Sync email fallback also failed for {$toEmail}: {$e2->getMessage()}");
                app(AuditLogService::class)->log(
                    'email.failed',
                    "Both async and sync email failed for {$toEmail}: {$e2->getMessage()}",
                    'email', null, null,
                    ['to' => $toEmail, 'mailable' => get_class($mailable)]
                );
            }
        }
    }

    /**
     * Create a notification for a user
     */
    public function create(
        User $user,
        string $type,
        string $title,
        string $message,
        string $priority = 'normal',
        ?string $actionUrl = null,
        $relatedModel = null,
        ?\DateTime $expiresAt = null
    ): Notification {
        $data = [
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'priority' => $priority,
            'action_url' => $actionUrl,
            'expires_at' => $expiresAt,
        ];

        if ($relatedModel) {
            $data['related_id'] = $relatedModel->id;
            $data['related_type'] = get_class($relatedModel);
        }

        return Notification::create($data);
    }

    /**
     * Create notification for multiple users
     */
    public function createForUsers(
        Collection $users,
        string $type,
        string $title,
        string $message,
        string $priority = 'normal',
        ?string $actionUrl = null,
        $relatedModel = null,
        ?\DateTime $expiresAt = null
    ): void {
        foreach ($users as $user) {
            $this->create($user, $type, $title, $message, $priority, $actionUrl, $relatedModel, $expiresAt);
        }
    }

    /**
     * Create announcement notification for all users
     */
    public function notifyNewAnnouncement(Announcement $announcement, bool $sendEmail = true): void
    {
        $visibility = (array) ($announcement->visibility ?? ['members']);

        if (in_array('everyone', $visibility, true)) {
            $users = User::all();
        } else {
            $userIds = collect();

            if (in_array('members', $visibility, true)) {
                $userIds = $userIds->merge(User::role('member')->pluck('id'));
            }

            if (in_array('officers', $visibility, true)) {
                $officerRoles = ['admin', 'manager', 'secretary', 'accountant', 'loan', 'developer'];
                $userIds = $userIds->merge(User::role($officerRoles)->pluck('id'));
            }

            if ($userIds->isEmpty()) {
                return;
            }

            $users = User::whereIn('id', $userIds->unique())->get();
        }

        $this->createForUsers(
            $users,
            'announcement',
            'New Announcement: '.$announcement->title,
            substr($announcement->content, 0, 100).'...',
            'normal',
            '/news',
            $announcement
        );

        if ($sendEmail && config('mail.default') !== 'log') {
            $userIds = $users->filter(fn ($u) => $u->email)->pluck('id')->toArray();
            if (! empty($userIds)) {
                try {
                    $batch = Bus::batch(
                        array_map(
                            fn ($userId) => new SendAnnouncementEmailJob($announcement, $userId),
                            $userIds
                        )
                    )->name('announcement-emails')->dispatch();

                    Log::info("Announcement email batch dispatched for {$batch->total()} users");
                } catch (Throwable $e) {
                    Log::error("Failed to dispatch announcement email batch: {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * Create loan due notifications (7, 3, 1 day before)
     */
    public function notifyLoanDue(Loan $loan, int $daysRemaining, bool $sendEmail = true): void
    {
        $priority = match ($daysRemaining) {
            1 => 'urgent',
            3 => 'high',
            default => 'normal',
        };

        $this->create(
            $loan->user,
            'loan_due',
            'Loan Payment Due Soon',
            'Your loan payment of ₱'.number_format($loan->loan_amount, 2)." is due in {$daysRemaining} day(s).",
            $priority,
            $this->resolveUserDashboardUrl($loan->user),
            $loan
        );

        if ($sendEmail) {
            $this->safeSendEmail(
                $loan->user->email,
                new LoanDueNotification($loan, $daysRemaining, false)
            );
        }
    }

    /**
     * Create loan overdue notification
     */
    public function notifyLoanOverdue(Loan $loan, bool $sendEmail = true): void
    {
        $this->create(
            $loan->user,
            'loan_due',
            'Loan Payment Overdue',
            'Your loan payment is now overdue. Please settle your payment as soon as possible.',
            'urgent',
            $this->resolveUserDashboardUrl($loan->user),
            $loan
        );

        if ($sendEmail) {
            $this->safeSendEmail(
                $loan->user->email,
                new LoanDueNotification($loan, 0, true)
            );
        }
    }

    /**
     * Create loan approved notification
     */
    public function notifyLoanApproved(Loan $loan, bool $sendEmail = true): void
    {
        $this->create(
            $loan->user,
            'loan_approved',
            'Loan Application Approved',
            'Congratulations! Your loan application for ₱'.number_format($loan->loan_amount, 2).' has been approved.',
            'high',
            $this->resolveUserDashboardUrl($loan->user),
            $loan
        );

        if ($sendEmail) {
            $this->safeSendEmail(
                $loan->user->email,
                new LoanStatusNotification($loan, 'approved')
            );
        }
    }

    /**
     * Create loan rejected notification
     */
    public function notifyLoanRejected(Loan $loan, string $reason = '', bool $sendEmail = true): void
    {
        $message = 'Your loan application has been rejected.';
        if ($reason) {
            $message .= ' Reason: '.$reason;
        }

        $this->create(
            $loan->user,
            'loan_rejected',
            'Loan Application Rejected',
            $message,
            'high',
            $this->resolveUserDashboardUrl($loan->user),
            $loan
        );

        if ($sendEmail) {
            $this->safeSendEmail(
                $loan->user->email,
                new LoanStatusNotification($loan, 'rejected', $reason)
            );
        }
    }

    /**
     * Create loan payment received notification
     */
    public function notifyLoanPayment(Loan $loan, float $amount): void
    {
        $this->create(
            $loan->user,
            'loan_payment',
            'Payment Received',
            'Your payment of ₱'.number_format($amount, 2).' has been received.',
            'normal',
            $this->resolveUserDashboardUrl($loan->user),
            $loan
        );
    }

    /**
     * Create withdrawal approved notification
     */
    public function notifyWithdrawalApproved(Transaction $withdrawal, bool $sendEmail = true): void
    {
        $this->create(
            $withdrawal->user,
            'general',
            'Withdrawal Request Approved',
            'Your withdrawal request for ₱'.number_format($withdrawal->amount, 2).' has been approved and processed.',
            'high',
            $this->resolveUserDashboardUrl($withdrawal->user),
            $withdrawal
        );

        if ($sendEmail) {
            $this->safeSendEmail(
                $withdrawal->user->email,
                new WithdrawalStatusNotification($withdrawal, 'approved')
            );
        }
    }

    /**
     * Create withdrawal rejected notification
     */
    public function notifyWithdrawalRejected(Transaction $withdrawal, string $reason = '', bool $sendEmail = true): void
    {
        $message = 'Your withdrawal request for ₱'.number_format($withdrawal->amount, 2).' has been rejected.';
        if ($reason) {
            $message .= ' Reason: '.$reason;
        }

        $this->create(
            $withdrawal->user,
            'general',
            'Withdrawal Request Rejected',
            $message,
            'high',
            $this->resolveUserDashboardUrl($withdrawal->user),
            $withdrawal
        );

        if ($sendEmail) {
            $this->safeSendEmail(
                $withdrawal->user->email,
                new WithdrawalStatusNotification($withdrawal, 'rejected', $reason)
            );
        }
    }

    /**
     * Create an in-app activity notification for account actions.
     */
    public function notifyAccountActivity(User $user, string $title, string $message, string $priority = 'normal'): void
    {
        $this->create($user, 'general', $title, $message, $priority, '/notifications');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Loan review lifecycle notifications
    // ─────────────────────────────────────────────────────────────────────────

    public function notifyLoanUnderReview(Loan $loan): void
    {
        $this->create(
            $loan->user,
            'general',
            'Loan Application Under Review',
            "Your loan application {$loan->loan_number} is now being reviewed by our loan officer.",
            'normal',
            $this->resolveUserDashboardUrl($loan->user),
            $loan
        );
    }

    public function notifyLoanEndorsedForApproval(Loan $loan): void
    {
        $this->create(
            $loan->user,
            'general',
            'Loan Application Endorsed',
            "Your loan application {$loan->loan_number} has been endorsed and is awaiting final approval.",
            'high',
            $this->resolveUserDashboardUrl($loan->user),
            $loan
        );
    }

    public function notifyLoanReviewRejected(Loan $loan, string $reason = '', bool $sendEmail = true): void
    {
        $message = "Your loan application {$loan->loan_number} has been rejected during review.";
        if ($reason) {
            $message .= ' Reason: '.$reason;
        }

        $this->create(
            $loan->user,
            'loan_rejected',
            'Loan Application Rejected',
            $message,
            'high',
            $this->resolveUserDashboardUrl($loan->user),
            $loan
        );

        if ($sendEmail) {
            $this->safeSendEmail(
                $loan->user?->email,
                new LoanStatusNotification($loan, 'rejected', $reason)
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Security alert notifications
    // ─────────────────────────────────────────────────────────────────────────

    public function notifyNewLogin(User $user, string $ipAddress, bool $sendEmail = true): void
    {
        $this->create(
            $user,
            'security_alert',
            'New Sign-in Detected',
            "A new login to your account was detected from IP: {$ipAddress}.",
            'high',
            '/notifications'
        );

        if ($sendEmail) {
            try {
                SendSecurityAlertJob::dispatch($user->id, 'new_login', ['ip_address' => $ipAddress]);
            } catch (Throwable $e) {
                Log::warning("Failed to dispatch security alert job: {$e->getMessage()}");
            }
        }
    }

    public function notifyFailedLogins(User $user, int $attempts, string $ipAddress, bool $sendEmail = true): void
    {
        $this->create(
            $user,
            'security_alert',
            'Multiple Failed Login Attempts',
            "We detected {$attempts} failed login attempts from IP: {$ipAddress}. If this wasn't you, please change your password immediately.",
            'urgent',
            '/notifications'
        );

        if ($sendEmail) {
            try {
                SendSecurityAlertJob::dispatch($user->id, 'failed_login', [
                    'attempts' => $attempts,
                    'ip_address' => $ipAddress,
                ]);
            } catch (Throwable $e) {
                Log::warning("Failed to dispatch security alert job: {$e->getMessage()}");
            }
        }
    }

    public function notifyPasswordChanged(User $user, bool $sendEmail = true): void
    {
        $this->create(
            $user,
            'security_alert',
            'Password Changed',
            "Your password was recently changed. If you didn't make this change, please contact support immediately.",
            'high',
            '/notifications'
        );

        if ($sendEmail) {
            try {
                SendSecurityAlertJob::dispatch($user->id, 'password_changed');
            } catch (Throwable $e) {
                Log::warning("Failed to dispatch security alert job: {$e->getMessage()}");
            }
        }
    }

    public function notifyEmailChanged(User $user, string $newEmail, bool $sendEmail = true): void
    {
        $this->create(
            $user,
            'security_alert',
            'Email Address Changed',
            "Your email address was changed to {$newEmail}. If you didn't make this change, please contact support immediately.",
            'high',
            '/notifications'
        );

        if ($sendEmail) {
            try {
                SendSecurityAlertJob::dispatch($user->id, 'email_changed', ['new_email' => $newEmail]);
            } catch (Throwable $e) {
                Log::warning("Failed to dispatch security alert job: {$e->getMessage()}");
            }
        }
    }

    public function notifyEmailVerification(User $user): void
    {
        $this->create(
            $user,
            'email_verification',
            'Verify Your Email',
            'Please verify your email address to access all features.',
            'high',
            $this->routeIfExists('verification.notice', '/notifications')
        );
    }

    /**
     * Record an in-app "verify your email" notification and email the signed
     * verification link to the user.
     */
    public function sendEmailVerification(User $user): void
    {
        if (! $user->email) {
            return;
        }

        $this->notifyEmailVerification($user);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1((string) $user->email)]
        );

        $this->safeSendEmail(
            $user->email,
            new EmailVerificationMail($user, $verificationUrl),
            'email.verification'
        );
    }

    /**
     * Delete old read notifications (older than 30 days)
     */
    public function cleanupOldNotifications(): int
    {
        return Notification::where('is_read', true)
            ->where('read_at', '<', now()->subDays(30))
            ->delete();
    }

    /**
     * Delete expired notifications
     */
    public function cleanupExpiredNotifications(): int
    {
        return Notification::where('expires_at', '<', now())
            ->delete();
    }
}
