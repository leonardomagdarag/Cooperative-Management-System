<?php

namespace App\Services;

use App\Mail\AnnouncementNotification as AnnouncementMail;
use App\Mail\LoanDueNotification;
use App\Mail\LoanStatusNotification;
use App\Mail\SecurityAlert;
use App\Mail\WithdrawalStatusNotification;
use App\Models\Notification;
use App\Models\User;
use App\Models\Announcement;
use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

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

        // 'public' means website visitors — no registered users to notify.
        // Build the target user set based on the remaining visibility values.
        if (in_array('everyone', $visibility, true)) {
            $users = User::all();
        } else {
            $userIds = collect();

            if (in_array('members', $visibility, true)) {
                $userIds = $userIds->merge(
                    User::role('member')->pluck('id')
                );
            }

            if (in_array('officers', $visibility, true)) {
                // Officers = all staff roles (everyone except members / inactive / terminated).
                $officerRoles = ['admin', 'manager', 'secretary', 'accountant', 'loan', 'developer'];
                $userIds = $userIds->merge(
                    User::role($officerRoles)->pluck('id')
                );
            }

            // 'public' only → no registered users; skip notifications.
            if ($userIds->isEmpty()) {
                return;
            }

            $users = User::whereIn('id', $userIds->unique())->get();
        }

        $this->createForUsers(
            $users,
            'announcement',
            'New Announcement: ' . $announcement->title,
            substr($announcement->content, 0, 100) . '...',
            'normal',
            '/news',
            $announcement
        );

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log') {
            foreach ($users as $user) {
                if ($user->email) {
                    Mail::to($user->email)->queue(new AnnouncementMail($announcement));
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
            "Your loan payment of ₱" . number_format($loan->loan_amount, 2) . " is due in {$daysRemaining} day(s).",
            $priority,
            $this->resolveUserDashboardUrl($loan->user),
            $loan
        );

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $loan->user->email) {
            Mail::to($loan->user->email)->queue(new LoanDueNotification($loan, $daysRemaining, false));
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
            "Your loan payment is now overdue. Please settle your payment as soon as possible.",
            'urgent',
            $this->resolveUserDashboardUrl($loan->user),
            $loan
        );

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $loan->user->email) {
            Mail::to($loan->user->email)->queue(new LoanDueNotification($loan, 0, true));
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
            "Congratulations! Your loan application for ₱" . number_format($loan->loan_amount, 2) . " has been approved.",
            'high',
            $this->resolveUserDashboardUrl($loan->user),
            $loan
        );

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $loan->user->email) {
            Mail::to($loan->user->email)->queue(new LoanStatusNotification($loan, 'approved'));
        }
    }

    /**
     * Create loan rejected notification
     */
    public function notifyLoanRejected(Loan $loan, string $reason = '', bool $sendEmail = true): void
    {
        $message = "Your loan application has been rejected.";
        if ($reason) {
            $message .= " Reason: " . $reason;
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

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $loan->user->email) {
            Mail::to($loan->user->email)->queue(new LoanStatusNotification($loan, 'rejected', $reason));
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
            "Your payment of ₱" . number_format($amount, 2) . " has been received.",
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
            "Your withdrawal request for ₱" . number_format($withdrawal->amount, 2) . " has been approved and processed.",
            'high',
            $this->resolveUserDashboardUrl($withdrawal->user),
            $withdrawal
        );

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $withdrawal->user->email) {
            Mail::to($withdrawal->user->email)->queue(new WithdrawalStatusNotification($withdrawal, 'approved'));
        }
    }

    /**
     * Create withdrawal rejected notification
     */
    public function notifyWithdrawalRejected(Transaction $withdrawal, string $reason = '', bool $sendEmail = true): void
    {
        $message = "Your withdrawal request for ₱" . number_format($withdrawal->amount, 2) . " has been rejected.";
        if ($reason) {
            $message .= " Reason: " . $reason;
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

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $withdrawal->user->email) {
            Mail::to($withdrawal->user->email)->queue(new WithdrawalStatusNotification($withdrawal, 'rejected', $reason));
        }
    }

    /**
     * Create an in-app activity notification for account actions.
     */
    public function notifyAccountActivity(
        User $user,
        string $title,
        string $message,
        string $priority = 'normal'
    ): void {
        $this->create(
            $user,
            'general',
            $title,
            $message,
            $priority,
            '/notifications'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Loan review lifecycle notifications (officer review stage)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Notify member that their loan application is now being reviewed by an officer.
     */
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

    /**
     * Notify member that their loan application has been endorsed for final approval.
     */
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

    /**
     * Notify member that their loan application was rejected during officer review.
     */
    public function notifyLoanReviewRejected(Loan $loan, string $reason = '', bool $sendEmail = true): void
    {
        $message = "Your loan application {$loan->loan_number} has been rejected during review.";
        if ($reason) {
            $message .= ' Reason: ' . $reason;
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

        if ($sendEmail && config('mail.default') !== 'log' && $loan->user?->email) {
            Mail::to($loan->user->email)->queue(new LoanStatusNotification($loan, 'rejected', $reason));
        }
    }

    /**
     * Create security alert for a new sign-in from an unfamiliar IP.
     */
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

        if ($sendEmail && config('mail.default') !== 'log' && $user->email) {
            Mail::to($user->email)->queue(new SecurityAlert($user, 'new_login', [
                'ip_address' => $ipAddress,
            ]));
        }
    }

    /**
     * Create security alert for failed login attempts
     */
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

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $user->email) {
            Mail::to($user->email)->queue(new SecurityAlert($user, 'failed_login', [
                'attempts' => $attempts,
                'ip_address' => $ipAddress,
            ]));
        }
    }

    /**
     * Create security alert for password change
     */
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

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $user->email) {
            Mail::to($user->email)->queue(new SecurityAlert($user, 'password_changed'));
        }
    }

    /**
     * Create security alert for email change
     */
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

        // Send email notification (to old email for security)
        if ($sendEmail && config('mail.default') !== 'log' && $user->email) {
            Mail::to($user->email)->queue(new SecurityAlert($user, 'email_changed', [
                'new_email' => $newEmail,
            ]));
        }
    }

    /**
     * Create email verification notification
     */
    public function notifyEmailVerification(User $user): void
    {
        $this->create(
            $user,
            'email_verification',
            'Verify Your Email',
            "Please verify your email address to access all features.",
            'high',
            $this->routeIfExists('verification.notice', '/notifications')
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
