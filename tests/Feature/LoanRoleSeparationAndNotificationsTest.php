<?php

use App\Jobs\SendEmailJob;
use App\Models\Loan;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->chairman = User::factory()->create(['role' => 'chairman', 'is_officer' => true]);
    $this->chairman->assignRole('chairman');
    $this->manager = User::factory()->create(['role' => 'manager', 'is_officer' => true]);
    $this->manager->assignRole('manager');
    $this->loanOfficer = User::factory()->create(['role' => 'loan', 'is_officer' => true]);
    $this->loanOfficer->assignRole('loan');
    $this->applicant = User::factory()->create(['role' => 'member']);
    $this->applicant->assignRole('member');
});

test('only a chairman distinct from the applicant, encoder, and reviewer can final approve', function () {
    $loan = Loan::factory()->pending()->create([
        'user_id' => $this->applicant->id,
        'encoded_by' => $this->loanOfficer->id,
        'reviewed_by' => $this->manager->id,
    ]);

    expect(Gate::forUser($this->chairman)->allows('finalApprove', $loan))->toBeTrue()
        ->and(Gate::forUser($this->manager)->allows('finalApprove', $loan))->toBeFalse()
        ->and(Gate::forUser($this->applicant)->allows('finalApprove', $loan))->toBeFalse()
        ->and(Gate::forUser($this->loanOfficer)->allows('finalApprove', $loan))->toBeFalse();

    $loan->update(['reviewed_by' => $this->chairman->id]);
    expect(Gate::forUser($this->chairman)->allows('finalApprove', $loan->fresh()))->toBeFalse();

    $loan->update(['reviewed_by' => $this->manager->id, 'encoded_by' => $this->chairman->id]);
    expect(Gate::forUser($this->chairman)->allows('finalApprove', $loan->fresh()))->toBeFalse();

    $loan->update(['encoded_by' => $this->loanOfficer->id, 'user_id' => $this->chairman->id]);
    expect(Gate::forUser($this->chairman)->allows('finalApprove', $loan->fresh()))->toBeFalse();
});

test('loan encoding and editing cannot bypass chairman approval and encoding records the actor', function () {
    $this->actingAs($this->manager);
    $payload = [
        'user_id' => $this->applicant->id,
        'loan_product' => 'business',
        'loan_type' => 'business',
        'loan_amount' => 10000,
        'interest_rate' => 3,
        'term_months' => 12,
        'payment_frequency' => 'monthly',
        'applied_at' => now()->toDateString(),
    ];

    $this->postJson('/api/loans', $payload + ['loan_status' => 'approved'])->assertUnprocessable();
    expect(Loan::query()->count())->toBe(0);

    $response = $this->postJson('/api/loans', $payload + ['loan_status' => 'pending'])->assertCreated();
    $loan = Loan::findOrFail($response->json('data.id'));
    expect($loan->loan_status)->toBe('pending')
        ->and($loan->encoded_by)->toBe($this->manager->id)
        ->and($loan->approved_by)->toBeNull();

    $this->putJson("/api/loans/{$loan->id}", [
        'loan_type' => 'business',
        'loan_amount' => 10000,
        'interest_rate' => 3,
        'term_months' => 12,
        'payment_frequency' => 'monthly',
        'loan_status' => 'approved',
    ])->assertUnprocessable()->assertJsonValidationErrors('loan_status');

    expect($loan->fresh()->loan_status)->toBe('pending');
});

test('every loan lifecycle notification reaches the applicant in app and by email', function () {
    Queue::fake();
    $loan = Loan::factory()->pending()->create(['user_id' => $this->applicant->id]);
    $notifications = app(NotificationService::class);

    $notifications->notifyLoanSubmitted($loan);
    $notifications->notifyLoanUnderReview($loan);
    $notifications->notifyLoanInterviewCompleted($loan);
    $notifications->notifyLoanEndorsedForApproval($loan);
    $notifications->notifyLoanReturnedForReview($loan, 'Please provide one more document.');
    $notifications->notifyLoanReviewRejected($loan, 'Eligibility requirements were not met.');
    $notifications->notifyLoanApproved($loan);
    $notifications->notifyLoanDisbursed($loan);
    $notifications->notifyLoanPayment($loan, 500);
    $notifications->notifyLoanDue($loan, 3);
    $notifications->notifyLoanOverdue($loan);
    $notifications->notifyLoanDetailsUpdated($loan);
    $notifications->notifyLoanClosed($loan);

    expect(Notification::query()->where('user_id', $this->applicant->id)->count())->toBe(13);
    Queue::assertPushed(SendEmailJob::class, 13);
});
