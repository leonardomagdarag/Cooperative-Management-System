<?php

use App\Models\Account;
use App\Models\AppCredential;
use App\Models\Loan;
use App\Models\PaymongoWebhookEvent;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PayMongoService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);
});

function createTestAdmin(string $permission = 'settings.update'): User
{
    $admin = User::factory()->create([
        'role' => 'admin',
        'email' => 'admin-'.Str::random(8).'@example.com',
        'password' => 'password',
        'account_status' => 'active',
    ]);
    $admin->syncRoles(['admin']);
    $admin->givePermissionTo($permission);

    return $admin;
}

function createTestMember(): User
{
    $member = User::factory()->create([
        'role' => 'member',
        'email' => 'member-'.Str::random(8).'@example.com',
        'password' => 'password',
        'account_status' => 'active',
    ]);
    $member->syncRoles(['member']);

    // Create a savings account for the member
    Account::create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 5000.00,
    ]);

    return $member;
}

test('unauthorized guest cannot access PayMongo settings or credentials', function () {
    $this->getJson('/api/settings/paymongo')
        ->assertStatus(401);

    $this->putJson('/api/settings/paymongo', ['enabled' => true, 'mode' => 'sandbox', 'enabled_methods' => ['gcash']])
        ->assertStatus(401);

    $this->putJson('/api/settings/paymongo/credentials', ['test_secret_key' => 'sk_test_123'])
        ->assertStatus(401);
});

test('normal authenticated member cannot access PayMongo settings or credentials', function () {
    $member = createTestMember();

    $this->actingAs($member)
        ->getJson('/api/settings/paymongo')
        ->assertStatus(403);

    $this->actingAs($member)
        ->putJson('/api/settings/paymongo/credentials', ['test_secret_key' => 'sk_test_hacked'])
        ->assertStatus(403);

    $this->actingAs($member)
        ->deleteJson('/api/settings/paymongo/credentials')
        ->assertStatus(403);
});

test('non-admin settings users cannot access PayMongo configuration or tests', function () {
    $officer = User::factory()->create([
        'role' => 'secretary',
        'email' => 'secretary-'.Str::random(8).'@example.com',
        'password' => 'password',
        'account_status' => 'active',
    ]);
    $officer->syncRoles(['secretary']);
    $officer->givePermissionTo(['settings.view', 'settings.update']);

    $this->actingAs($officer)
        ->getJson('/api/settings/paymongo')
        ->assertForbidden();

    $this->actingAs($officer)
        ->putJson('/api/settings/paymongo', ['enabled' => false, 'mode' => 'sandbox', 'enabled_methods' => []])
        ->assertForbidden();

    $this->actingAs($officer)
        ->putJson('/api/settings/paymongo/credentials', ['test_secret_key' => 'sk_test_hacked'])
        ->assertForbidden();

    $this->actingAs($officer)
        ->deleteJson('/api/settings/paymongo/credentials')
        ->assertForbidden();

    $this->actingAs($officer)
        ->postJson('/api/settings/paymongo/test')
        ->assertForbidden();
});

test('admin can save PayMongo credentials and secret keys are encrypted at rest in the database', function () {
    $admin = createTestAdmin();

    $this->actingAs($admin)
        ->putJson('/api/settings/paymongo/credentials', [
            'test_public_key' => 'pk_test_ABC123456789',
            'test_secret_key' => 'sk_test_SECRET_KEY_XYZ987',
            'test_webhook_secret' => 'whsk_test_WEBHOOK_SECRET_456',
        ])
        ->assertOk()
        ->assertJsonPath('data.configured', true)
        ->assertJsonPath('data.stored.test_secret_key', true);

    // Verify in database: the raw column MUST NOT contain the plaintext secret
    $rawSecret = DB::table('app_credentials')->where('key', PayMongoService::KEY_TEST_SECRET_KEY)->value('value');
    expect($rawSecret)->not->toBeNull()
        ->and($rawSecret)->not->toBe('sk_test_SECRET_KEY_XYZ987')
        ->and(Crypt::decryptString($rawSecret))->toBe('sk_test_SECRET_KEY_XYZ987');

    // Server-side retrieval automatically decrypts
    $service = app(PayMongoService::class);
    expect($service->getActiveSecretKey())->toBe('sk_test_SECRET_KEY_XYZ987')
        ->and($service->getActivePublicKey())->toBe('pk_test_ABC123456789')
        ->and($service->getActiveWebhookSecret())->toBe('whsk_test_WEBHOOK_SECRET_456');
});

test('admin settings API responses never leak secret keys or webhook secrets', function () {
    $admin = createTestAdmin();

    // Pre-populate credentials
    AppCredential::create([
        'key' => PayMongoService::KEY_TEST_SECRET_KEY,
        'value' => 'sk_test_SUPER_SECRET_VALUE_NEVER_LEAK',
    ]);
    AppCredential::create([
        'key' => PayMongoService::KEY_TEST_WEBHOOK_SECRET,
        'value' => 'whsk_test_WEBHOOK_SECRET_NEVER_LEAK',
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/settings/paymongo')
        ->assertOk();

    $content = $response->getContent();

    // Assert that the raw plaintext secrets are nowhere in the output
    expect($content)->not->toContain('sk_test_SUPER_SECRET_VALUE_NEVER_LEAK')
        ->and($content)->not->toContain('whsk_test_WEBHOOK_SECRET_NEVER_LEAK');

    // Assert that summary provides safe boolean indicators
    expect($response->json('data.stored.test_secret_key'))->toBeTrue()
        ->and($response->json('data.stored.test_webhook_secret'))->toBeTrue();
});

test('sandbox mode is default and safe while live mode requires explicit configuration', function () {
    $admin = createTestAdmin();
    $service = app(PayMongoService::class);

    // Default mode is sandbox
    expect($service->getMode())->toBe('sandbox')
        ->and($service->isLiveMode())->toBeFalse();

    // Attempting to switch to live mode without live credentials is rejected with 422
    $this->actingAs($admin)
        ->putJson('/api/settings/paymongo', [
            'enabled' => true,
            'mode' => 'live',
            'enabled_methods' => ['gcash', 'card'],
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'live_credentials_missing');

    // Now configure live credentials
    $this->actingAs($admin)
        ->putJson('/api/settings/paymongo/credentials', [
            'live_public_key' => 'pk_live_PUBLIC_123',
            'live_secret_key' => 'sk_live_SECRET_987',
            'live_webhook_secret' => 'whsk_live_WEBHOOK_456',
        ])
        ->assertOk();

    // Now switching to live mode succeeds
    $this->actingAs($admin)
        ->putJson('/api/settings/paymongo', [
            'enabled' => true,
            'mode' => 'live',
            'enabled_methods' => ['gcash', 'card'],
        ])
        ->assertOk()
        ->assertJsonPath('data.mode', 'live');

    expect($service->isLiveMode())->toBeTrue()
        ->and($service->getActiveSecretKey())->toBe('sk_live_SECRET_987');
});

test('admin can remove stored PayMongo credentials', function () {
    $admin = createTestAdmin();

    AppCredential::create(['key' => PayMongoService::KEY_TEST_PUBLIC_KEY, 'value' => 'pk_test_123']);
    AppCredential::create(['key' => PayMongoService::KEY_TEST_SECRET_KEY, 'value' => 'sk_test_456']);

    $this->actingAs($admin)
        ->deleteJson('/api/settings/paymongo/credentials')
        ->assertOk()
        ->assertJsonPath('data.configured', false);

    expect(AppCredential::where('key', 'like', 'paymongo_%')->count())->toBe(0);
});

test('disabling PayMongo prevents checkout creation', function () {
    $member = createTestMember();

    // Configure credentials but disable integration
    AppCredential::create(['key' => PayMongoService::KEY_TEST_PUBLIC_KEY, 'value' => 'pk_test_123']);
    AppCredential::create(['key' => PayMongoService::KEY_TEST_SECRET_KEY, 'value' => 'sk_test_456']);
    SystemSetting::set('paymongo_enabled', false);

    $this->actingAs($member)
        ->postJson('/api/services/paymongo/checkout', [
            'service_type' => 'deposit',
            'amount' => 500,
            'account_type' => 'savings',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'paymongo_disabled');
});

test('member can initiate checkout and receives checkout_url without exposing secrets', function () {
    $member = createTestMember();

    AppCredential::create(['key' => PayMongoService::KEY_TEST_PUBLIC_KEY, 'value' => 'pk_test_123']);
    AppCredential::create(['key' => PayMongoService::KEY_TEST_SECRET_KEY, 'value' => 'sk_test_456']);
    SystemSetting::set('paymongo_enabled', true);
    SystemSetting::set('paymongo_mode', 'sandbox');
    SystemSetting::set('paymongo_methods', ['gcash', 'paymaya', 'card']);

    Http::fake([
        'https://api.paymongo.com/v2/checkout_sessions' => Http::response([
            'data' => [
                'id' => 'cs_test_session_12345',
                'type' => 'checkout_session',
                'attributes' => [
                    'checkout_url' => 'https://checkout.paymongo.com/cs_test_session_12345',
                    'status' => 'active',
                    'line_items' => [
                        ['name' => 'Deposit - Savings Account', 'amount' => 50000, 'currency' => 'PHP'],
                    ],
                    'payment_method_types' => ['gcash', 'paymaya', 'card'],
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($member)
        ->postJson('/api/services/paymongo/checkout', [
            'service_type' => 'deposit',
            'amount' => 500,
            'account_type' => 'savings',
            'note' => 'Deposit test note',
        ])
        ->assertStatus(201)
        ->assertJsonPath('checkout_url', 'https://checkout.paymongo.com/cs_test_session_12345')
        ->assertJsonPath('checkout_session_id', 'cs_test_session_12345');

    expect($response->getContent())->not->toContain('sk_test_456');

    // Assert transaction was recorded in pending status
    $txn = Transaction::where('paymongo_checkout_session_id', 'cs_test_session_12345')->first();
    expect($txn)->not->toBeNull()
        ->and($txn->status)->toBe('pending')
        ->and((float) $txn->amount)->toBe(500.00)
        ->and($txn->payment_gateway)->toBe('paymongo');
});

test('payment verification authoritatively checks PayMongo API and is idempotent', function () {
    $member = createTestMember();
    $account = Account::where('user_id', $member->id)->where('account_type', 'savings')->first();
    $initialBalance = (float) $account->balance;

    AppCredential::create(['key' => PayMongoService::KEY_TEST_PUBLIC_KEY, 'value' => 'pk_test_123']);
    AppCredential::create(['key' => PayMongoService::KEY_TEST_SECRET_KEY, 'value' => 'sk_test_456']);
    SystemSetting::set('paymongo_enabled', true);

    $txn = Transaction::create([
        'account_id' => $account->id,
        'transaction_type' => 'deposit',
        'transaction_mode' => 'online',
        'payment_gateway' => 'paymongo',
        'paymongo_checkout_session_id' => 'cs_test_session_paid_999',
        'amount' => 1000.00,
        'status' => 'pending',
        'recorded_by' => $member->id,
    ]);

    // Mock PayMongo API returning completed/paid checkout session
    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions/cs_test_session_paid_999' => Http::response([
            'data' => [
                'id' => 'cs_test_session_paid_999',
                'type' => 'checkout_session',
                'attributes' => [
                    'paid_at' => time(),
                    'payment_method_used' => 'gcash',
                    'payments' => [
                        [
                            'id' => 'pay_test_9999',
                            'attributes' => [
                                'status' => 'paid',
                                'amount' => 100000, // 1000.00 in cents
                                'fee' => 2500,      // 25.00 in cents
                                'source' => ['type' => 'gcash'],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    // Call verify endpoint
    $response = $this->actingAs($member)
        ->getJson("/api/services/paymongo/verify/{$txn->transaction_number}")
        ->assertOk()
        ->assertJsonPath('is_paid', true)
        ->assertJsonPath('status', 'completed');

    $txn->refresh();
    $account->refresh();

    expect($txn->status)->toBe('completed')
        ->and($txn->paymongo_payment_id)->toBe('pay_test_9999')
        ->and((float) $txn->paymongo_fee)->toBe(25.00)
        ->and((float) $account->balance)->toBe($initialBalance + 1000.00);

    // SECOND CALL (Idempotency test): Re-verifying must NOT increment the balance again!
    $this->actingAs($member)
        ->getJson("/api/services/paymongo/verify/{$txn->transaction_number}")
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    $account->refresh();
    expect((float) $account->balance)->toBe($initialBalance + 1000.00);
});

test('webhook signature verification accepts genuine PayMongo webhooks and rejects forged ones', function () {
    $member = createTestMember();
    $account = Account::where('user_id', $member->id)->where('account_type', 'savings')->first();
    $initialBalance = (float) $account->balance;

    $webhookSecret = 'whsk_test_VALID_SECRET_123';
    AppCredential::create(['key' => PayMongoService::KEY_TEST_WEBHOOK_SECRET, 'value' => $webhookSecret]);
    SystemSetting::set('paymongo_enabled', true);
    SystemSetting::set('paymongo_mode', 'sandbox');

    $txn = Transaction::create([
        'account_id' => $account->id,
        'transaction_type' => 'deposit',
        'transaction_mode' => 'online',
        'payment_gateway' => 'paymongo',
        'paymongo_checkout_session_id' => 'cs_test_webhook_session_777',
        'amount' => 500.00,
        'status' => 'pending',
        'recorded_by' => $member->id,
    ]);

    $payload = [
        'data' => [
            'id' => 'evt_test_checkout_paid_777',
            'type' => 'event',
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'data' => [
                    'id' => 'cs_test_webhook_session_777',
                    'type' => 'checkout_session',
                    'attributes' => [
                        'payments' => [
                            [
                                'id' => 'pay_wh_test_777',
                                'attributes' => [
                                    'status' => 'paid',
                                    'amount' => 50000,
                                    'fee' => 1250,
                                    'source' => ['type' => 'gcash'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $rawJson = json_encode($payload);
    $timestamp = (string) time();

    // 1. Forged signature test -> must be rejected with 400
    $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
        'HTTP_Paymongo-Signature' => "t={$timestamp},te=fake_invalid_signature,li=",
        'CONTENT_TYPE' => 'application/json',
    ], $rawJson)->assertStatus(400);

    $txn->refresh();
    expect($txn->status)->toBe('pending');

    // 2. Valid signature test -> must succeed
    $computedSig = hash_hmac('sha256', "{$timestamp}.{$rawJson}", $webhookSecret);
    $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
        'HTTP_Paymongo-Signature' => "t={$timestamp},te={$computedSig},li=",
        'CONTENT_TYPE' => 'application/json',
    ], $rawJson)->assertStatus(200);

    $txn->refresh();
    $account->refresh();

    expect($txn->status)->toBe('completed')
        ->and($txn->paymongo_payment_id)->toBe('pay_wh_test_777')
        ->and((float) $account->balance)->toBe($initialBalance + 500.00);

    // 3. Duplicate webhook delivery test (Idempotency) -> must return 200 without double crediting
    $this->call('POST', '/api/webhooks/paymongo', [], [], [], [
        'HTTP_Paymongo-Signature' => "t={$timestamp},te={$computedSig},li=",
        'CONTENT_TYPE' => 'application/json',
    ], $rawJson)->assertStatus(200);

    $account->refresh();
    expect((float) $account->balance)->toBe($initialBalance + 500.00);

    // Assert event was tracked in webhook events table
    expect(PaymongoWebhookEvent::where('event_id', 'evt_test_checkout_paid_777')->exists())->toBeTrue();
});

test('member can initiate loan payment checkout and completing it marks loan paid off when balance is zero', function () {
    $member = createTestMember();

    AppCredential::create(['key' => PayMongoService::KEY_TEST_PUBLIC_KEY, 'value' => 'pk_test_123']);
    AppCredential::create(['key' => PayMongoService::KEY_TEST_SECRET_KEY, 'value' => 'sk_test_456']);
    SystemSetting::set('paymongo_enabled', true);

    $loan = Loan::create([
        'user_id' => $member->id,
        'loan_amount' => 5000.00,
        'interest_rate' => 3.0,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'loan_status' => 'active',
        'applied_at' => now(),
        'approved_at' => now(),
    ]);

    Http::fake([
        'https://api.paymongo.com/v2/checkout_sessions' => Http::response([
            'data' => [
                'id' => 'cs_test_loan_session_555',
                'type' => 'checkout_session',
                'attributes' => [
                    'checkout_url' => 'https://checkout.paymongo.com/cs_test_loan_session_555',
                    'status' => 'active',
                ],
            ],
        ], 200),
        'https://api.paymongo.com/v1/checkout_sessions/cs_test_loan_session_555' => Http::response([
            'data' => [
                'id' => 'cs_test_loan_session_555',
                'type' => 'checkout_session',
                'attributes' => [
                    'paid_at' => time(),
                    'payment_method_used' => 'gcash',
                    'payments' => [
                        [
                            'id' => 'pay_loan_555',
                            'attributes' => [
                                'status' => 'paid',
                                'amount' => 515000, // full balance in cents
                                'fee' => 1000,
                                'source' => ['type' => 'gcash'],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $remaining = $loan->remainingBalance();

    $response = $this->actingAs($member)
        ->postJson('/api/services/paymongo/checkout', [
            'service_type' => 'loan_payment',
            'loan_id' => $loan->id,
            'amount' => $remaining,
        ])
        ->assertStatus(201)
        ->assertJsonPath('checkout_url', 'https://checkout.paymongo.com/cs_test_loan_session_555');

    $txnNumber = $response->json('transaction_number');

    // Authoritatively verify and complete payment
    $this->actingAs($member)
        ->getJson("/api/services/paymongo/verify/{$txnNumber}")
        ->assertOk()
        ->assertJsonPath('is_paid', true)
        ->assertJsonPath('status', 'completed');

    $loan->refresh();
    expect($loan->loan_status)->toBe('paid_off');
});

test('member cannot pay someone elses loan unless officer or admin', function () {
    $member1 = createTestMember();
    $member2 = createTestMember();

    AppCredential::create(['key' => PayMongoService::KEY_TEST_PUBLIC_KEY, 'value' => 'pk_test_123']);
    AppCredential::create(['key' => PayMongoService::KEY_TEST_SECRET_KEY, 'value' => 'sk_test_456']);
    SystemSetting::set('paymongo_enabled', true);

    $loan = Loan::create([
        'user_id' => $member2->id,
        'loan_amount' => 10000.00,
        'interest_rate' => 3.0,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'loan_status' => 'active',
    ]);

    // Member 1 tries to pay Member 2's loan -> rejected
    $this->actingAs($member1)
        ->postJson('/api/services/paymongo/checkout', [
            'service_type' => 'loan_payment',
            'loan_id' => $loan->id,
            'amount' => 1000,
        ])
        ->assertStatus(422);
});
