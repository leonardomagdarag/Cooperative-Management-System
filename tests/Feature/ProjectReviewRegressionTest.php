<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    config(['permission.cache.store' => 'array']);
    app(PermissionRegistrar::class)->initializeCache();
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(SystemSettingSeeder::class);
    $this->branch = Branch::create(['code' => 'BR900001', 'name' => 'Review Test Branch', 'is_active' => true]);
    $this->member = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->member->assignRole('member');
});

test('borrowers cannot change approved or disbursed loan terms', function () {
    $loan = Loan::factory()->active()->create([
        'user_id' => $this->member->id,
        'branch_id' => $this->branch->id,
        'loan_amount' => 10000,
        'interest_rate' => 3,
    ]);

    $this->actingAs($this->member)->putJson("/api/loans/{$loan->id}", [
        'loan_amount' => 1,
        'interest_rate' => 0,
        'term_months' => 1,
        'payment_frequency' => 'monthly',
    ])->assertForbidden();

    expect((float) $loan->fresh()->loan_amount)->toBe(10000.0)
        ->and((float) $loan->fresh()->interest_rate)->toBe(3.0);
});

test('borrowers can edit their own pending unreviewed loan application', function () {
    $loan = Loan::factory()->pending()->create([
        'user_id' => $this->member->id,
        'branch_id' => $this->branch->id,
        'loan_amount' => 10000,
    ]);

    $this->actingAs($this->member)->putJson("/api/loans/{$loan->id}", [
        'loan_amount' => 12000,
        'interest_rate' => 3,
        'term_months' => 12,
        'payment_frequency' => 'monthly',
    ])->assertOk();

    expect((float) $loan->fresh()->loan_amount)->toBe(12000.0);
});

test('legacy profile and password mutation routes are no longer available', function () {
    $this->actingAs($this->member);

    $this->patch('/profile', ['name' => 'Changed Name', 'email' => 'changed@example.test'])->assertMethodNotAllowed();
    $this->delete('/profile', ['password' => 'password'])->assertMethodNotAllowed();
    $this->put('/password', [
        'current_password' => 'password',
        'password' => 'Changed-password123',
        'password_confirmation' => 'Changed-password123',
    ])->assertMethodNotAllowed();

    expect($this->member->fresh()->email)->not->toBe('changed@example.test')
        ->and(Hash::check('password', $this->member->fresh()->password))->toBeTrue();
});

test('retrying another members request id returns conflict without their transaction', function () {
    $other = User::factory()->create(['branch_id' => $this->branch->id]);
    $account = Account::factory()->savings()->create(['user_id' => $other->id]);
    $transaction = Transaction::create([
        'account_id' => $account->id,
        'transaction_type' => 'deposit',
        'amount' => 1234,
        'status' => 'completed',
        'transaction_mode' => 'counter',
        'client_request_id' => 'review-cross-member-request',
        'recorded_by' => $other->id,
        'note' => 'private note',
    ]);

    $this->actingAs($this->member)->postJson('/api/services/deposit', [
        'amount' => 100,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
        'client_request_id' => 'review-cross-member-request',
    ])->assertConflict()
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.note');

    expect($transaction->fresh())->not->toBeNull();
});

test('same member retry is idempotent but cannot change the original amount', function () {
    $account = Account::factory()->savings()->create(['user_id' => $this->member->id]);
    $transaction = Transaction::create([
        'account_id' => $account->id,
        'transaction_type' => 'deposit',
        'amount' => 500,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'client_request_id' => 'review-own-request-id',
        'recorded_by' => $this->member->id,
    ]);
    $payload = [
        'amount' => 500,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
        'client_request_id' => 'review-own-request-id',
    ];

    $this->actingAs($this->member)->postJson('/api/services/deposit', $payload)
        ->assertOk()->assertJsonPath('data.id', $transaction->id);

    $this->postJson('/api/services/deposit', array_merge($payload, ['amount' => 700]))
        ->assertConflict()->assertJsonMissingPath('data.id');
});

test('legacy profile deletion cannot hard delete a member with an account', function () {
    $account = Account::factory()->savings()->create(['user_id' => $this->member->id]);

    $this->actingAs($this->member)->deleteJson('/profile', ['password' => 'password'])->assertMethodNotAllowed();

    expect(User::withTrashed()->find($this->member->id))->not->toBeNull()
        ->and($account->fresh())->not->toBeNull();
});
