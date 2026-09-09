<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TurnstileService
{
    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (! $token) {
            return false;
        }

        $secret = config('services.turnstile.secret_key');

        if (! $secret) {
            return false;
        }

        $caBundle = config('services.turnstile.ca_bundle');
        $verify = match (true) {
            $caBundle === null => true,
            in_array(strtolower((string) $caBundle), ['false', '0', ''], true) => false,
            default => $caBundle,
        };

        $response = Http::withOptions(['verify' => $verify])->asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $remoteIp,
        ]);

        if (! $response->ok()) {
            return false;
        }

        return (bool) data_get($response->json(), 'success', false);
    }
}
