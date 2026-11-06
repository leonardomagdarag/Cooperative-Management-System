<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\OfficerPermissionOverride;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SecurityOtpService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(AccountSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->accountant = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $this->member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();

    $this->mainBranch = Branch::where('code', 'BR000001')->firstOrFail();
    $this->officeBranch = Branch::where('code', 'BR000002')->firstOrFail();

    $this->memberAccount = Account::where('user_id', $this->member->id)->firstOrFail();
});

test('F023-1: unauthenticated requests to pending queue, approval, and OTP return 401', function () {
    $this->getJson('/api/services/pending')->assertUnauthorized();
    $this->postJson('/api/services/approve/1', ['action' => 'approve'])->assertUnauthorized();
    $this->postJson('/api/services/transactions/1/send-otp')->assertUnauthorized();
});

test('F023-2: regular members receive 403 when accessing pending queue or approval', function () {
    $this->actingAs($this->member);

    $transaction = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'deposit',
        'amount' => 500,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $this->getJson('/api/services/pending')->assertForbidden();
    $this->postJson("/api/services/approve/{$transaction->id}", ['action' => 'approve'])->assertForbidden();
    $this->postJson("/api/services/transactions/{$transaction->id}/send-otp")->assertForbidden();
});

test('F023-3: accountant without approval permissions receives 403 on pending queue and approval', function () {
    $this->actingAs($this->accountant);

    $transaction = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'deposit',
        'amount' => 500,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $this->getJson('/api/services/pending')->assertForbidden();
    $this->postJson("/api/services/approve/{$transaction->id}", ['action' => 'approve'])->assertForbidden();
});

test('F023-4: manager can view pending transactions filtered by branch', function () {
    $tMain = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'deposit',
        'amount' => 1250,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $response = $this->actingAs($this->manager)->getJson('/api/services/pending');
    $response->assertOk()
        ->assertJsonStructure(['data']);

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($tMain->id);
});

test('F023-5: manager approves pending deposit, incrementing account balance and recording approval', function () {
    $initialBalance = (float) $this->memberAccount->balance;
    $depositAmount = 750.00;

    $transaction = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'deposit',
        'amount' => $depositAmount,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Transaction approved.']);

    $transaction->refresh();
    expect($transaction->status)->toBe('completed')
        ->and($transaction->approved_by)->toBe($this->manager->id)
        ->and($transaction->approved_at)->not->toBeNull();

    $this->memberAccount->refresh();
    expect((float) $this->memberAccount->balance)->toBe($initialBalance + $depositAmount);
});

test('F023-6: manager rejects pending deposit with reason, leaving balance unchanged', function () {
    $initialBalance = (float) $this->memberAccount->balance;

    $transaction = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'deposit',
        'amount' => 1000.00,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'reject',
        'reason' => 'Invalid deposit reference voucher',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Transaction rejectd.']);

    $transaction->refresh();
    expect($transaction->status)->toBe('rejected')
        ->and($transaction->rejection_reason)->toBe('Invalid deposit reference voucher');

    $this->memberAccount->refresh();
    expect((float) $this->memberAccount->balance)->toBe($initialBalance);
});

test('F023-7: manager approves low-value withdrawal (< threshold) without requiring OTP code', function () {
    $this->memberAccount->update(['balance' => 5000.00]);
    $initialBalance = (float) $this->memberAccount->balance;
    $withdrawalAmount = 500.00; // < 2000 threshold

    $transaction = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'withdrawal',
        'amount' => $withdrawalAmount,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Transaction approved.']);

    $transaction->refresh();
    expect($transaction->status)->toBe('completed');

    $this->memberAccount->refresh();
    expect((float) $this->memberAccount->balance)->toBe($initialBalance - $withdrawalAmount);
});

test('F023-8: manager approves high-value withdrawal (>= threshold) with valid OTP verification', function () {
    Mail::fake();

    $this->memberAccount->update(['balance' => 10000.00]);
    $initialBalance = (float) $this->memberAccount->balance;
    $withdrawalAmount = 3500.00; // >= 2000 threshold

    $transaction = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'withdrawal',
        'amount' => $withdrawalAmount,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    // Attempting approval without OTP fails with 422
    $failResponse = $this->actingAs($this->manager)->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
    ]);
    $failResponse->assertStatus(422)
        ->assertJson(['message' => 'A verification code is required to approve this withdrawal.']);

    // Send OTP
    $otpResponse = $this->actingAs($this->manager)->postJson("/api/services/transactions/{$transaction->id}/send-otp");
    $otpResponse->assertOk()
        ->assertJsonStructure(['message', 'expires_at']);

    // Issue challenge directly via service for known code
    $securityOtp = app(SecurityOtpService::class);
    $challenge = $securityOtp->issueChallenge(
        $this->manager,
        $securityOtp->withdrawalApprovalPurpose(),
        ['transaction_id' => $transaction->id]
    );

    // Approve with issued code
    $approveResponse = $this->actingAs($this->manager)->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
        'otp_code' => $challenge['otp_code'],
    ]);

    $approveResponse->assertOk()
        ->assertJson(['message' => 'Transaction approved.']);

    $transaction->refresh();
    expect($transaction->status)->toBe('completed');

    $this->memberAccount->refresh();
    expect((float) $this->memberAccount->balance)->toBe($initialBalance - $withdrawalAmount);
});

test('F023-9: high-value withdrawal approval rejects invalid OTP code with 422', function () {
    $this->memberAccount->update(['balance' => 10000.00]);

    $transaction = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'withdrawal',
        'amount' => 4000.00,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
        'otp_code' => '999999',
    ]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'Invalid or expired verification code.']);
});

test('F023-10: withdrawal approval fails with 422 if account balance dropped below requested amount', function () {
    $this->memberAccount->update(['balance' => 100.00]); // Less than withdrawal amount

    $transaction = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'withdrawal',
        'amount' => 500.00,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
    ]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'Insufficient available balance to approve this transaction.']);
});

test('F023-11: officer holding branch bypass can approve a transaction from another branch', function () {
    // Set manager's branch to officeBranch
    $this->manager->update(['branch_id' => $this->officeBranch->id]);

    // Transaction is in mainBranch
    $transaction = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'deposit',
        'amount' => 500.00,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $this->memberAccount->update(['balance' => $before = 1000.00]);

    $response = $this->actingAs($this->manager)->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Transaction approved.']);

    $this->memberAccount->refresh();
    expect((float) $this->memberAccount->balance)->toBe($before + 500.0);
});

test('F023-12: approving or rejecting an already processed transaction returns 422', function () {
    $transaction = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'deposit',
        'amount' => 500.00,
        'status' => 'completed',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
    ]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'Transaction is not pending.']);
});

test('F023-13: loan payment approval decrements balance and updates loan to paid_off when remaining reaches zero', function () {
    $this->memberAccount->update(['balance' => 15000.00]);

    $product = LoanProduct::create([
        'code' => 'audit-test-prod',
        'name' => 'Audit Test Product',
        'base_type' => 'business',
        'interest_rate' => 0,
        'max_amount' => 50000,
        'max_term' => 12,
        'minimum_membership_years' => 0,
        'required_co_makers' => 0,
        'sort_order' => 1,
    ]);

    $loan = Loan::create([
        'user_id' => $this->member->id,
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_number' => 'LN-AUDIT-001',
        'loan_amount' => 2000.00,
        'interest_rate' => 0,
        'term_months' => 6,
        'loan_status' => 'active',
        'disbursed_at' => now()->subMonths(1),
        'applied_at' => now(),
    ]);

    $transaction = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'loan_id' => $loan->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'loan_payment',
        'amount' => 2000.00,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Transaction approved.']);

    $transaction->refresh();
    expect($transaction->status)->toBe('completed');

    $loan->refresh();
    expect($loan->loan_status)->toBe('paid_off');
});

test('F023-14: manager role can self-approve recorded transactions by default and can be disallowed via override', function () {
    $this->memberAccount->update(['balance' => 5000.00]);

    $transaction = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'deposit',
        'amount' => 500.00,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->manager->id,
    ]);

    // Manager can self-approve by default because manager preset includes transactions.approve-own
    $this->actingAs($this->manager)
        ->postJson("/api/services/approve/{$transaction->id}", ['action' => 'approve'])
        ->assertOk()
        ->assertJson(['message' => 'Transaction approved.']);

    expect($transaction->fresh()->status)->toBe('completed');

    // Create a second transaction to test disallow override
    $transaction2 = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'deposit',
        'amount' => 300.00,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->manager->id,
    ]);

    OfficerPermissionOverride::create([
        'user_id' => $this->manager->id,
        'permission' => 'transactions.approve-own',
        'effect' => OfficerPermissionOverride::EFFECT_DISALLOW,
    ]);
    OfficerPermissionOverride::forgetAllCachedEffects($this->manager->id);

    // With disallow override, manager cannot self-approve
    $this->actingAs($this->manager)
        ->postJson("/api/services/approve/{$transaction2->id}", ['action' => 'approve'])
        ->assertForbidden();
});

test('F023-15: officer can approve fee transaction with null account_id without crashing', function () {
    $transaction = Transaction::create([
        'account_id' => null,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'fee',
        'amount' => 150.00,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Transaction approved.']);

    expect($transaction->fresh()->status)->toBe('completed');
});

test('F023-16: officer can approve counter loan payment with null account_id and loan marks paid_off', function () {
    $product = LoanProduct::create([
        'code' => 'audit-test-prod-counter',
        'name' => 'Audit Test Product Counter',
        'base_type' => 'business',
        'interest_rate' => 0,
        'max_amount' => 50000,
        'max_term' => 12,
        'minimum_membership_years' => 0,
        'required_co_makers' => 0,
        'sort_order' => 1,
    ]);

    $loan = Loan::create([
        'user_id' => $this->member->id,
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_number' => 'LN-AUDIT-COUNTER-001',
        'loan_amount' => 1000.00,
        'interest_rate' => 0,
        'term_months' => 6,
        'loan_status' => 'active',
        'disbursed_at' => now()->subMonths(1),
        'applied_at' => now(),
    ]);

    // Counter cash loan payment without account deduction
    $transaction = Transaction::create([
        'account_id' => null,
        'loan_id' => $loan->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'loan_payment',
        'amount' => 1000.00,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Transaction approved.']);

    expect($transaction->fresh()->status)->toBe('completed');
    expect($loan->fresh()->loan_status)->toBe('paid_off');
});

test('F023-17: approving transaction on closed account is rejected with 422', function () {
    $transaction = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'deposit',
        'amount' => 500.00,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $this->memberAccount->delete(); // Soft-delete / close account

    $response = $this->actingAs($this->manager)->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
    ]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'Cannot process transaction on a closed account.']);
});
