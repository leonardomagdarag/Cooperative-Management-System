<?php

use App\Models\Account;
use App\Models\Loan;
use App\Models\MemberApplication;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LoanPaymentAllocationService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->accountant = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $this->accountant = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $this->borrower = User::where('user_number', 'USR000007')->firstOrFail();
});

test('member application approval creates zero-balance savings and share-capital accounts', function () {
    Mail::fake();

    $applicant = User::factory()->create([
        'account_status' => 'pending',
        'is_officer' => false,
    ]);

    MemberApplication::create([
        'user_id' => $applicant->id,
        'status' => 'pending',
        'branch_id' => $this->manager->branch_id,
        'original_email' => $applicant->email,
        'first_name' => 'Maria',
        'last_name' => 'Reyes',
        'phone' => '09218887766',
        'membership_type' => 'Regular',
    ]);

    $response = $this->actingAs($this->accountant)->postJson("/api/members/{$applicant->id}/approve");
    $response->assertOk();

    $savings = Account::where('user_id', $applicant->id)->where('account_type', 'savings')->first();
    $shareCapital = Account::where('user_id', $applicant->id)->where('account_type', 'share-capital')->first();

    expect($savings)->not->toBeNull()
        ->and((float) $savings->balance)->toBe(0.0)
        ->and($shareCapital)->not->toBeNull()
        ->and((float) $shareCapital->balance)->toBe(0.0);
});

test('balance sheet excludes interest credits from cash inflows and reflects cash disbursements accurately', function () {
    $this->admin->givePermissionTo('reports.view');
    $this->admin->givePermissionTo('reports.financial');

    $member = User::factory()->create(['branch_id' => $this->manager->branch_id]);
    $account = Account::factory()->create(['user_id' => $member->id, 'account_type' => 'savings', 'balance' => 1000]);

    $date = now()->toDateString();

    // 1. Post an interest credit (internal credit to deposit liability, NOT cash in)
    Transaction::create([
        'account_id' => $account->id,
        'branch_id' => $this->manager->branch_id,
        'transaction_type' => 'interest',
        'transaction_mode' => 'system',
        'amount' => 75.00,
        'status' => 'completed',
        'transaction_date' => $date,
    ]);

    // 2. Post a cash deposit (actual cash inflow)
    Transaction::create([
        'account_id' => $account->id,
        'branch_id' => $this->manager->branch_id,
        'transaction_type' => 'deposit',
        'transaction_mode' => 'counter',
        'amount' => 500.00,
        'status' => 'completed',
        'transaction_date' => $date,
    ]);

    $response = $this->actingAs($this->admin)->getJson("/api/reports/balance-sheet?from={$date}&to={$date}");
    $response->assertOk();

    // Cash inflow should only reflect the ₱500 deposit, NOT the ₱75 interest credit
    expect((float) $response->json('period_movement.inflows'))->toBe(500.0);
});

test('loan payment allocation allows advance installment payments in sequential order', function () {
    $loan = Loan::create([
        'user_id' => $this->borrower->id,
        'branch_id' => $this->manager->branch_id,
        'loan_product' => 'business',
        'loan_type' => 'business',
        'loan_status' => 'active',
        'loan_amount' => 12000.00,
        'interest_rate' => 2.0, // 2% per month
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'disbursed_at' => now()->startOfDay(), // Disbursed today, installments are in the future
        'applied_at' => now(),
    ]);

    $tx = Transaction::create([
        'loan_id' => $loan->id,
        'branch_id' => $this->manager->branch_id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'counter',
        'amount' => 2240.00, // Period 1 total payment (2000 principal + 240 interest)
        'status' => 'pending',
        'transaction_date' => now(),
    ]);

    $service = app(LoanPaymentAllocationService::class);

    // Period 1 is due next month, but paying in advance should succeed without 422
    $service->record($tx, [['period' => 1, 'amount' => 2240.00]], 0.0);

    $this->assertDatabaseHas('loan_payment_installment_allocations', [
        'transaction_id' => $tx->id,
        'period' => 1,
        'amount' => 2240.00,
    ]);
});

test('loan payment allocation rejects skipping earlier unpaid installments', function () {
    $loan = Loan::create([
        'user_id' => $this->borrower->id,
        'branch_id' => $this->manager->branch_id,
        'loan_product' => 'business',
        'loan_type' => 'business',
        'loan_status' => 'active',
        'loan_amount' => 12000.00,
        'interest_rate' => 2.0,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'disbursed_at' => now()->startOfDay(),
        'applied_at' => now(),
    ]);

    $tx = Transaction::create([
        'loan_id' => $loan->id,
        'branch_id' => $this->manager->branch_id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'counter',
        'amount' => 2240.00,
        'status' => 'pending',
        'transaction_date' => now(),
    ]);

    $service = app(LoanPaymentAllocationService::class);

    // Trying to pay Period 2 while Period 1 is unpaid must throw 422
    expect(fn () => $service->record($tx, [['period' => 2, 'amount' => 2240.00]], 0.0))
        ->toThrow(HttpException::class);
});

test('approving officer cannot disburse their own approved loan', function () {
    $loan = Loan::create([
        'user_id' => $this->borrower->id,
        'branch_id' => $this->manager->branch_id,
        'loan_product' => 'business',
        'loan_type' => 'business',
        'loan_status' => 'approved',
        'loan_amount' => 10000.00,
        'interest_rate' => 2.0,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'applied_at' => now(),
        'approved_at' => now(),
        'approved_by' => $this->manager->id,
    ]);

    $this->manager->givePermissionTo('loans.disburse');

    // Manager who approved the loan is forbidden from disbursing it
    $this->actingAs($this->manager)
        ->postJson("/api/loans/{$loan->id}/disburse", ['destination' => 'cash'])
        ->assertForbidden();

    // A different officer with loans.disburse CAN disburse it
    $this->accountant->givePermissionTo('loans.disburse');
    $this->actingAs($this->accountant)
        ->postJson("/api/loans/{$loan->id}/disburse", ['destination' => 'cash'])
        ->assertOk();

    expect($loan->fresh()->loan_status)->toBe('active');
});
