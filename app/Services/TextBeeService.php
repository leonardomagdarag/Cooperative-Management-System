<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TextBeeService
{
    private function apiKey(): string
    {
        return (string) SystemSetting::get('textbee_api_key', '');
    }

    private function deviceId(): ?string
    {
        $id = (string) SystemSetting::get('textbee_device_id', '');
        return $id !== '' ? $id : null;
    }

    /**
     * Whether the service has a key configured AND is enabled by the admin.
     */
    public function isEnabled(): bool
    {
        return $this->apiKey() !== ''
            && (bool) SystemSetting::get('sms_service_enabled', false);
    }

    /**
     * Normalize a Philippine mobile number to E.164.
     * 09XXXXXXXXX → +639XXXXXXXXX
     * +639XXXXXXXXX → unchanged
     */
    public function normalizePhone(string $phone): string
    {
        $stripped = preg_replace('/\D/', '', $phone);
        if (str_starts_with($stripped, '0')) {
            $stripped = '63' . substr($stripped, 1);
        } elseif (str_starts_with($stripped, '9') && strlen($stripped) === 10) {
            $stripped = '63' . $stripped;
        }
        return '+' . $stripped;
    }

    /**
     * Send a single SMS. Returns true on success, false on failure/skip.
     */
    public function send(string $phone, string $message): bool
    {
        if (! $this->isEnabled()) {
            Log::info('TextBee: SMS skipped — service disabled or unconfigured.');
            return false;
        }

        $normalized = $this->normalizePhone($phone);
        $payload = [
            'message' => $message,
            'recipients' => [$normalized],
        ];

        if ($this->deviceId()) {
            $payload['deviceId'] = $this->deviceId();
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey(),
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://api.textbee.dev/api/v1/gateway/send-sms', $payload);

            if ($response->successful()) {
                Log::info("TextBee: SMS sent to {$normalized}.");
                return true;
            }

            Log::warning("TextBee: HTTP {$response->status()} for {$normalized} — {$response->body()}");
            return false;
        } catch (\Throwable $e) {
            Log::error("TextBee: Exception for {$normalized}: {$e->getMessage()}");
            return false;
        }
    }
}
