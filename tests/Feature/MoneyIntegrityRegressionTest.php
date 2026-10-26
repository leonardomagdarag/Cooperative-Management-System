<?php

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
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
