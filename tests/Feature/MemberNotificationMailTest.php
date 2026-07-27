<?php

use App\Jobs\SendEmailJob;
use App\Mail\LoanStatusNotification;
use App\Mail\WithdrawalStatusNotification;
use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Role;

test('loan approval queues an email notification for a member', function () {
    Bus::fake();
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

    Bus::assertDispatched(SendEmailJob::class, function (SendEmailJob $job) use ($member, $loan) {
        return $job->toEmail === $member->email
            && $job->mailable instanceof LoanStatusNotification
            && $job->mailable->loan->is($loan)
            && $job->mailable->status === 'approved';
    });

    $notification = $member->notifications()->latest('id')->first();

    expect($notification)->not->toBeNull()
        ->and($notification->type)->toBe('loan_approved')
        ->and($notification->title)->toBe('Loan Application Approved');
});

test('withdrawal approval queues an email notification for a member', function () {
    Bus::fake();
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

    Bus::assertDispatched(SendEmailJob::class, function (SendEmailJob $job) use ($member, $withdrawal) {
        return $job->toEmail === $member->email
            && $job->mailable instanceof WithdrawalStatusNotification
            && $job->mailable->withdrawal->is($withdrawal)
            && $job->mailable->status === 'approved';
    });

    $notification = $member->notifications()->latest('id')->first();

    expect($notification)->not->toBeNull()
        ->and($notification->title)->toBe('Withdrawal Request Approved')
        ->and($notification->priority)->toBe('high');
});
