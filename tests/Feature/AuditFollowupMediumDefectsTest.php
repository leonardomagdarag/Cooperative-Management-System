<?php

use App\Mail\LoanLifecycleNotification;
use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $this->otherMember = User::role('member')->whereKeyNot($this->member->id)->firstOrFail();
    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->account = Account::where('user_id', $this->member->id)->firstOrFail();
    $this->loan = Loan::factory()->active()->create([
        'user_id' => $this->member->id,
        'branch_id' => $this->member->branch_id,
    ]);
});

test('external loan payments appear in only the borrowers history and pending requests', function () {
    $payment = Transaction::create([
        'loan_id' => $this->loan->id,
        'account_id' => null,
        'branch_id' => $this->member->branch_id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'counter',
        'amount' => 125,
        'status' => 'pending',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $this->actingAs($this->member)->getJson('/api/history?status=pending')
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('data.0.id', $payment->id)
        ->assertJsonPath('data.0.balance_after', null);

    $this->actingAs($this->otherMember)->getJson('/api/history?status=pending')
        ->assertOk()
        ->assertJsonMissing(['id' => $payment->id]);

    expect((float) $this->account->fresh()->balance)->toBe((float) $this->account->balance);
});

test('approval queue identifies the borrower of an external loan payment', function () {
    $payment = Transaction::create([
        'loan_id' => $this->loan->id,
        'account_id' => null,
        'branch_id' => $this->member->branch_id,
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'counter',
        'amount' => 125,
        'status' => 'pending',
        'transaction_date' => now(),
        'recorded_by' => $this->member->id,
    ]);

    $response = $this->actingAs($this->manager)->getJson('/api/services/pending');
    $response->assertOk();

    $item = collect($response->json('data'))->firstWhere('id', $payment->id);
    expect($item)->not->toBeNull()
        ->and($item['account_holder'])->toBe(trim($this->member->personalData->first_name.' '.$this->member->personalData->last_name));

    $this->actingAs($this->manager)
        ->getJson('/api/services/pending?search='.$this->member->personalData->last_name)
        ->assertOk()
        ->assertJsonFragment(['id' => $payment->id]);
});

test('loan lifecycle mail renders its notification message', function () {
    $notification = new LoanLifecycleNotification(
        $this->loan,
        'Loan update',
        'Your loan status has changed.',
    );

    Mail::to($this->member->email)->send($notification);

    expect($notification->render())->toContain('Your loan status has changed.');
});
