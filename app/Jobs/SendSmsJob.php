<?php

namespace App\Jobs;

use App\Services\TextBeeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public string $phone,
        public string $message,
        public string $tag = 'sms',
    ) {
        $this->onQueue('default');
    }

    public function handle(TextBeeService $sms): void
    {
        if (! $sms->send($this->phone, $this->message)) {
            Log::warning("SendSmsJob failed ({$this->tag}): {$this->phone}");
            throw new RuntimeException('SMS delivery returned false.');
        }
    }
}
