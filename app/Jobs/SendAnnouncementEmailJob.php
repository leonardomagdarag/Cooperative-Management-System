<?php

namespace App\Jobs;

use App\Mail\AnnouncementNotification as AnnouncementMail;
use App\Models\Announcement;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAnnouncementEmailJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public Announcement $announcement,
        public int $userId,
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $user = User::find($this->userId);

        if (! $user || ! $user->email || config('mail.default') === 'log') {
            return;
        }

        try {
            Mail::to($user->email)->send(new AnnouncementMail($this->announcement));
        } catch (Throwable $e) {
            app(AuditLogService::class)->log(
                'email.failed',
                "Announcement email failed for user {$this->userId}: {$e->getMessage()}",
                'email',
                null,
                null,
                ['user_id' => $this->userId, 'announcement_id' => $this->announcement->id]
            );

            throw $e;
        }
    }
}
