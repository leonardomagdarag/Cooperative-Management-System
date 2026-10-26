<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AppCredential;
use App\Models\Loan;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\XenditWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class XenditService
{
    private const CREDENTIALS = [
        'test_secret_key' => 'xendit_test_secret_key',
        'test_webhook_token' => 'xendit_test_webhook_token',
        'live_secret_key' => 'xendit_live_secret_key',
        'live_webhook_token' => 'xendit_live_webhook_token',
    ];

    private function credential(string $name): ?string
    {
        try {
            $stored = AppCredential::query()->where('key', self::CREDENTIALS[$name])->value('value');
        } catch (Throwable) {
            $stored = null;
        }

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        $value = config("xendit.{$name}");

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function mode(): string
    {
        $mode = (string) SystemSetting::get('xendit_mode', config('xendit.mode', 'test'));

        return in_array($mode, ['test', 'live'], true) ? $mode : 'test';
    }

    public function isEnabled(): bool
    {
        return (bool) SystemSetting::get('xendit_enabled', false);
    }

    public function activeSecretKey(?string $mode = null): ?string
    {
        $mode ??= $this->mode();

        return $this->credential($mode === 'live' ? 'live_secret_key' : 'test_secret_key');
    }

    public function activeWebhookToken(?string $mode = null): ?string
    {
        $mode ??= $this->mode();

        return $this->credential($mode === 'live' ? 'live_webhook_token' : 'test_webhook_token');
    }

    public function isConfigured(): bool
    {
        return filled($this->activeSecretKey()) && filled($this->activeWebhookToken());
    }

    public function isLiveConfigured(): bool
    {
        return filled($this->credential('live_secret_key')) && filled($this->credential('live_webhook_token'));
    }

    public function storeCredentials(array $values): void
    {
        foreach (self::CREDENTIALS as $name => $key) {
            $value = trim((string) ($values[$name] ?? ''));
            if ($value === '') {
                continue;
            }

            AppCredential::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'label' => 'Xendit '.str($name)->replace('_', ' ')->title(),
                    'description' => 'Xendit payment gateway credential',
                ]
            );
        }
    }

    public function clearCredentials(): void
    {
        AppCredential::query()->whereIn('key', array_values(self::CREDENTIALS))->delete();
    }

    public function summary(): array
    {
        $mode = $this->mode();
        $keys = [];
        foreach (array_keys(self::CREDENTIALS) as $name) {
            $keys[$name] = filled($this->credential($name));
        }

        return [
            'enabled' => $this->isEnabled(),
            'mode' => $mode,
            'configured' => $this->isConfigured(),
            'live_configured' => $this->isLiveConfigured(),
            'stored' => collect(self::CREDENTIALS)->mapWithKeys(function (string $key, string $name) {
                try {
                    return [$name => AppCredential::query()->where('key', $key)->exists()];
                } catch (Throwable) {
                    return [$name => false];
                }
            })->all(),
            'configured_keys' => $keys,
        ];
    }

    private function request(?string $mode = null)
    {
        $mode ??= $this->mode();
        $secretKey = $this->activeSecretKey($mode);
        if (! $secretKey) {
            throw new RuntimeException('No Xendit secret API key is configured for the active mode.');
        }

        return Http::withBasicAuth($secretKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('xendit.base_url', 'https://api.xendit.co'), '/');
    }

    public function createSession(array $params): array
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Xendit payments are currently disabled.');
        }

        $payload = [
            'reference_id' => $params['reference_id'],
            'session_type' => 'PAY',
            'mode' => 'PAYMENT_LINK',
            'amount' => round((float) $params['amount'], 2),
            'currency' => 'PHP',
            'country' => 'PH',
            'capture_method' => 'AUTOMATIC',
            'description' => $params['description'],
            'metadata' => $params['metadata'],
        ];
        foreach (['success_return_url', 'cancel_return_url'] as $returnField) {
            if (! empty($params[$returnField])) {
                $payload[$returnField] = $params[$returnField];
            }
        }

        $response = $this->request()->post($this->baseUrl().'/sessions', $payload);
        if (! $response->successful()) {
            Log::error('Xendit payment session creation failed', [
                'status' => $response->status(),
                'error_code' => $response->json('error_code'),
            ]);
            throw new RuntimeException($response->json('message') ?? 'Xendit could not create the payment session.');
        }

        return $response->json() ?? [];
    }

    public function getSession(string $sessionId, ?string $mode = null): array
    {
        $response = $this->request($mode)->get($this->baseUrl().'/sessions/'.rawurlencode($sessionId));
        if (! $response->successful()) {
            throw new RuntimeException($response->json('message') ?? 'Unable to retrieve the Xendit payment session.');
        }

        return $response->json() ?? [];
    }

    public function getPayment(string $paymentId, ?string $mode = null): array
    {
        $response = $this->request($mode)
            ->withHeaders(['api-version' => '2024-11-11'])
            ->get($this->baseUrl().'/v3/payments/'.rawurlencode($paymentId));
        if (! $response->successful()) {
            throw new RuntimeException($response->json('message') ?? 'Unable to retrieve the Xendit payment status.');
        }

        return $response->json() ?? [];
    }

    public function testConnection(): array
    {
        $response = $this->request()->get($this->baseUrl().'/sessions/xendit-connection-test-not-found');
        if ($response->status() === 401) {
            throw new RuntimeException('Xendit rejected the active secret API key.');
        }

        if (! in_array($response->status(), [200, 404], true)) {
            throw new RuntimeException("Xendit connection test returned status {$response->status()}.");
        }

        return ['success' => true, 'mode' => $this->mode(), 'message' => 'Connected to Xendit successfully.'];
    }

    public function verifyAndCompletePayment(Transaction $transaction): array
    {
        if ($transaction->status === 'completed') {
            return ['status' => 'completed', 'already_completed' => true];
        }

        $sessionId = $transaction->xendit_session_id;
        if (! $sessionId) {
            throw new RuntimeException('Transaction has no Xendit payment session.');
        }

        $session = $this->getSession($sessionId, $transaction->xendit_mode);
        if (($session['status'] ?? null) !== 'COMPLETED') {
            return ['status' => $transaction->status, 'is_paid' => false, 'message' => 'Xendit has not marked this payment as completed.'];
        }

        $paymentId = $session['payment_id'] ?? null;
        if (! is_string($paymentId) || $paymentId === '') {
            return ['status' => $transaction->status, 'is_paid' => false, 'message' => 'Xendit has not provided a payment ID yet.'];
        }
        $payment = $this->getPayment($paymentId, $transaction->xendit_mode);
        if (($payment['status'] ?? null) !== 'SUCCEEDED') {
            return ['status' => $transaction->status, 'is_paid' => false, 'message' => 'Xendit has not confirmed a successful payment yet.'];
        }

        if ($transaction->status === 'rejected' && filled($transaction->xendit_payment_id)) {
            return [
                'status' => 'rejected',
                'is_paid' => true,
                'approval_required' => false,
                'message' => 'Xendit verified the payment, but the transaction was rejected. Contact the cooperative for resolution.',
            ];
        }

        $verifiedTransaction = $this->completeSessionPayment($sessionId, $session, $payment);

        return [
            'status' => $verifiedTransaction->status,
            'is_paid' => true,
            'approval_required' => $verifiedTransaction->status === 'pending',
            'message' => $verifiedTransaction->status === 'pending'
                ? 'Payment verified and awaiting manager or chairman approval.'
                : 'Payment successfully verified and completed.',
            'transaction' => $verifiedTransaction,
        ];
    }

    public function verifyWebhookToken(?string $provided, string $sessionId): bool
    {
        $mode = Transaction::query()->where('xendit_session_id', $sessionId)->value('xendit_mode') ?: $this->mode();
        $expected = $this->activeWebhookToken($mode);

        return $expected !== null && $provided !== null && hash_equals($expected, $provided);
    }

    public function processWebhook(array $payload): array
    {
        $eventType = (string) ($payload['event'] ?? 'unknown');
        $sessionId = $payload['data']['payment_session_id'] ?? null;
        if (! is_string($sessionId) || $sessionId === '') {
            throw new RuntimeException('Webhook did not include a payment session ID.');
        }

        $eventId = hash('sha256', $eventType.'|'.$sessionId);
        $event = XenditWebhookEvent::query()->firstOrCreate(
            ['event_id' => $eventId],
            ['event_type' => $eventType, 'payload' => $payload]
        );

        if ($event->processed_at !== null) {
            return ['status' => 'already_processed', 'event_id' => $eventId];
        }

        if ($eventType === 'payment_session.completed') {
            // Fetch authoritative state from Xendit; do not credit from the callback alone.
            $mode = Transaction::query()->where('xendit_session_id', $sessionId)->value('xendit_mode') ?: $this->mode();
            $session = $this->getSession($sessionId, $mode);
            if (($session['status'] ?? null) === 'COMPLETED') {
                $paymentId = $session['payment_id'] ?? null;
                if (! is_string($paymentId) || $paymentId === '') {
                    throw new RuntimeException('Xendit completed the session without providing a payment ID.');
                }
                $payment = $this->getPayment($paymentId, $mode);
                if (($payment['status'] ?? null) !== 'SUCCEEDED') {
                    throw new RuntimeException('Xendit has not confirmed the payment as successful yet.');
                }
                $this->completeSessionPayment($sessionId, $session, $payment);
            }
        } elseif (! in_array($eventType, ['payment_session.expired'], true)) {
            Log::info('Ignoring unsupported Xendit webhook event.', ['event' => $eventType]);
        }

        $event->update(['processed_at' => now()]);

        return ['status' => 'processed', 'event_id' => $eventId];
    }

    private function completeSessionPayment(string $sessionId, array $session, array $payment): Transaction
    {
        return DB::transaction(function () use ($sessionId, $session, $payment) {
            $transaction = Transaction::query()->where('xendit_session_id', $sessionId)->lockForUpdate()->first();
            if (! $transaction) {
                throw new RuntimeException('No local transaction is associated with this Xendit payment session.');
            }

            if ($transaction->status === 'completed') {
                return $transaction;
            }

            $sessionAmount = $session['amount'] ?? null;
            $paidAmount = $payment['request_amount'] ?? null;
            if (($payment['payment_id'] ?? null) !== ($session['payment_id'] ?? null)
                || ! is_numeric($sessionAmount) || ! is_numeric($paidAmount)
                || abs((float) $sessionAmount - (float) $transaction->amount) > 0.01
                || abs((float) $paidAmount - (float) $transaction->amount) > 0.01
                || strtoupper((string) ($payment['currency'] ?? $session['currency'] ?? '')) !== 'PHP'
                || ($session['reference_id'] ?? null) !== $transaction->transaction_number
                || ($payment['reference_id'] ?? null) !== $transaction->transaction_number) {
                Log::warning('Xendit payment session did not match its local transaction.', [
                    'transaction_id' => $transaction->id,
                    'session_id' => $sessionId,
                    'expected_amount' => (float) $transaction->amount,
                    'actual_amount' => $sessionAmount,
                    'actual_payment_amount' => $paidAmount,
                    'currency' => $payment['currency'] ?? $session['currency'] ?? null,
                    'reference_id' => $session['reference_id'] ?? null,
                ]);
                throw new RuntimeException('Xendit payment details did not match the transaction.');
            }

            $update = [
                'xendit_payment_id' => $payment['payment_id'],
                'payment_method_used' => $payment['channel_code'] ?? $transaction->payment_method_used,
            ];
            if ($transaction->status === 'rejected') {
                // A completed provider payment cannot be left rejected and unposted.
                $update['status'] = 'pending';
                $update['rejection_reason'] = null;
            }
            $transaction->update($update);

            $account = Account::query()->whereKey($transaction->account_id)->lockForUpdate()->first();

            try {
                $user = $account?->user ?? User::find($transaction->recorded_by);
                app(AuditLogService::class)->log(
                    'payment.xendit.verified',
                    "Xendit payment verified for transaction {$transaction->transaction_number}; approval is pending.",
                    'transaction',
                    $transaction,
                    $user
                );

                if ($user) {
                    app(NotificationService::class)->notifyTransactionStatus(
                        $user,
                        $transaction,
                        'pending',
                        'Your payment of ₱'.number_format((float) $transaction->amount, 2).' was verified; manager or chairman approval is still required.'
                    );
                }
            } catch (Throwable $exception) {
                Log::warning('Xendit post-completion notification or audit failed: '.$exception->getMessage());
            }

            return $transaction;
        });
    }
}
