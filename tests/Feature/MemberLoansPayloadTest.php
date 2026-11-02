<?php

use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $this->member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $this->actingAs($this->member);
});

function makeMemberLoan(User $user, array $attributes = []): Loan
{
    return Loan::factory()->active()->create(array_merge([
        'user_id' => $user->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'payment_frequency' => 'monthly',
    ], $attributes));
}

test('member loans payload counts only completed payments and exposes derived payment fields', function () {
    $loan = makeMemberLoan($this->member);

    $completed = Transaction::factory()->loanPayment()->atCounter()->create([
        'loan_id' => $loan->id,
        'amount' => $loan->totalPayable(),
        'status' => 'completed',
    ]);

    Transaction::factory()->loanPayment()->pending()->create([
        'loan_id' => $loan->id,
        'amount' => 100,
    ]);

    $payload = collect($this->getJson('/api/member-loans')->assertOk()->json('data'))
        ->firstWhere('id', $loan->id);

    expect($payload)->not->toBeNull()
        ->and((float) $payload['total_paid'])->toBe($loan->totalPayable())
        ->and((float) $payload['remaining_balance'])->toBe(0.0)
        ->and((float) $payload['progress'])->toEqualWithDelta(100.0, 0.05)
        ->and($payload)->toHaveKey('payments')
        ->and($payload)->not->toHaveKey('completed_payments')
        ->and($payload['payments'])->toHaveCount(2);

    $reportedCompleted = collect($payload['payments'])->firstWhere('status', 'completed');
    expect($reportedCompleted)
        ->toHaveKeys(['id', 'payment_number', 'transaction_number', 'amount', 'payment_date', 'status', 'is_reversal'])
        ->and($reportedCompleted['payment_date'])->toBe($completed->transaction_date->format('M d, Y'))
        ->and($reportedCompleted['is_reversal'])->toBeFalse();

    $reportedPending = collect($payload['payments'])->firstWhere('status', 'pending');
    expect($reportedPending)->not->toBeNull()
        ->and($reportedPending['is_reversal'])->toBeFalse();
});

test('member loans payload nets out reversed payments and flags them', function () {
    $loan = makeMemberLoan($this->member, ['loan_amount' => 5000, 'interest_rate' => 12, 'term_months' => 6]);

    $original = Transaction::factory()->loanPayment()->atCounter()->create([
        'loan_id' => $loan->id,
        'amount' => 300,
        'status' => 'completed',
    ]);

    Transaction::factory()->loanPayment()->atCounter()->create([
        'loan_id' => $loan->id,
        'amount' => 300,
        'status' => 'completed',
        'reversal_of_id' => $original->id,
    ]);

    $payload = collect($this->getJson('/api/member-loans')->assertOk()->json('data'))
        ->firstWhere('id', $loan->id);

    expect((float) $payload['total_paid'])->toBe(0.0)
        ->and($payload['payments'])->toHaveCount(2)
        ->and(collect($payload['payments'])->where('is_reversal', true))->toHaveCount(1);
});

test('member loan modal payload lists only the borrowers own documents', function () {
    $loan = makeMemberLoan($this->member);
    $document = $loan->documents()->create([
        'original_name' => 'Signed Loan Agreement.pdf',
        'storage_path' => 'loan-documents/'.$loan->id.'/agreement.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        'uploaded_by' => $this->member->id,
    ]);

    $otherMember = User::where('email', 'leo.baluyot039+crisdavid.jarabejo@gmail.com')->firstOrFail();
    $otherLoan = makeMemberLoan($otherMember);
    $otherLoan->documents()->create([
        'original_name' => 'Other Member Agreement.pdf',
        'storage_path' => 'loan-documents/'.$otherLoan->id.'/agreement.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_by' => $otherMember->id,
    ]);

    $loans = collect($this->getJson('/api/member-loans')->assertOk()->json('data'));
    $documents = $loans->firstWhere('id', $loan->id)['documents'];

    expect($loans->pluck('id'))->not->toContain($otherLoan->id)
        ->and($documents)->toHaveCount(1)
        ->and($documents[0])->toMatchArray([
            'id' => $document->id,
            'original_name' => 'Signed Loan Agreement.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
        ])
        ->and($documents[0]['url'])->toContain("/api/loans/{$loan->id}/documents/{$document->id}");
});

test('officer loan detail returns totals and payment references', function () {
    $loan = makeMemberLoan($this->member);

    Transaction::factory()->loanPayment()->atCounter()->create([
        'loan_id' => $loan->id,
        'amount' => 200,
        'status' => 'completed',
    ]);

    $admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $admin->assignRole('chairman');
    $this->actingAs($admin);

    $detail = $this->getJson("/api/loans/{$loan->id}")->assertOk()->json();

    expect($detail)
        ->toHaveKeys(['total_paid', 'remaining_balance', 'progress', 'principal_amount', 'estimated_interest', 'estimated_total_repayment', 'loan_type_label'])
        ->and((float) $detail['total_paid'])->toBe(200.0)
        // progress is measured against the total repayable, not the principal.
        ->and((float) $detail['progress'])->toEqualWithDelta(200 / $loan->totalPayable() * 100, 0.05)
        ->and($detail['payments'][0])->toHaveKeys(['payment_number', 'transaction_number', 'payment_date', 'is_reversal']);
});

test('member profile payload reports loan progress against the total repayable', function () {
    $loan = makeMemberLoan($this->member);

    Transaction::factory()->loanPayment()->atCounter()->create([
        'loan_id' => $loan->id,
        'amount' => 500,
        'status' => 'completed',
    ]);

    // A pending payment must not count toward "paid".
    Transaction::factory()->loanPayment()->pending()->create([
        'loan_id' => $loan->id,
        'amount' => 75,
    ]);

    $payload = collect($this->getJson('/api/profile')->assertOk()->json('loans'))
        ->firstWhere('id', $loan->id);

    $payable = $loan->totalPayable();

    expect($payload)->not->toBeNull()
        ->and((float) $payload['total_paid'])->toBe(500.0)
        ->and((float) $payload['remaining_balance'])->toEqualWithDelta($payable - 500, 0.01)
        ->and((float) $payload['progress'])->toEqualWithDelta(round(500 / $payable * 100, 1), 0.05);
});
