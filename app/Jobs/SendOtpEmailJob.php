<?php

namespace App\Jobs;

use App\Mail\OtpCodeMail;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOtpEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public int $userId,
        public string $purpose,
        public string $otpCode,
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
            Mail::to($user->email)->send(new OtpCodeMail($user, $this->purpose, $this->otpCode));
        } catch (Throwable $e) {
            app(AuditLogService::class)->log(
                'email.failed',
                "OTP email failed for user {$this->userId}: {$e->getMessage()}",
                'email',
                null,
                null,
                ['user_id' => $this->userId, 'purpose' => $this->purpose]
            );

            throw $e;
        }
    }
}
