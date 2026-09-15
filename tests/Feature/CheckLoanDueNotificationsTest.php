<?php

use App\Jobs\SendEmailJob;
use App\Mail\LoanDueNotification;
use App\Models\Loan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('member', 'web');
});

test('active disbursed loan due in 7 days receives warning notification and email job', function () {
    Bus::fake();

    $member = User::factory()->create(['email' => 'active.borrower@example.com']);
    $member->assignRole('member');

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_status' => 'active',
        'due_date' => Carbon::today()->addDays(7)->toDateString(),
    ]);

    $this->artisan('notifications:check-loan-due')
        ->assertSuccessful();

    $notification = $member->notifications()
        ->where('type', 'loan_due')
        ->where('related_id', $loan->id)
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification->message)->toContain('7 day');

    Bus::assertDispatched(SendEmailJob::class, function (SendEmailJob $job) use ($member, $loan) {
        return $job->toEmail === $member->email
            && $job->mailable instanceof LoanDueNotification
            && $job->mailable->loan->is($loan)
            && $job->mailable->daysRemaining === 7
            && $job->mailable->isOverdue === false;
    });
});

test('approved loan due in 3 days receives warning notification and email job', function () {
    Bus::fake();

    $member = User::factory()->create(['email' => 'approved.borrower@example.com']);
    $member->assignRole('member');

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_status' => 'approved',
        'due_date' => Carbon::today()->addDays(3)->toDateString(),
    ]);

    $this->artisan('notifications:check-loan-due')
        ->assertSuccessful();

    $notification = $member->notifications()
        ->where('type', 'loan_due')
        ->where('related_id', $loan->id)
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification->message)->toContain('3 day');

    Bus::assertDispatched(SendEmailJob::class, function (SendEmailJob $job) use ($member, $loan) {
        return $job->toEmail === $member->email
            && $job->mailable instanceof LoanDueNotification
            && $job->mailable->loan->is($loan)
            && $job->mailable->daysRemaining === 3;
    });
});

test('active loan overdue receives overdue notification and email job', function () {
    Bus::fake();

    $member = User::factory()->create(['email' => 'overdue.borrower@example.com']);
    $member->assignRole('member');

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_status' => 'active',
        'due_date' => Carbon::today()->subDays(2)->toDateString(),
    ]);

    $this->artisan('notifications:check-loan-due')
        ->assertSuccessful();

    $notification = $member->notifications()
        ->where('type', 'loan_due')
        ->where('related_id', $loan->id)
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification->message)->toContain('overdue');

    Bus::assertDispatched(SendEmailJob::class, function (SendEmailJob $job) use ($member, $loan) {
        return $job->toEmail === $member->email
            && $job->mailable instanceof LoanDueNotification
            && $job->mailable->loan->is($loan)
            && $job->mailable->isOverdue === true;
    });
});

test('subsequent command run on the same day does not duplicate notifications', function () {
    Bus::fake();

    $member = User::factory()->create(['email' => 'dedup.borrower@example.com']);
    $member->assignRole('member');

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_status' => 'active',
        'due_date' => Carbon::today()->addDays(1)->toDateString(),
    ]);

    $this->artisan('notifications:check-loan-due')->assertSuccessful();
    expect($member->notifications()->count())->toBe(1);

    // Run again
    $this->artisan('notifications:check-loan-due')->assertSuccessful();
    expect($member->notifications()->count())->toBe(1);

    Bus::assertDispatchedTimes(SendEmailJob::class, 1);
});
