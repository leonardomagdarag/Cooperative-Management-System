<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Mail\BackupAlertMail;
use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BackupNotificationService
{
    public function administratorEmails(): Collection
    {
        return User::query()
            ->where('account_status', 'active')
            ->whereNotNull('email')
            ->where(function ($query) {
                $query->where('role', 'admin')
                    ->orWhereHas('roles', fn ($roles) => $roles->where('name', 'admin'));
            })
            ->pluck('email')
            ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();
    }

    public function send(string $title, string $message, array $details = []): void
    {
        foreach ($this->administratorEmails() as $email) {
            SendEmailJob::dispatch($email, new BackupAlertMail($title, $message, $details), 'backup.notification');
        }
    }

    public function notifyFailure(BackupRun $run): void
    {
        $this->send(
            'Backup failed',
            'An automated backup failed and requires administrator attention.',
            [
                'tier' => $run->tier,
                'scope' => $run->scope,
                'run_id' => (string) $run->id,
                'error' => (string) ($run->message ?: 'No error message was recorded.'),
            ],
        );
    }

    /** Send restore lifecycle notices synchronously using recipients captured before the restore. */
    public function notifyRestoreEvent(array $emails, string $title, string $message, array $details = []): void
    {
        foreach (array_unique($emails) as $email) {
            if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                Mail::to($email)->send(new BackupAlertMail($title, $message, $details));
            } catch (Throwable $e) {
                Log::error('Restore status email could not be sent.', [
                    'recipient' => $email,
                    'title' => $title,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
