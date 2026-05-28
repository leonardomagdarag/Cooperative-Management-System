<?php

use App\Mail\LoanStatusNotification;
use App\Mail\WithdrawalStatusNotification;
use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

test('loan approval queues an email notification for a member', function () {
    Mail::fake();
    Role::findOrCreate('member', 'web');

    $member = User::factory()->create([
        'role' => 'member',
        'email' => 'member@example.com',
    ]);
    $member->assignRole('member');

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_status' => 'approved',
        'loan_amount' => 15000,
    ]);

    app(NotificationService::class)->notifyLoanApproved($loan);

    Mail::assertQueued(LoanStatusNotification::class, function (LoanStatusNotification $mail) use ($member, $loan) {
        return $mail->hasTo($member->email)
            && $mail->loan->is($loan)
            && $mail->status === 'approved';
    });

    $notification = $member->notifications()->latest('id')->first();

    expect($notification)->not->toBeNull()
        ->and($notification->type)->toBe('loan_approved')
        ->and($notification->title)->toBe('Loan Application Approved');
});

test('withdrawal approval queues an email notification for a member', function () {
    Mail::fake();
    Role::findOrCreate('member', 'web');

    $member = User::factory()->create([
        'role' => 'member',
        'email' => 'member@example.com',
    ]);
    $member->assignRole('member');

    $account = Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
    ]);

    $withdrawal = Transaction::factory()->withdrawal()->create([
        'account_id' => $account->id,
        'status' => 'completed',
        'amount' => 2500,
    ]);

    app(NotificationService::class)->notifyWithdrawalApproved($withdrawal);

    Mail::assertQueued(WithdrawalStatusNotification::class, function (WithdrawalStatusNotification $mail) use ($member, $withdrawal) {
        return $mail->hasTo($member->email)
            && $mail->withdrawal->is($withdrawal)
            && $mail->status === 'approved';
    });

    $notification = $member->notifications()->latest('id')->first();

    expect($notification)->not->toBeNull()
        ->and($notification->title)->toBe('Withdrawal Request Approved')
        ->and($notification->priority)->toBe('high');
});