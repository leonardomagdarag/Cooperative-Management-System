<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AppCredential;
use App\Models\Loan;
use App\Models\PaymongoWebhookEvent;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PayMongoService
{
    public const KEY_TEST_PUBLIC_KEY = 'paymongo_test_public_key';

    public const KEY_TEST_SECRET_KEY = 'paymongo_test_secret_key';

    public const KEY_TEST_WEBHOOK_SECRET = 'paymongo_test_webhook_secret';

    public const KEY_LIVE_PUBLIC_KEY = 'paymongo_live_public_key';

    public const KEY_LIVE_SECRET_KEY = 'paymongo_live_secret_key';

    public const KEY_LIVE_WEBHOOK_SECRET = 'paymongo_live_webhook_secret';

    private const VALID_CREDENTIAL_KEYS = [
        'test_public_key' => self::KEY_TEST_PUBLIC_KEY,
        'test_secret_key' => self::KEY_TEST_SECRET_KEY,
        'test_webhook_secret' => self::KEY_TEST_WEBHOOK_SECRET,
        'live_public_key' => self::KEY_LIVE_PUBLIC_KEY,
        'live_secret_key' => self::KEY_LIVE_SECRET_KEY,
        'live_webhook_secret' => self::KEY_LIVE_WEBHOOK_SECRET,
    ];

    /**
     * Stored encrypted value from app_credentials table.
     */
    private function stored(string $key): ?string
    {
        try {
            $credential = AppCredential::query()->where('key', $key)->first();

            return $credential?->value !== null && $credential->value !== ''
                ? (string) $credential->value
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolve credential: database (encrypted at rest) wins over config/.env fallback.
     */
    public function getCredential(string $key, ?string $configFallback = null): ?string
    {
        $stored = $this->stored($key);

        if ($stored !== null && $stored !== '') {
            return $stored;
        }

        if (is_string($configFallback) && $configFallback !== '') {
            return $configFallback;
        }

        return null;
    }

    public function isEnabled(): bool
    {
        return (bool) SystemSetting::get('paymongo_enabled', false);
    }

    public function getMode(): string
    {
        $mode = (string) SystemSetting::get('paymongo_mode', config('paymongo.mode', 'sandbox'));

        return in_array($mode, ['sandbox', 'live'], true) ? $mode : 'sandbox';
    }

    public function isLiveMode(): bool
    {
        return $this->getMode() === 'live';
    }

    public function getActivePublicKey(): ?string
    {
        return $this->isLiveMode()
            ? $this->getCredential(self::KEY_LIVE_PUBLIC_KEY, config('paymongo.live_public_key'))
            : $this->getCredential(self::KEY_TEST_PUBLIC_KEY, config('paymongo.test_public_key'));
    }

    public function getActiveSecretKey(): ?string
    {
        return $this->isLiveMode()
            ? $this->getCredential(self::KEY_LIVE_SECRET_KEY, config('paymongo.live_secret_key'))
            : $this->getCredential(self::KEY_TEST_SECRET_KEY, config('paymongo.test_secret_key'));
    }

    public function getActiveWebhookSecret(): ?string
    {
        return $this->isLiveMode()
            ? $this->getCredential(self::KEY_LIVE_WEBHOOK_SECRET, config('paymongo.live_webhook_secret'))
            : $this->getCredential(self::KEY_TEST_WEBHOOK_SECRET, config('paymongo.test_webhook_secret'));
    }

    /**
     * Check if PayMongo has all required keys for the active mode.
     */
    public function isConfigured(): bool
    {
        $secretKey = $this->getActiveSecretKey();
        $publicKey = $this->getActivePublicKey();

        return ! empty($secretKey) && ! empty($publicKey);
    }

    /**
     * Check if live credentials are fully configured.
     */
    public function isLiveConfigured(): bool
    {
        $liveSecret = $this->getCredential(self::KEY_LIVE_SECRET_KEY, config('paymongo.live_secret_key'));
        $livePublic = $this->getCredential(self::KEY_LIVE_PUBLIC_KEY, config('paymongo.live_public_key'));

        return ! empty($liveSecret) && ! empty($livePublic);
    }

    /**
     * Save credentials securely to database (encrypted at rest).
     * Blank values are left untouched so secrets do not need to be re-entered.
     */
    public function storeCredentials(array $values): void
    {
        foreach (self::VALID_CREDENTIAL_KEYS as $inputKey => $credentialKey) {
            $value = $values[$inputKey] ?? null;

            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            AppCredential::query()->updateOrCreate(
                ['key' => $credentialKey],
                [
                    'value' => trim((string) $value),
                    'label' => $this->labelFor($credentialKey),
                    'description' => 'PayMongo gateway credential',
                ]
            );
        }
    }

    /**
     * Clear all database-backed PayMongo credentials.
     */
    public function clearCredentials(): void
    {
        AppCredential::query()->whereIn('key', array_values(self::VALID_CREDENTIAL_KEYS))->delete();
    }

    private function labelFor(string $key): string
    {
        return match ($key) {
            self::KEY_TEST_PUBLIC_KEY => 'PayMongo Test Public Key',
            self::KEY_TEST_SECRET_KEY => 'PayMongo Test Secret Key',
            self::KEY_TEST_WEBHOOK_SECRET => 'PayMongo Test Webhook Secret',
            self::KEY_LIVE_PUBLIC_KEY => 'PayMongo Live Public Key',
            self::KEY_LIVE_SECRET_KEY => 'PayMongo Live Secret Key',
            self::KEY_LIVE_WEBHOOK_SECRET => 'PayMongo Live Webhook Secret',
            default => 'PayMongo Credential',
        };
    }

    /**
     * Enabled payment methods configured by administrator.
     */
    public function getEnabledMethods(): array
    {
        $allSupported = array_keys(config('paymongo.supported_methods', []));
        $saved = SystemSetting::get('paymongo_methods', $allSupported);

        if (! is_array($saved) || empty($saved)) {
            return $allSupported;
        }

        return array_values(array_intersect($saved, $allSupported));
    }

    /**
     * Non-secret overview for UI and API responses.
     * Secret values are NEVER returned.
     */
    public function summary(): array
    {
        $mode = $this->getMode();
        $isConfigured = $this->isConfigured();

        return [
            'enabled' => $this->isEnabled(),
            'mode' => $mode,
            'is_live' => $mode === 'live',
            'configured' => $isConfigured,
            'live_configured' => $this->isLiveConfigured(),
            'pass_on_fees' => (bool) SystemSetting::get('paymongo_pass_on_fees', false),
            'send_email_receipt' => (bool) SystemSetting::get('paymongo_send_email_receipt', true),
            'stored' => [
                'test_public_key' => $this->stored(self::KEY_TEST_PUBLIC_KEY) !== null,
                'test_secret_key' => $this->stored(self::KEY_TEST_SECRET_KEY) !== null,
                'test_webhook_secret' => $this->stored(self::KEY_TEST_WEBHOOK_SECRET) !== null,
                'live_public_key' => $this->stored(self::KEY_LIVE_PUBLIC_KEY) !== null,
                'live_secret_key' => $this->stored(self::KEY_LIVE_SECRET_KEY) !== null,
                'live_webhook_secret' => $this->stored(self::KEY_LIVE_WEBHOOK_SECRET) !== null,
            ],
            'configured_keys' => [
                'test_public_key' => ! empty($this->getCredential(self::KEY_TEST_PUBLIC_KEY, config('paymongo.test_public_key'))),
                'test_secret_key' => ! empty($this->getCredential(self::KEY_TEST_SECRET_KEY, config('paymongo.test_secret_key'))),
                'test_webhook_secret' => ! empty($this->getCredential(self::KEY_TEST_WEBHOOK_SECRET, config('paymongo.test_webhook_secret'))),
                'live_public_key' => ! empty($this->getCredential(self::KEY_LIVE_PUBLIC_KEY, config('paymongo.live_public_key'))),
                'live_secret_key' => ! empty($this->getCredential(self::KEY_LIVE_SECRET_KEY, config('paymongo.live_secret_key'))),
                'live_webhook_secret' => ! empty($this->getCredential(self::KEY_LIVE_WEBHOOK_SECRET, config('paymongo.live_webhook_secret'))),
            ],
            'enabled_methods' => $this->getEnabledMethods(),
            'supported_methods' => config('paymongo.supported_methods', []),
        ];
    }

    /**
     * Create a PayMongo Checkout Session (v2).
     */
    public function createCheckoutSession(array $params): array
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('PayMongo payment integration is currently disabled.');
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException('PayMongo is not properly configured with required credentials.');
        }

        $secretKey = $this->getActiveSecretKey();
        if (empty($secretKey)) {
            throw new RuntimeException('Missing active PayMongo secret key.');
        }

        if ($this->isLiveMode() && ! $this->isLiveConfigured()) {
            throw new RuntimeException('Live mode is enabled but live credentials are not configured.');
        }

        $baseUrl = rtrim(config('paymongo.base_url', 'https://api.paymongo.com'), '/');

        // Amount in cents / subunits (PHP 100.00 = 10000)
        $amountInSubunits = (int) round(((float) $params['amount']) * 100);

        $allowedMethods = $this->getEnabledMethods();
        $requestedMethods = ! empty($params['payment_method_types'])
            ? array_values(array_intersect($params['payment_method_types'], $allowedMethods))
            : $allowedMethods;

        if (empty($requestedMethods)) {
            $requestedMethods = $allowedMethods;
        }

        $payload = [
            'data' => [
                'attributes' => [
                    'line_items' => [
                        [
                            'name' => $params['name'] ?? 'Payment',
                            'amount' => $amountInSubunits,
                            'currency' => 'PHP',
                            'quantity' => 1,
                            'description' => $params['description'] ?? null,
                        ],
                    ],
                    'payment_method_types' => $requestedMethods,
                    'success_url' => $params['success_url'],
                    'cancel_url' => $params['cancel_url'] ?? $params['success_url'],
                    'reference_number' => $params['reference_number'] ?? null,
                    'send_email_receipt' => (bool) ($params['send_email_receipt'] ?? SystemSetting::get('paymongo_send_email_receipt', true)),
                    'pass_on_fees' => (bool) ($params['pass_on_fees'] ?? SystemSetting::get('paymongo_pass_on_fees', false)),
                    'metadata' => $params['metadata'] ?? [],
                ],
            ],
        ];

        // Filter out null description
        if ($payload['data']['attributes']['line_items'][0]['description'] === null) {
            unset($payload['data']['attributes']['line_items'][0]['description']);
        }

        $response = Http::withBasicAuth($secretKey, '')
            ->timeout(20)
            ->post("{$baseUrl}/v2/checkout_sessions", $payload);

        if (! $response->successful()) {
            $errorMsg = $response->json('errors.0.detail') ?? $response->json('errors.0.code') ?? 'Failed to create PayMongo checkout session.';
            Log::error('PayMongo checkout session creation failed', [
                'status' => $response->status(),
                'error' => $response->json('errors'),
            ]);
            throw new RuntimeException($errorMsg);
        }

        return $response->json('data') ?? [];
    }

    /**
     * Retrieve a Checkout Session from PayMongo API (v1 / v2 compatible).
     */
    public function getCheckoutSession(string $sessionId): array
    {
        $secretKey = $this->getActiveSecretKey();
        if (empty($secretKey)) {
            throw new RuntimeException('Missing active PayMongo secret key.');
        }

        $baseUrl = rtrim(config('paymongo.base_url', 'https://api.paymongo.com'), '/');

        $response = Http::withBasicAuth($secretKey, '')
            ->timeout(15)
            ->get("{$baseUrl}/v1/checkout_sessions/{$sessionId}");

        if (! $response->successful()) {
            $errorMsg = $response->json('errors.0.detail') ?? 'Failed to retrieve checkout session.';
            throw new RuntimeException($errorMsg);
        }

        return $response->json('data') ?? [];
    }

    /**
     * Authoritative payment verification for a transaction and checkout session.
     * Never trusts client-side confirmation alone (Requirement 9 & 10).
     */
    public function verifyAndCompletePayment(Transaction $transaction): array
    {
        $sessionId = $transaction->paymongo_checkout_session_id;
        if (empty($sessionId)) {
            throw new RuntimeException('Transaction does not have an associated PayMongo checkout session.');
        }

        // If already completed, return status immediately (idempotent)
        if ($transaction->status === 'completed') {
            return [
                'status' => 'completed',
                'already_completed' => true,
                'transaction' => $transaction,
            ];
        }

        $sessionData = $this->getCheckoutSession($sessionId);
        $attributes = $sessionData['attributes'] ?? [];
        $payments = $attributes['payments'] ?? [];
        $paidAt = $attributes['paid_at'] ?? null;

        // Check authoritative paid status
        $isPaid = false;
        $paidPayment = null;

        if (! empty($payments)) {
            foreach ($payments as $payment) {
                $pAttrs = $payment['attributes'] ?? [];
                if (($pAttrs['status'] ?? null) === 'paid') {
                    $isPaid = true;
                    $paidPayment = $payment;
                    break;
                }
            }
        } elseif (! empty($paidAt)) {
            $isPaid = true;
        }

        if (! $isPaid) {
            return [
                'status' => $transaction->status,
                'is_paid' => false,
                'message' => 'Payment has not been completed on PayMongo.',
                'transaction' => $transaction,
            ];
        }

        // Validate amount (subunits in PayMongo vs decimal in DB)
        if ($paidPayment !== null) {
            $paidAmount = ($paidPayment['attributes']['amount'] ?? 0) / 100;
            if (abs($paidAmount - (float) $transaction->amount) > 0.01) {
                Log::warning('PayMongo paid amount mismatch', [
                    'transaction_id' => $transaction->id,
                    'expected' => $transaction->amount,
                    'paid' => $paidAmount,
                ]);
            }
        }

        $completedTransaction = $this->handleSuccessfulPayment($sessionId, $paidPayment ?? [
            'id' => $attributes['payment_intent']['id'] ?? null,
            'attributes' => [
                'amount' => ((float) $transaction->amount) * 100,
                'fee' => 0,
                'source' => ['type' => $attributes['payment_method_used'] ?? 'online'],
            ],
        ]);

        return [
            'status' => 'completed',
            'is_paid' => true,
            'transaction' => $completedTransaction,
        ];
    }

    /**
     * Idempotently process successful payment and update accounts/loans.
     */
    public function handleSuccessfulPayment(string $checkoutSessionId, array $paymentData = []): Transaction
    {
        return DB::transaction(function () use ($checkoutSessionId, $paymentData) {
            $transaction = Transaction::where('paymongo_checkout_session_id', $checkoutSessionId)
                ->lockForUpdate()
                ->first();

            if (! $transaction) {
                throw new RuntimeException("Transaction with checkout session {$checkoutSessionId} not found.");
            }

            // IDEMPOTENCY: If transaction is already completed, do not credit again!
            if ($transaction->status === 'completed') {
                return $transaction;
            }

            if ($transaction->transaction_type === 'loan_payment') {
                $allocations = DB::table('loan_payment_installment_allocations')
                    ->where('transaction_id', $transaction->id)
                    ->get(['period', 'amount'])
                    ->map(fn ($allocation) => ['period' => (int) $allocation->period, 'amount' => (float) $allocation->amount])
                    ->all();
                if ($allocations !== []) {
                    $loan = Loan::whereKey($transaction->loan_id)->lockForUpdate()->firstOrFail();
                    app(LoanPaymentAllocationService::class)->validate($loan, $transaction, $allocations, (float) $transaction->penalty_amount);
                }
            }

            $paymentId = $paymentData['id'] ?? $transaction->paymongo_payment_id;
            $paymentAttrs = $paymentData['attributes'] ?? [];
            $fee = isset($paymentAttrs['fee']) ? ((float) $paymentAttrs['fee']) / 100 : null;
            $sourceType = $paymentAttrs['source']['type'] ?? $transaction->payment_method_used;

            $transaction->update([
                'status' => 'completed',
                'paymongo_payment_id' => $paymentId,
                'paymongo_fee' => $fee,
                'payment_method_used' => $sourceType,
                'approved_at' => now(),
            ]);

            $targetAccount = Account::whereKey($transaction->account_id)->lockForUpdate()->first();

            if ($transaction->transaction_type === 'deposit') {
                if ($targetAccount) {
                    $targetAccount->increment('balance', $transaction->amount);
                }
            } elseif ($transaction->transaction_type === 'loan_payment') {
                // If loan payment was made via online portal, credit loan payment
                $loan = Loan::whereKey($transaction->loan_id)->lockForUpdate()->first();
                if ($loan) {
                    $loan->refresh();
                    if ($loan->remainingBalance() <= 0 && $loan->loan_status !== 'paid_off') {
                        $loan->update(['loan_status' => 'paid_off']);
                    }
                }
            }

            // Log activity
            try {
                $user = $targetAccount?->user ?? User::find($transaction->recorded_by);
                app(AuditLogService::class)->log(
                    'payment.paymongo.success',
                    "PayMongo payment completed for transaction {$transaction->transaction_number}.",
                    'transaction',
                    $transaction,
                    $user
                );

                if ($user) {
                    app(NotificationService::class)->notifyTransactionStatus(
                        $user,
                        $transaction,
                        'completed',
                        'Your PayMongo payment total was ₱'.number_format((float) $transaction->amount + (float) $transaction->fee_amount, 2)
                            .((float) $transaction->fee_amount > 0 ? ' (including a ₱'.number_format((float) $transaction->fee_amount, 2).' transaction fee).' : '.'),
                    );
                }
            } catch (Throwable $e) {
                Log::warning('PayMongo post-completion notification/audit failed: '.$e->getMessage());
            }

            return $transaction;
        });
    }

    /**
     * Verify PayMongo webhook signature.
     * Header format: t=<timestamp>,te=<test_signature>,li=<live_signature>
     */
    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        if (empty($signatureHeader) || empty($rawPayload)) {
            return false;
        }

        $webhookSecret = $this->getActiveWebhookSecret();
        if (empty($webhookSecret)) {
            Log::warning('PayMongo webhook signature verification failed: No active webhook secret configured.');

            return false;
        }

        // Parse signature header
        parse_str(str_replace(',', '&', $signatureHeader), $headerParts);

        $timestamp = $headerParts['t'] ?? null;
        $testSignature = $headerParts['te'] ?? null;
        $liveSignature = $headerParts['li'] ?? null;

        if (empty($timestamp)) {
            return false;
        }

        $expectedSignature = $this->isLiveMode() ? $liveSignature : $testSignature;
        if (empty($expectedSignature)) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$rawPayload}";
        $computedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

        return hash_equals($computedSignature, $expectedSignature);
    }

    /**
     * Process an incoming verified webhook event safely and idempotently.
     */
    public function processWebhook(array $eventPayload): array
    {
        $data = $eventPayload['data'] ?? [];
        $eventId = $data['id'] ?? null;
        $eventType = $data['attributes']['type'] ?? $eventPayload['event_type'] ?? null;

        if (empty($eventId)) {
            $eventId = 'evt_'.md5(json_encode($eventPayload));
        }

        // Deduplication & idempotency check
        $existing = PaymongoWebhookEvent::where('event_id', $eventId)->first();
        if ($existing && $existing->processed_at !== null) {
            return [
                'status' => 'already_processed',
                'event_id' => $eventId,
            ];
        }

        $webhookEvent = PaymongoWebhookEvent::updateOrCreate(
            ['event_id' => $eventId],
            [
                'event_type' => (string) ($eventType ?? 'unknown'),
                'payload' => $eventPayload,
            ]
        );

        // Handle checkout session completion event
        if ($eventType === 'checkout_session.payment.paid') {
            $checkoutSession = $data['attributes']['data'] ?? [];
            $sessionId = $checkoutSession['id'] ?? null;
            $payments = $checkoutSession['attributes']['payments'] ?? [];
            $firstPayment = $payments[0] ?? [];

            if (! empty($sessionId)) {
                $this->handleSuccessfulPayment($sessionId, $firstPayment);
            }
        } elseif ($eventType === 'payment.paid') {
            // Check if payment links to a payment intent or checkout session in metadata
            $paymentResource = $data['attributes']['data'] ?? [];
            $paymentId = $paymentResource['id'] ?? null;
            $metadata = $paymentResource['attributes']['metadata'] ?? [];
            $txnNumber = $metadata['transaction_number'] ?? null;

            if ($txnNumber) {
                $transaction = Transaction::where('transaction_number', $txnNumber)->first();
                if ($transaction && $transaction->status !== 'completed' && $transaction->paymongo_checkout_session_id) {
                    $this->handleSuccessfulPayment($transaction->paymongo_checkout_session_id, $paymentResource);
                }
            }
        }

        $webhookEvent->update(['processed_at' => now()]);

        return [
            'status' => 'processed',
            'event_id' => $eventId,
        ];
    }

    /**
     * Test connection to PayMongo API using configured secret key.
     */
    public function testConnection(): array
    {
        $secretKey = $this->getActiveSecretKey();
        if (empty($secretKey)) {
            throw new RuntimeException('No active secret key configured.');
        }

        $baseUrl = rtrim(config('paymongo.base_url', 'https://api.paymongo.com'), '/');

        // Test with a lightweight request: retrieve payment methods or checkout sessions
        $response = Http::withBasicAuth($secretKey, '')
            ->timeout(10)
            ->get("{$baseUrl}/v1/payment_methods?limit=1");

        // Even 200 or 400 with valid auth response means key was decrypted and authenticated
        if ($response->status() === 401) {
            throw new RuntimeException('PayMongo rejected credentials: Unauthorized (invalid secret key).');
        }

        if (! in_array($response->status(), [200, 400, 404], true)) {
            throw new RuntimeException("PayMongo connection test returned status {$response->status()}.");
        }

        return [
            'success' => true,
            'mode' => $this->getMode(),
            'message' => 'Successfully connected and authenticated with PayMongo.',
        ];
    }
}
