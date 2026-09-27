<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\Notification;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(SystemSettingSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->manager = User::where('email', 'manager@example.com')->firstOrFail();
    $this->secretary = User::where('email', 'secretary@example.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@example.com')->firstOrFail();

    $this->mainBranch = Branch::where('code', 'BR000001')->firstOrFail();
    $this->account = Account::where('user_id', $this->member->id)->where('account_type', 'savings')->firstOrFail();
});

test('F020-1: unauthenticated requests to service availability endpoints return 401', function () {
    $this->getJson('/api/settings/service-availability')->assertUnauthorized();
    $this->putJson('/api/settings/service-availability', [])->assertUnauthorized();
});

test('F020-2: regular member without settings.view receives 403', function () {
    $this->actingAs($this->member);

    $this->getJson('/api/settings/service-availability')->assertForbidden();
    $this->putJson('/api/settings/service-availability', [])->assertForbidden();
});

test('F020-3: user without settings.update receives 403 on updating service availability', function () {
    // Secretary role has settings.view but not settings.update
    $this->actingAs($this->secretary);

    $this->getJson('/api/settings/service-availability')->assertOk();
    $this->putJson('/api/settings/service-availability', ['services' => []])->assertForbidden();
});

test('F020-4: authorized user retrieves full service availability schema', function () {
    $this->actingAs($this->admin);

    $response = $this->getJson('/api/settings/service-availability');
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'deposit' => ['global', 'member', 'officer', 'counter', 'online'],
                'withdrawal' => ['global', 'member', 'officer', 'counter', 'online', 'bank_transfer'],
                'loan_application' => ['global', 'member', 'officer'],
                'loan_payment' => ['global', 'member', 'officer', 'counter', 'online'],
                'registration' => ['global', 'member'],
                'login' => ['global', 'member', 'officer'],
            ],
        ]);
});

test('F020-5: updating service availability rejects unknown services (422)', function () {
    $this->actingAs($this->admin);

    $response = $this->putJson('/api/settings/service-availability', [
        'services' => [
            'crypto_trading' => ['global' => true],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'One or more services are invalid.']);
});

test('F020-6: admin updates service availability flags successfully', function () {
    $this->actingAs($this->admin);

    $payload = [
        'services' => [
            'deposit' => [
                'global' => true,
                'member' => false,
                'officer' => true,
                'counter' => true,
                'online' => false,
            ],
        ],
        'notify_affected' => false,
    ];

    $response = $this->putJson('/api/settings/service-availability', $payload);
    $response->assertOk()
        ->assertJson(['message' => 'Service availability updated.']);

    // Check system setting values
    expect((bool) SystemSetting::get('service_deposit_member_enabled'))->toBeFalse()
        ->and((bool) SystemSetting::get('service_deposit_online_enabled'))->toBeFalse()
        ->and((bool) SystemSetting::get('service_deposit_officer_enabled'))->toBeTrue()
        ->and((bool) SystemSetting::get('service_deposit_counter_enabled'))->toBeTrue();
});

test('F020-7: CheckServiceAvailability middleware blocks disabled service with HTTP 423', function () {
    // Disable member deposits
    SystemSetting::set('service_deposit_member_enabled', false);

    $this->actingAs($this->member);

    $response = $this->postJson('/api/services/deposit', [
        'account_id' => $this->account->id,
        'amount' => 500,
        'transaction_mode' => 'counter',
    ]);

    $response->assertStatus(423)
        ->assertJson([
            'message' => 'This service is currently under maintenance. Please try again later.',
            'service' => 'deposit',
            'maintenance' => true,
        ]);
});

test('F020-8: transaction mode toggle blocks specific channel with 423 while allowing other channels', function () {
    // Enable member deposits, but disable online deposits while keeping counter deposits enabled
    SystemSetting::set('service_deposit_enabled', true);
    SystemSetting::set('service_deposit_member_enabled', true);
    SystemSetting::set('service_deposit_online_enabled', false);
    SystemSetting::set('service_deposit_counter_enabled', true);

    $this->actingAs($this->member);

    // Online deposit attempt is blocked with 423
    $onlineResponse = $this->postJson('/api/services/deposit', [
        'account_id' => $this->account->id,
        'amount' => 500,
        'transaction_mode' => 'online',
    ]);
    $onlineResponse->assertStatus(423);

    // Counter deposit attempt passes availability check (returns 200 or 201)
    $counterResponse = $this->postJson('/api/services/deposit', [
        'account_id' => $this->account->id,
        'amount' => 500,
        'transaction_mode' => 'counter',
    ]);
    expect($counterResponse->status())->not->toBe(423);
});

test('F020-9: user-type toggle allows officers to operate even when member access is disabled', function () {
    // Disable member deposits, but keep officer deposits enabled
    SystemSetting::set('service_deposit_enabled', true);
    SystemSetting::set('service_deposit_member_enabled', false);
    SystemSetting::set('service_deposit_officer_enabled', true);
    SystemSetting::set('service_deposit_counter_enabled', true);

    // Member self-service is blocked
    $memberResponse = $this->actingAs($this->member)->postJson('/api/services/deposit', [
        'account_id' => $this->account->id,
        'amount' => 500,
        'transaction_mode' => 'counter',
    ]);
    $memberResponse->assertStatus(423);

    // Officer act-on-behalf for the member passes availability check
    $officerResponse = $this->actingAs($this->manager)->postJson('/api/services/deposit', [
        'member_id' => $this->member->id,
        'account_id' => $this->account->id,
        'amount' => 500,
        'transaction_mode' => 'counter',
    ]);
    expect($officerResponse->status())->not->toBe(423);
});

test('F020-10: notify_affected dispatches notifications when services become newly unavailable', function () {
    // Ensure deposit was previously enabled
    SystemSetting::set('service_deposit_member_enabled', true);

    $initialNotifCount = Notification::count();

    $this->actingAs($this->admin);

    $response = $this->putJson('/api/settings/service-availability', [
        'services' => [
            'deposit' => [
                'global' => true,
                'member' => false, // Newly disabled
                'officer' => true,
                'counter' => true,
                'online' => true,
            ],
        ],
        'notify_affected' => true,
    ]);

    $response->assertOk();

    // Check notifications were created for active members
    $memberNotif = Notification::where('user_id', $this->member->id)
        ->where('title', 'Service Unavailable')
        ->first();
    expect($memberNotif)->not->toBeNull()
        ->and($memberNotif->message)->toContain('Deposit');
});

test('F020-11: offline bank transfer deposit stays available when online payment is disabled and requires proof', function () {
    Storage::fake('public');

    // Disable the online deposit channel only
    SystemSetting::set('service_deposit_enabled', true);
    SystemSetting::set('service_deposit_member_enabled', true);
    SystemSetting::set('service_deposit_online_enabled', false);
    SystemSetting::set('service_deposit_counter_enabled', true);

    $this->actingAs($this->member);
    $balanceBefore = (float) $this->account->fresh()->balance;

    // bank_transfer deposit without proof must be rejected
    $this->postJson('/api/services/deposit', [
        'account_id' => $this->account->id,
        'amount' => 500,
        'account_type' => 'savings',
        'transaction_mode' => 'bank_transfer',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('proof');

    // bank_transfer deposit with proof must pass availability AND validation, staying pending
    $this->postJson('/api/services/deposit', [
        'account_id' => $this->account->id,
        'amount' => 500,
        'account_type' => 'savings',
        'transaction_mode' => 'bank_transfer',
        'proof' => UploadedFile::fake()->create('transfer.jpg', 100, 'image/jpeg'),
    ])->assertCreated();

    $txn = \App\Models\Transaction::query()
        ->where('account_id', $this->account->id)
        ->where('transaction_type', 'deposit')
        ->latest('id')
        ->first();

    expect($txn)->not->toBeNull()
        ->and($txn->transaction_mode)->toBe('bank_transfer')
        ->and($txn->status)->toBe('pending')
        ->and($txn->proof_of_payment_path)->not->toBeNull()
        ->and((float) $this->account->fresh()->balance)->toBe($balanceBefore);
});

test('F020-12: offline bank transfer loan payment stays available when online payment is disabled and requires proof', function () {
    Storage::fake('public');

    // Disable the online loan payment channel only
    SystemSetting::set('service_loan_payment_enabled', true);
    SystemSetting::set('service_loan_payment_member_enabled', true);
    SystemSetting::set('service_loan_payment_online_enabled', false);
    SystemSetting::set('service_loan_payment_counter_enabled', true);

    $this->account->update(['balance' => 5000]);

    $loan = Loan::factory()->create([
        'user_id' => $this->member->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'payment_frequency' => 'monthly',
        'loan_status' => 'active',
    ]);

    $this->actingAs($this->member);

    // bank_transfer loan payment without proof must be rejected
    $this->postJson('/api/services/loan-payment', [
        'loan_id' => $loan->id,
        'amount' => 620,
        'transaction_mode' => 'bank_transfer',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('proof');

    // bank_transfer loan payment with proof must pass, staying pending
    $this->postJson('/api/services/loan-payment', [
        'loan_id' => $loan->id,
        'amount' => 620,
        'transaction_mode' => 'bank_transfer',
        'proof' => UploadedFile::fake()->create('transfer.jpg', 100, 'image/jpeg'),
    ])->assertCreated();

    $payment = \App\Models\Transaction::query()
        ->where('loan_id', $loan->id)
        ->where('transaction_type', 'loan_payment')
        ->latest('id')
        ->first();

    expect($payment)->not->toBeNull()
        ->and($payment->transaction_mode)->toBe('bank_transfer')
        ->and($payment->status)->toBe('pending')
        ->and($payment->proof_of_payment_path)->not->toBeNull()
        ->and($loan->fresh()->loan_status)->toBe('active')
        ->and((float) $this->account->fresh()->balance)->toBe(5000.0);
});
