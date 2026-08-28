<?php

namespace App\Jobs;

use App\Services\AuditLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public string $toEmail,
        public Mailable $mailable,
        public string $tag = 'email.sent',
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        try {
            Mail::to($this->toEmail)->send($this->mailable);
        } catch (Throwable $e) {
            Log::error("Failed to send email to {$this->toEmail} (attempt {$this->attempts()}): {$e->getMessage()}");

            if ($this->attempts() >= $this->tries) {
                app(AuditLogService::class)->log(
                    'email.failed',
                    "Failed to send email to {$this->toEmail}: {$e->getMessage()}",
                    'email',
                    null,
                    null,
                    [
                        'to' => $this->toEmail,
                        'mailable' => get_class($this->mailable),
                        'error' => $e->getMessage(),
                    ]
                );
            }

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(AuditLogService::class)->log(
            'email.failed',
            "Failed to send email to {$this->toEmail}: ".($exception?->getMessage() ?? 'Unknown error'),
            'email',
            null,
            null,
            [
                'to' => $this->toEmail,
                'mailable' => get_class($this->mailable),
                'error' => $exception?->getMessage(),
            ]
        );
    }
}
