<?php

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LoanPaymentAllocationService;
use App\Services\XenditService;
use Carbon\Carbon;
use Database\Seeders\AccountSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(AccountSeeder::class);
    $this->seed(SystemSettingSeeder::class);
});

test('officer counter deposit returns success after crediting exactly once', function () {
    $officer = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $account = Account::where('user_id', $member->id)->where('account_type', 'savings')->firstOrFail();
    $balance = (float) $account->balance;
    $payload = [
        'member_id' => $member->id,
        'amount' => 250,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
        'client_request_id' => 'counter_deposit_001',
    ];

    $this->actingAs($officer)->postJson('/api/services/deposit', $payload)
        ->assertCreated()
        ->assertJsonPath('data.status', 'completed');
    $this->postJson('/api/services/deposit', $payload)->assertOk();

    expect((float) $account->fresh()->balance)->toBe($balance + 250)
        ->and(Transaction::where('client_request_id', 'counter_deposit_001')->count())->toBe(1);
});

test('officer counter loan payment validates allocations before completing payment', function () {
    $officer = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $account = Account::where('user_id', $member->id)->where('account_type', 'savings')->firstOrFail();
    $balance = (float) $account->balance;
    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 12000,
        'interest_rate' => 0,
        'term_months' => 12,
        'loan_status' => 'active',
        'disbursed_at' => now()->subMonths(3),
        'penalty_amount' => 0,
    ]);
    $payload = [
        'member_id' => $member->id,
        'loan_id' => $loan->id,
        'amount' => 1000,
        'installment_allocations' => [['period' => 1, 'amount' => 1000]],
        'penalty_amount' => 0,
        'transaction_mode' => 'counter',
        'client_request_id' => 'counter_loan_001',
    ];

    $this->actingAs($officer)->postJson('/api/services/loan-payment', $payload)
        ->assertCreated()
        ->assertJsonPath('data.status', 'completed');
    $this->postJson('/api/services/loan-payment', $payload)->assertOk();

    $payment = Transaction::where('client_request_id', 'counter_loan_001')->sole();
    expect($payment->account_id)->toBeNull()
        ->and((float) $account->fresh()->balance)->toBe($balance)
        ->and((float) $loan->fresh()->schedulePaid())->toBe(1000.0)
        ->and(DB::table('loan_payment_installment_allocations')->where('transaction_id', $payment->id)->value('amount'))->toBe(1000);

    $this->postJson('/api/services/loan-payment', [
        ...$payload,
        'amount' => 1001,
        'installment_allocations' => [['period' => 1, 'amount' => 1001]],
        'client_request_id' => 'counter_loan_002',
    ])->assertStatus(422);

    expect(Transaction::where('client_request_id', 'counter_loan_002')->exists())->toBeFalse();
});

test("a member's own counter deposit remains pending without changing the account balance", function () {
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $account = Account::where('user_id', $member->id)->where('account_type', 'savings')->firstOrFail();
    $initialBalance = (float) $account->balance;

    $response = $this->actingAs($member)->postJson('/api/services/deposit', [
        'amount' => 100,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.transaction_mode', 'counter')
        ->assertJsonPath('data.recorded_by', $member->id);

    $transaction = Transaction::findOrFail($response->json('data.id'));
    expect($transaction->approved_by)->toBeNull()
        ->and((float) $account->fresh()->balance)->toBe($initialBalance);
});

test('member dashboard marks an unpaid loan with past instalments as overdue', function () {
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 12000,
        'interest_rate' => 0,
        'term_months' => 12,
        'loan_status' => 'active',
        'applied_at' => now()->subMonths(3),
        'approved_at' => now()->subMonths(3),
        'disbursed_at' => now()->subMonths(3),
        'penalty_amount' => 0,
    ]);

    $response = $this->actingAs($member)->getJson('/api/dashboard')->assertOk();

    $response->assertJsonPath('loans.0.is_overdue', true);
    expect((int) $response->json('loans.0.days_overdue'))->toBeGreaterThan(0);
});

test('approved loans have no repayment schedule before disbursement', function () {
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 12000,
        'interest_rate' => 0,
        'term_months' => 12,
        'loan_status' => 'approved',
        'applied_at' => now()->subMonths(3),
        'approved_at' => now()->subMonths(3),
        'disbursed_at' => null,
        'due_date' => null,
        'penalty_amount' => 0,
    ]);

    expect($loan->paymentSchedule())->toBeNull()
        ->and($loan->getCollectionMetrics()['amount_due'])->toBe(0.0);
});

test('selected installment allocations reduce only the installment selected', function () {
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 12000,
        'interest_rate' => 0,
        'term_months' => 12,
        'loan_status' => 'active',
        'disbursed_at' => now()->subMonths(3),
        'penalty_amount' => 0,
    ]);
    $payment = Transaction::create([
        'loan_id' => $loan->id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'counter',
        'amount' => 400,
        'penalty_amount' => 0,
        'status' => 'completed',
        'transaction_date' => now(),
        'recorded_by' => $member->id,
    ]);
    DB::table('loan_payment_installment_allocations')->insert([
        'transaction_id' => $payment->id,
        'period' => 2,
        'amount' => 400,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $schedule = $loan->paymentSchedule();

    expect($schedule[0]['amount_due'])->toBe(1000.0)
        ->and($schedule[1]['amount_due'])->toBe(600.0);
});

test('pending payment allocations reserve an installment for concurrent checkouts', function () {
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 12000,
        'interest_rate' => 0,
        'term_months' => 12,
        'loan_status' => 'active',
        'disbursed_at' => now()->subMonths(3),
        'penalty_amount' => 0,
    ]);
    $existingPayment = Transaction::create([
        'loan_id' => $loan->id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'online',
        'payment_gateway' => 'xendit',
        'amount' => 1000,
        'penalty_amount' => 0,
        'status' => 'pending',
        'transaction_date' => now(),
        'recorded_by' => $member->id,
    ]);
    DB::table('loan_payment_installment_allocations')->insert([
        'transaction_id' => $existingPayment->id,
        'period' => 1,
        'amount' => 1000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $newPayment = Transaction::make([
        'loan_id' => $loan->id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'online',
        'payment_gateway' => 'xendit',
        'amount' => 1,
        'penalty_amount' => 0,
        'status' => 'pending',
        'transaction_date' => now(),
        'recorded_by' => $member->id,
    ]);

    expect(fn () => app(LoanPaymentAllocationService::class)->validate(
        $loan->fresh(),
        $newPayment,
        [['period' => 1, 'amount' => 1]],
        0
    ))->toThrow('An installment allocation exceeds its unpaid amount.');
});

test('rejected Xendit sessions no longer reserve installment allocations', function () {
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 12000,
        'interest_rate' => 0,
        'term_months' => 12,
        'loan_status' => 'active',
        'disbursed_at' => now()->subMonths(3),
        'penalty_amount' => 0,
    ]);
    $abandonedPayment = Transaction::create([
        'loan_id' => $loan->id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'online',
        'payment_gateway' => 'xendit',
        'amount' => 1000,
        'penalty_amount' => 0,
        'status' => 'rejected',
        'rejection_reason' => 'Xendit payment session expired or was canceled.',
        'transaction_date' => now(),
        'recorded_by' => $member->id,
    ]);
    DB::table('loan_payment_installment_allocations')->insert([
        'transaction_id' => $abandonedPayment->id,
        'period' => 1,
        'amount' => 1000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $newPayment = Transaction::make([
        'loan_id' => $loan->id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'online',
        'payment_gateway' => 'xendit',
        'amount' => 1000,
        'penalty_amount' => 0,
        'status' => 'pending',
        'transaction_date' => now(),
        'recorded_by' => $member->id,
    ]);

    app(LoanPaymentAllocationService::class)->validate(
        $loan->fresh(),
        $newPayment,
        [['period' => 1, 'amount' => 1000]],
        0
    );

    expect(true)->toBeTrue();
});

test('a provider-confirmed Xendit transaction cannot be retried or expired locally', function () {
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 12000,
        'interest_rate' => 0,
        'term_months' => 12,
        'loan_status' => 'active',
        'disbursed_at' => now()->subMonths(3),
        'penalty_amount' => 0,
    ]);
    $transaction = Transaction::create([
        'loan_id' => $loan->id,
        'branch_id' => $member->branch_id,
        'client_request_id' => 'xendit-provider-confirmed-001',
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'online',
        'payment_gateway' => 'xendit',
        'amount' => 1000,
        'penalty_amount' => 0,
        'status' => 'pending',
        'xendit_session_id' => 'session-provider-confirmed',
        'xendit_payment_id' => 'payment-provider-confirmed',
        'transaction_date' => now(),
        'recorded_by' => $member->id,
    ]);
    DB::table('loan_payment_installment_allocations')->insert([
        'transaction_id' => $transaction->id,
        'period' => 1,
        'amount' => 1000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->partialMock(XenditService::class, function ($mock) {
        $mock->shouldReceive('isEnabled')->andReturnTrue();
        $mock->shouldReceive('isConfigured')->andReturnTrue();
    });

    $this->actingAs($member)->postJson('/api/services/xendit/loan-payment/checkout', [
        'service_type' => 'loan_payment',
        'loan_id' => $loan->id,
        'member_id' => $member->id,
        'amount' => 1000,
        'installment_allocations' => [['period' => 1, 'amount' => 1000]],
        'penalty_amount' => 0,
        'client_request_id' => 'xendit-provider-confirmed-001',
    ])->assertStatus(409);

    app(XenditService::class)->processWebhook([
        'event' => 'payment_session.expired',
        'data' => ['payment_session_id' => 'session-provider-confirmed'],
    ]);

    expect($transaction->fresh()->status)->toBe('pending')
        ->and($transaction->fresh()->xendit_payment_id)->toBe('payment-provider-confirmed');
});

test('daily penalties continue while an installment remains partially unpaid and stop when settled', function () {
    $this->travelTo(Carbon::parse('2026-10-22 12:00:00'));
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 12000,
        'interest_rate' => 0,
        'term_months' => 12,
        'loan_status' => 'active',
        'disbursed_at' => Carbon::parse('2026-09-20 12:00:00'),
        'penalty_type' => 'flat',
        'penalty_amount' => 10,
        'penalty_frequency' => 'daily',
    ]);

    $partialPayment = Transaction::create([
        'loan_id' => $loan->id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'counter',
        'amount' => 520,
        'penalty_amount' => 20,
        'status' => 'completed',
        'transaction_date' => now(),
        'recorded_by' => $member->id,
    ]);
    DB::table('loan_payment_installment_allocations')->insert([
        'transaction_id' => $partialPayment->id,
        'period' => 1,
        'amount' => 500,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $schedule = $loan->fresh()->paymentSchedule();
    expect($schedule[0]['amount_due'])->toBe(500.0)
        ->and($schedule[0]['penalty'])->toBe(0.0);

    $this->travel(1)->day();
    expect($loan->fresh()->paymentSchedule()[0]['penalty'])->toBe(10.0)
        ->and($loan->fresh()->accruedPenalty())->toBe(10.0);

    $finalPayment = Transaction::create([
        'loan_id' => $loan->id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'counter',
        'amount' => 500,
        'penalty_amount' => 0,
        'status' => 'completed',
        'transaction_date' => now(),
        'recorded_by' => $member->id,
    ]);
    DB::table('loan_payment_installment_allocations')->insert([
        'transaction_id' => $finalPayment->id,
        'period' => 1,
        'amount' => 500,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($loan->fresh()->paymentSchedule()[0]['amount_due'])->toBe(0.0)
        ->and($loan->fresh()->paymentSchedule()[0]['penalty'])->toBe(0.0);
});

test('enabled weekly interest extension adds an interest-only installment for each overdue week', function () {
    $this->travelTo(Carbon::parse('2026-10-22 12:00:00'));
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 12000,
        'interest_rate' => 3,
        'term_months' => 1,
        'payment_frequency' => 'weekly',
        'loan_status' => 'active',
        'disbursed_at' => Carbon::parse('2026-09-01 12:00:00'),
        'interest_extension_penalty' => true,
        'penalty_amount' => 0,
    ]);

    $schedule = $loan->paymentSchedule();
    expect($schedule)->toHaveCount(4)
        ->and($schedule[1]['due_date'])->toBe('Oct 08, 2026')
        ->and($schedule[1]['payment'])->toEqual(360.0)
        ->and($schedule[1]['principal'])->toEqual(0.0)
        ->and($schedule[2]['due_date'])->toBe('Oct 15, 2026')
        ->and($schedule[3]['due_date'])->toBe('Oct 22, 2026')
        ->and($loan->remainingBalance())->toEqual(13440.0);

    $payment = Transaction::create([
        'loan_id' => $loan->id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'counter',
        'amount' => 360,
        'penalty_amount' => 0,
        'status' => 'completed',
        'transaction_date' => now(),
        'recorded_by' => $member->id,
    ]);
    DB::table('loan_payment_installment_allocations')->insert([
        'transaction_id' => $payment->id,
        'period' => 2,
        'amount' => 360,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($loan->fresh()->paymentSchedule()[1]['amount_due'])->toEqual(0.0)
        ->and($loan->fresh()->remainingBalance())->toEqual(13080.0);
});

test('ledger marks payments allocated to post-term interest installments', function () {
    $this->travelTo(Carbon::parse('2026-10-22 12:00:00'));
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 12000,
        'interest_rate' => 3,
        'term_months' => 1,
        'payment_frequency' => 'weekly',
        'loan_status' => 'active',
        'disbursed_at' => Carbon::parse('2026-09-01 12:00:00'),
        'interest_extension_penalty' => true,
        'penalty_amount' => 0,
    ]);
    $payment = Transaction::create([
        'loan_id' => $loan->id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'counter',
        'amount' => 360,
        'penalty_amount' => 0,
        'status' => 'completed',
        'transaction_date' => now(),
        'recorded_by' => $member->id,
    ]);
    DB::table('loan_payment_installment_allocations')->insert([
        'transaction_id' => $payment->id,
        'period' => 2,
        'amount' => 360,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $accountant = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();

    $this->actingAs($accountant)->getJson('/api/ledger?category=loan_payment')
        ->assertOk()
        ->assertJsonPath('data.0.loan_number', $loan->loan_number)
        ->assertJsonPath('data.0.installment_allocations.0.period', 2)
        ->assertJsonPath('data.0.installment_allocations.0.is_interest_extension', true);
});

test('member can submit a partial external payment for a selected installment without savings debit', function () {
    $member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $account = Account::where('user_id', $member->id)->where('account_type', 'savings')->firstOrFail();
    $startingBalance = (float) $account->balance;
    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 12000,
        'interest_rate' => 0,
        'term_months' => 12,
        'loan_status' => 'active',
        'disbursed_at' => now()->subMonths(2),
        'penalty_amount' => 0,
    ]);

    $response = $this->actingAs($member)->postJson('/api/services/loan-payment', [
        'loan_id' => $loan->id,
        'amount' => 125.50,
        'transaction_mode' => 'counter',
        'installment_allocations' => [['period' => 1, 'amount' => 125.50]],
        'penalty_amount' => 0,
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.account_id', null);

    $payment = Transaction::findOrFail($response->json('data.id'));
    $manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->actingAs($manager)->postJson("/api/services/approve/{$payment->id}", ['action' => 'approve'])->assertOk();
    $schedule = $loan->fresh()->paymentSchedule();
    expect((float) $account->fresh()->balance)->toBe($startingBalance)
        ->and(DB::table('loan_payment_installment_allocations')->where('transaction_id', $payment->id)->value('period'))->toBe(1)
        ->and((float) $schedule[0]['amount_due'])->toBe(874.5)
        ->and((float) $schedule[1]['amount_due'])->toBe(1000.0);
});
