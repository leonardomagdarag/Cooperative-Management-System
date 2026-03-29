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

class NotificationService
{
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
        $users = User::all();

        $this->createForUsers(
            $users,
            'announcement',
            'New Announcement: ' . $announcement->title,
            substr($announcement->content, 0, 100) . '...',
            'normal',
            route('announcements.show', $announcement->id),
            $announcement
        );

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log') {
            foreach ($users as $user) {
                if ($user->email) {
                    Mail::to($user->email)->send(new AnnouncementMail($announcement));
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
            route('loans.show', $loan->id),
            $loan
        );

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $loan->user->email) {
            Mail::to($loan->user->email)->send(new LoanDueNotification($loan, $daysRemaining, false));
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
            route('loans.show', $loan->id),
            $loan
        );

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $loan->user->email) {
            Mail::to($loan->user->email)->send(new LoanDueNotification($loan, 0, true));
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
            route('loans.show', $loan->id),
            $loan
        );

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $loan->user->email) {
            Mail::to($loan->user->email)->send(new LoanStatusNotification($loan, 'approved'));
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
            route('loans.show', $loan->id),
            $loan
        );

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $loan->user->email) {
            Mail::to($loan->user->email)->send(new LoanStatusNotification($loan, 'rejected', $reason));
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
            route('loans.show', $loan->id),
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
            'withdrawal_approved',
            'Withdrawal Request Approved',
            "Your withdrawal request for ₱" . number_format($withdrawal->amount, 2) . " has been approved and processed.",
            'high',
            route('member.history'),
            $withdrawal
        );

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $withdrawal->user->email) {
            Mail::to($withdrawal->user->email)->send(new WithdrawalStatusNotification($withdrawal, 'approved'));
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
            'withdrawal_rejected',
            'Withdrawal Request Rejected',
            $message,
            'high',
            route('member.services'),
            $withdrawal
        );

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $withdrawal->user->email) {
            Mail::to($withdrawal->user->email)->send(new WithdrawalStatusNotification($withdrawal, 'rejected', $reason));
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
            route('profile.security')
        );

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $user->email) {
            Mail::to($user->email)->send(new SecurityAlert($user, 'failed_login', [
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
            route('profile.security')
        );

        // Send email notification
        if ($sendEmail && config('mail.default') !== 'log' && $user->email) {
            Mail::to($user->email)->send(new SecurityAlert($user, 'password_changed'));
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
            route('profile.security')
        );

        // Send email notification (to old email for security)
        if ($sendEmail && config('mail.default') !== 'log' && $user->email) {
            Mail::to($user->email)->send(new SecurityAlert($user, 'email_changed', [
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
            route('verification.notice')
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
