<?php

namespace App\Jobs;

use App\Mail\SecurityAlert;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendSecurityAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $userId,
        public string $alertType,
        public array $data = [],
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user || ! $user->email || config('mail.default') === 'log') {
            return;
        }

        try {
            Mail::to($user->email)->send(new SecurityAlert($user, $this->alertType, $this->data));
        } catch (Throwable $e) {
            app(AuditLogService::class)->log(
                'email.failed',
                "Security alert email failed for user {$this->userId}: {$e->getMessage()}",
                'email',
                null,
                null,
                ['user_id' => $this->userId, 'alert_type' => $this->alertType]
            );

            throw $e;
        }
    }
}
