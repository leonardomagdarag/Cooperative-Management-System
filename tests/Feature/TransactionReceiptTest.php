<?php

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\TransactionReceipt;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->accountant = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();

    $this->member = User::factory()->create();
    $this->member->assignRole('member');

    $this->account = Account::factory()->create([
        'user_id' => $this->member->id,
        'account_type' => 'savings',
        'balance' => 1000.00,
    ]);

    $this->loan = Loan::factory()->active()->create([
        'user_id' => $this->member->id,
        'account_id' => $this->account->id,
        'branch_id' => $this->manager->branch_id,
    ]);
});

// Collection-run payments post with a loan_id but no account_id; receipts used to
// 500 on the NOT NULL issued_to_member_id column because the member was only ever
// read off the (missing) account.
function collectionRunPayment(User $recordedBy, Loan $loan): Transaction
{
    return Transaction::create([
        'transaction_type' => 'loan_payment',
        'amount' => 150.00,
        'loan_id' => $loan->id,
        'status' => 'completed',
        'recorded_by' => $recordedBy->id,
        'branch_id' => $recordedBy->branch_id,
        'transaction_date' => now(),
    ]);
}

test('reprint of a loan-only payment resolves the member through the loan', function () {
    $payment = collectionRunPayment($this->manager, $this->loan);

    $this->actingAs($this->manager)
        ->postJson("/api/transactions/{$payment->id}/receipts/reprint")
        ->assertOk()
        ->assertJsonPath('data.reason', 'reprint')
        ->assertJsonPath('data.certified', false)
        ->assertJsonPath('data.transaction.transaction_number', $payment->transaction_number)
        ->assertJsonPath('data.transaction.type', 'loan_payment')
        ->assertJsonPath('data.transaction.amount', '150.00')
        ->assertJsonPath('data.member.name', $this->member->name)
        ->assertJsonPath('data.member.member_number', $this->member->user_number)
        ->assertJsonPath('data.member.account_number', null)
        ->assertJsonPath('data.issued_by', $this->manager->name)
        ->assertJsonPath('data.cooperative', config('app.name'));

    $receipt = TransactionReceipt::latest('id')->first();
    expect($receipt)->not->toBeNull()
        ->and($receipt->transaction_id)->toBe($payment->id)
        ->and($receipt->issued_by)->toBe($this->manager->id)
        ->and($receipt->issued_to_member_id)->toBe($this->member->id);
});

test('reprint of an account-backed transaction resolves the member through the account', function () {
    $deposit = Transaction::create([
        'transaction_type' => 'deposit',
        'amount' => 500.00,
        'account_id' => $this->account->id,
        'status' => 'completed',
        'recorded_by' => $this->manager->id,
        'branch_id' => $this->manager->branch_id,
        'transaction_date' => now(),
    ]);

    $this->actingAs($this->manager)
        ->postJson("/api/transactions/{$deposit->id}/receipts/reprint")
        ->assertOk()
        ->assertJsonPath('data.member.name', $this->member->name)
        ->assertJsonPath('data.member.account_number', $this->account->account_number)
        ->assertJsonPath('data.transaction.type', 'deposit')
        ->assertJsonPath('data.transaction.branch', $this->manager->branch?->name);
});

test('reissue of a loan-only payment records a certified replacement for the member', function () {
    $payment = collectionRunPayment($this->manager, $this->loan);

    $this->actingAs($this->manager)
        ->postJson("/api/transactions/{$payment->id}/receipts/reissue", ['note' => 'Member reported a lost receipt'])
        ->assertOk()
        ->assertJsonPath('data.reason', 'reissue')
        ->assertJsonPath('data.certified', true)
        ->assertJsonPath('data.note', 'Member reported a lost receipt')
        ->assertJsonPath('data.transaction.loan_number', $this->loan->loan_number);

    $receipt = TransactionReceipt::latest('id')->first();
    expect($receipt->certified)->toBeTrue()
        ->and($receipt->note)->toBe('Member reported a lost receipt')
        ->and($receipt->issued_to_member_id)->toBe($this->member->id);
});

test('reissue requires a reason note', function () {
    $payment = collectionRunPayment($this->manager, $this->loan);

    $this->actingAs($this->manager)
        ->postJson("/api/transactions/{$payment->id}/receipts/reissue")
        ->assertUnprocessable();

    expect(TransactionReceipt::count())->toBe(0);
});

test('reissue requires the receipts reissue permission', function () {
    $payment = collectionRunPayment($this->accountant, $this->loan);

    $this->actingAs($this->accountant)
        ->postJson("/api/transactions/{$payment->id}/receipts/reissue", ['note' => 'Accountant issued replacement'])
        ->assertForbidden();

    expect(TransactionReceipt::count())->toBe(0);
});

test('a member can reprint their own loan-only payment but not another member payment', function () {
    $payment = collectionRunPayment($this->manager, $this->loan);

    $this->actingAs($this->member)
        ->postJson("/api/transactions/{$payment->id}/receipts/reprint")
        ->assertOk();

    expect(TransactionReceipt::latest('id')->first()->issued_to_member_id)->toBe($this->member->id);

    $otherMember = User::factory()->create();
    $otherMember->assignRole('member');

    $this->actingAs($otherMember)
        ->postJson("/api/transactions/{$payment->id}/receipts/reprint")
        ->assertForbidden();
});

test('receipts are not issued for uncompleted transactions', function () {
    $payment = Transaction::create([
        'transaction_type' => 'loan_payment',
        'amount' => 75.00,
        'loan_id' => $this->loan->id,
        'status' => 'pending',
        'recorded_by' => $this->manager->id,
        'branch_id' => $this->manager->branch_id,
        'transaction_date' => now(),
    ]);

    $this->actingAs($this->manager)
        ->postJson("/api/transactions/{$payment->id}/receipts/reprint")
        ->assertStatus(422);

    expect(TransactionReceipt::count())->toBe(0);
});
