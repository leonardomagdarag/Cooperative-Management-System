<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();

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

test('ledger store deposit credits account balance', function () {
    $this->actingAs($this->admin);

    $response = $this->postJson('/api/ledger', [
        'transaction_type' => 'deposit',
        'amount' => 500.00,
        'note' => 'Manual counter deposit',
        'account_id' => $this->account->id,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.transaction_type', 'deposit')
        ->assertJsonPath('data.amount', '500.00');

    expect($this->account->fresh()->balance)->toBe('1500.00');

    $transaction = Transaction::findOrFail($response->json('data.id'));
    expect($transaction->recorded_by)->toBe($this->admin->id)
        ->and($transaction->account_id)->toBe($this->account->id);
});

test('ledger store withdrawal debits account balance and rejects insufficient funds', function () {
    $this->actingAs($this->admin);

    // Attempt withdrawal greater than balance
    $failResponse = $this->postJson('/api/ledger', [
        'transaction_type' => 'withdrawal',
        'amount' => 1500.00,
        'note' => 'Excessive withdrawal',
        'account_id' => $this->account->id,
    ]);

    $failResponse->assertStatus(422);
    expect($this->account->fresh()->balance)->toBe('1000.00');

    // Successful withdrawal
    $successResponse = $this->postJson('/api/ledger', [
        'transaction_type' => 'withdrawal',
        'amount' => 400.00,
        'note' => 'Valid withdrawal',
        'account_id' => $this->account->id,
    ]);

    $successResponse->assertStatus(201);
    expect($this->account->fresh()->balance)->toBe('600.00');
});

test('ledger update adjusts account balance by the transaction delta', function () {
    $this->actingAs($this->admin);

    // Initial deposit of 500: balance becomes 1000 + 500 = 1500
    $createResponse = $this->postJson('/api/ledger', [
        'transaction_type' => 'deposit',
        'amount' => 500.00,
        'account_id' => $this->account->id,
    ]);
    $transactionId = $createResponse->json('data.id');
    expect($this->account->fresh()->balance)->toBe('1500.00');

    // Update deposit amount to 800: balance should become 1000 + 800 = 1800
    $this->putJson("/api/ledger/{$transactionId}", [
        'transaction_type' => 'deposit',
        'amount' => 800.00,
        'account_id' => $this->account->id,
    ])->assertOk();

    expect($this->account->fresh()->balance)->toBe('1800.00');

    // Update deposit amount down to 200: balance should become 1000 + 200 = 1200
    $this->putJson("/api/ledger/{$transactionId}", [
        'transaction_type' => 'deposit',
        'amount' => 200.00,
        'account_id' => $this->account->id,
    ])->assertOk();

    expect($this->account->fresh()->balance)->toBe('1200.00');
});

test('ledger destroy reverses balance effect on the linked account', function () {
    $this->actingAs($this->admin);

    // Create deposit of 300: balance becomes 1300
    $createResponse = $this->postJson('/api/ledger', [
        'transaction_type' => 'deposit',
        'amount' => 300.00,
        'account_id' => $this->account->id,
    ]);
    $transactionId = $createResponse->json('data.id');
    expect($this->account->fresh()->balance)->toBe('1300.00');

    // Delete transaction: balance should revert to 1000
    $this->deleteJson("/api/ledger/{$transactionId}")->assertOk();

    expect($this->account->fresh()->balance)->toBe('1000.00');
    $this->assertDatabaseMissing('transactions', ['id' => $transactionId]);
});

test('multiple ledger operations accurately reconcile account balance', function () {
    $this->actingAs($this->admin);

    // Initial balance: 1000
    // Deposit 500 -> 1500
    $this->postJson('/api/ledger', [
        'transaction_type' => 'deposit',
        'amount' => 500.00,
        'account_id' => $this->account->id,
    ])->assertStatus(201);

    // Withdrawal 200 -> 1300
    $this->postJson('/api/ledger', [
        'transaction_type' => 'withdrawal',
        'amount' => 200.00,
        'account_id' => $this->account->id,
    ])->assertStatus(201);

    // Deposit 100 -> 1400
    $this->postJson('/api/ledger', [
        'transaction_type' => 'deposit',
        'amount' => 100.00,
        'account_id' => $this->account->id,
    ])->assertStatus(201);

    expect((float) $this->account->fresh()->balance)->toBe(1400.00);
});
