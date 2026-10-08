<?php

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SecurityOtpService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->manager = User::where('email', 'manager@example.com')->firstOrFail();

    $this->member = User::factory()->create();
    $this->member->assignRole('member');

    $this->account = Account::factory()->create([
        'user_id' => $this->member->id,
        'account_type' => 'savings',
        'balance' => 1000.00,
    ]);
});

test('users without transactions permissions cannot access ledger api', function () {
    $unauthorizedUser = User::factory()->create();

    $this->actingAs($unauthorizedUser);
    $this->getJson('/api/ledger')->assertForbidden();

    $this->actingAs($this->member);
    $this->postJson('/api/ledger', [
        'transaction_type' => 'deposit',
        'amount' => 500,
        'account_id' => $this->account->id,
    ])->assertForbidden();
});

test('ledger store creates pending transaction without affecting balance', function () {
    $this->actingAs($this->manager);

    $response = $this->postJson('/api/ledger', [
        'transaction_type' => 'deposit',
        'amount' => 500.00,
        'note' => 'Manual counter deposit',
        'account_id' => $this->account->id,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.transaction_type', 'deposit')
        ->assertJsonPath('data.amount', '500.00');

    expect($this->account->fresh()->balance)->toBe('1000.00'); // No change

    $transaction = Transaction::findOrFail($response->json('data.id'));
    expect($transaction->status)->toBe('pending')
        ->and($transaction->recorded_by)->toBe($this->manager->id)
        ->and($transaction->account_id)->toBe($this->account->id);
});

test('ledger update modifies pending transaction without affecting balance', function () {
    $this->actingAs($this->manager);

    $createResponse = $this->postJson('/api/ledger', [
        'transaction_type' => 'deposit',
        'amount' => 500.00,
        'account_id' => $this->account->id,
    ]);
    $transactionId = $createResponse->json('data.id');
    expect($this->account->fresh()->balance)->toBe('1000.00');

    $this->putJson("/api/ledger/{$transactionId}", [
        'transaction_type' => 'deposit',
        'amount' => 800.00,
        'account_id' => $this->account->id,
    ])->assertOk();

    expect($this->account->fresh()->balance)->toBe('1000.00');
    expect(Transaction::find($transactionId)->amount)->toBe('800.00');
});

test('ledger update rejects modifying non-pending transaction', function () {
    $this->actingAs($this->manager);

    $createResponse = $this->postJson('/api/ledger', [
        'transaction_type' => 'deposit',
        'amount' => 500.00,
        'account_id' => $this->account->id,
    ]);
    $transactionId = $createResponse->json('data.id');

    $transaction = Transaction::find($transactionId);
    $transaction->update(['status' => 'completed']);

    $this->putJson("/api/ledger/{$transactionId}", [
        'transaction_type' => 'deposit',
        'amount' => 800.00,
        'account_id' => $this->account->id,
    ])->assertStatus(409)
    ->assertJson(['message' => 'Only pending transactions can be edited.']);
});

test('reversing a completed ledger entry posts one linked correction and preserves the original', function () {
    $this->actingAs($this->manager);
    $this->account->update(['balance' => 1500.00]);

    $original = Transaction::create([
        'transaction_type' => 'deposit',
        'amount' => 500.00,
        'account_id' => $this->account->id,
        'status' => 'completed',
        'recorded_by' => $this->manager->id,
        'branch_id' => $this->manager->branch_id,
        'transaction_date' => now(),
    ]);

    $this->postJson("/api/ledger/{$original->id}/reverse")->assertUnprocessable();
    $this->postJson("/api/ledger/{$original->id}/reverse/send-otp")->assertOk();
    $securityOtp = app(SecurityOtpService::class);
    $challenge = $securityOtp->issueChallenge($this->manager, $securityOtp->ledgerReversalPurpose(), ['transaction_id' => $original->id]);
    $response = $this->postJson("/api/ledger/{$original->id}/reverse", ['otp_code' => $challenge['otp_code']]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.reversal_of_id', $original->id);
    expect($this->account->fresh()->balance)->toBe('1000.00')
        ->and($original->fresh()->status)->toBe('completed')
        ->and($original->fresh()->reversal)->not->toBeNull();

    $this->postJson("/api/ledger/{$original->id}/reverse")->assertStatus(409);
    expect(Transaction::where('reversal_of_id', $original->id)->count())->toBe(1);
});

test('reversing a completed loan payment restores savings and nets the payment out of loan balance', function () {
    $this->actingAs($this->manager);
    $loan = Loan::factory()->active()->create([
        'user_id' => $this->member->id,
        'branch_id' => $this->manager->branch_id,
    ]);
    $loan->update(['account_id' => $this->account->id]);
    $this->account->update(['balance' => 900.00]);

    $payment = Transaction::create([
        'transaction_type' => 'loan_payment',
        'amount' => 100.00,
        'account_id' => $this->account->id,
        'loan_id' => $loan->id,
        'status' => 'completed',
        'recorded_by' => $this->manager->id,
        'branch_id' => $this->manager->branch_id,
        'transaction_date' => now(),
    ]);
    $remainingBeforePayment = $loan->totalPayable();

    $securityOtp = app(SecurityOtpService::class);
    $challenge = $securityOtp->issueChallenge($this->manager, $securityOtp->ledgerReversalPurpose(), ['transaction_id' => $payment->id]);
    $this->postJson("/api/ledger/{$payment->id}/reverse", ['otp_code' => $challenge['otp_code']])->assertOk();

    expect((float) $this->account->fresh()->balance)->toBe(1000.0)
        ->and((float) $loan->fresh()->totalPaid())->toBe(0.0)
        ->and((float) $loan->fresh()->remainingBalance())->toBe((float) $remainingBeforePayment)
        ->and($payment->fresh()->status)->toBe('completed');
});

test('ledger destroy deletes pending transaction without affecting balance', function () {
    $this->actingAs($this->manager);

    $createResponse = $this->postJson('/api/ledger', [
        'transaction_type' => 'deposit',
        'amount' => 300.00,
        'account_id' => $this->account->id,
    ]);
    $transactionId = $createResponse->json('data.id');
    expect($this->account->fresh()->balance)->toBe('1000.00');

    $this->deleteJson("/api/ledger/{$transactionId}")->assertOk();

    expect($this->account->fresh()->balance)->toBe('1000.00');
    $this->assertDatabaseMissing('transactions', ['id' => $transactionId]);
});

test('ledger destroy rejects deleting non-pending transaction', function () {
    $this->actingAs($this->manager);

    $createResponse = $this->postJson('/api/ledger', [
        'transaction_type' => 'deposit',
        'amount' => 300.00,
        'account_id' => $this->account->id,
    ]);
    $transactionId = $createResponse->json('data.id');

    $transaction = Transaction::find($transactionId);
    $transaction->update(['status' => 'completed']);

    $this->deleteJson("/api/ledger/{$transactionId}")->assertStatus(409)
    ->assertJson(['message' => 'Only pending transactions can be deleted.']);
});
