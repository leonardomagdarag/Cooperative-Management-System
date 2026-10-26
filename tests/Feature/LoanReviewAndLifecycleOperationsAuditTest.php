<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\PrivilegedActionRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CountersignService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(AccountSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->loanOfficer = User::where('email', 'leo.baluyot039+loan@gmail.com')->firstOrFail();
    // Review and loan maintenance are permission-gated and are not in the
    // default loan role preset. Grant them explicitly for these workflow tests.
    $this->loanOfficer->givePermissionTo('loans.update');
    $this->chairman = User::factory()->create(['role' => 'chairman', 'is_officer' => true]);
    $this->chairman->assignRole('chairman');
    $this->borrower = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();

    $this->mainBranch = Branch::where('code', 'BR000001')->firstOrFail();
    $this->savingsAccount = Account::where('user_id', $this->borrower->id)->where('account_type', 'savings')->firstOrFail();
});

test('F024-1: unauthenticated requests to loan review, disburse, and close return 401', function () {
    $this->postJson('/api/loans/1/review', ['action' => 'start_review'])->assertUnauthorized();
    $this->postJson('/api/loans/1/disburse')->assertUnauthorized();
    $this->postJson('/api/loans/1/close')->assertUnauthorized();
});

test('F024-2: regular borrower cannot review, disburse, or close loans', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_status' => 'pending',
    ]);

    $this->actingAs($this->borrower);

    $this->postJson("/api/loans/{$loan->id}/review", ['action' => 'start_review'])->assertForbidden();
    $this->postJson("/api/loans/{$loan->id}/disburse")->assertForbidden();
    $this->postJson("/api/loans/{$loan->id}/close")->assertForbidden();
});

test('F024-3: loan officer can start review and endorse loan for approval', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_status' => 'pending',
        'review_status' => null,
    ]);

    $this->actingAs($this->loanOfficer);

    // 1. Start review
    $res1 = $this->postJson("/api/loans/{$loan->id}/review", ['action' => 'start_review']);
    $res1->assertOk();
    $loan->refresh();
    expect($loan->review_status)->toBe('under_review')
        ->and($loan->reviewed_by)->toBe($this->loanOfficer->id);

    // 2. Endorse for approval
    $res2 = $this->postJson("/api/loans/{$loan->id}/review", ['action' => 'endorse']);
    $res2->assertOk();
    $loan->refresh();
    expect($loan->review_status)->toBe('endorsed_for_approval');
});

test('F024-4: loan officer can reject loan during review with notes', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_status' => 'pending',
        'review_status' => 'under_review',
    ]);

    $response = $this->actingAs($this->loanOfficer)->postJson("/api/loans/{$loan->id}/review", [
        'action' => 'reject',
        'notes' => 'Credit score does not meet minimum threshold.',
    ]);

    $response->assertOk();
    $loan->refresh();
    expect($loan->review_status)->toBe('review_rejected')
        ->and($loan->loan_status)->toBe('rejected')
        ->and($loan->review_notes)->toBe('Credit score does not meet minimum threshold.')
        ->and($loan->reviewed_by)->toBe($this->loanOfficer->id);
});

test('F024-5: loan officer can record interview completion and schedule', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_status' => 'pending',
        'review_status' => 'under_review',
    ]);

    $futureSchedule = now()->addDays(2)->format('Y-m-d H:i:s');

    $response = $this->actingAs($this->loanOfficer)->postJson("/api/loans/{$loan->id}/review", [
        'action' => 'interview_complete',
        'notes' => 'Interview conducted. Collateral verified.',
        'interview_schedule' => $futureSchedule,
    ]);

    $response->assertOk();
    $loan->refresh();
    expect($loan->interview_status)->toBe('completed')
        ->and($loan->interview_notes)->toBe('Interview conducted. Collateral verified.');
});

test('F024-6: manager or chairman can approve endorsed loan', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_status' => 'pending',
        'review_status' => 'endorsed_for_approval',
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/loans/{$loan->id}/review", [
        'action' => 'approve',
    ]);

    $response->assertOk();
    $loan->refresh();
    expect($loan->loan_status)->toBe('approved')
        ->and($loan->approved_by)->toBe($this->manager->id)
        ->and($loan->approved_at)->not->toBeNull();
});

test('F024-7: manager can disburse approved loan to savings when explicitly selected', function () {
    $loanAmount = 15000.00;
    $initialBalance = (float) $this->savingsAccount->balance;

    $loan = Loan::factory()->approved()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_amount' => $loanAmount,
        'loan_status' => 'approved',
        'term_months' => 12,
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/loans/{$loan->id}/disburse", ['destination' => 'savings']);
    $response->assertOk()
        ->assertJson(['message' => 'Loan disbursed.']);

    $loan->refresh();
    expect($loan->loan_status)->toBe('active')
        ->and($loan->due_date)->not->toBeNull();

    $this->savingsAccount->refresh();
    expect((float) $this->savingsAccount->balance)->toBe($initialBalance + $loanAmount);

    $this->assertDatabaseHas('transactions', [
        'loan_id' => $loan->id,
        'account_id' => $this->savingsAccount->id,
        'transaction_type' => 'loan_disbursement',
        'amount' => $loanAmount,
        'status' => 'completed',
    ]);
});

test('cash disbursement starts the schedule without crediting savings and cannot be repeated', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'branch_id' => $this->mainBranch->id,
        'loan_amount' => 15000.00,
        'loan_status' => 'approved',
        'term_months' => 12,
        'disbursed_at' => null,
        'due_date' => null,
    ]);
    $initialBalance = (float) $this->savingsAccount->balance;

    $this->actingAs($this->manager)->postJson("/api/loans/{$loan->id}/disburse", ['destination' => 'cash'])
        ->assertOk();

    $loan->refresh();
    expect($loan->loan_status)->toBe('active')
        ->and($loan->disbursed_at)->not->toBeNull()
        ->and($loan->paymentSchedule())->not->toBeNull()
        ->and((float) $this->savingsAccount->fresh()->balance)->toBe($initialBalance);

    $this->assertDatabaseHas('transactions', [
        'loan_id' => $loan->id,
        'transaction_type' => 'loan_disbursement',
        'account_id' => null,
        'status' => 'completed',
        'note' => 'Loan disbursed as physical cash.',
    ]);

    $this->postJson("/api/loans/{$loan->id}/disburse", ['destination' => 'cash'])
        ->assertStatus(409)
        ->assertJson(['message' => 'Only approved loans that have not been disbursed can be released.']);

    expect(Transaction::where('loan_id', $loan->id)->where('transaction_type', 'loan_disbursement')->count())->toBe(1);
});

test('F024-8: disburse rejects loans not in approved status', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_status' => 'pending',
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/loans/{$loan->id}/disburse", ['destination' => 'cash']);
    $response->assertStatus(409)
        ->assertJson(['message' => 'Only approved loans that have not been disbursed can be released.']);
});

test('savings destination rejects borrowers without a savings account', function () {
    $newUser = User::factory()->create(['branch_id' => $this->mainBranch->id]);

    $loan = Loan::factory()->create([
        'user_id' => $newUser->id,
        'branch_id' => $this->mainBranch->id,
        'loan_status' => 'approved',
        'disbursed_at' => null,
        'due_date' => null,
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/loans/{$loan->id}/disburse", ['destination' => 'savings']);
    $response->assertStatus(422)
        ->assertJson(['message' => 'Borrower has no savings account.']);
});

test('F024-10: closing loan with outstanding balance returns 422', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_amount' => 10000.00,
        'interest_rate' => 5.0,
        'term_months' => 6,
        'loan_status' => 'active',
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/loans/{$loan->id}/close");
    $response->assertStatus(422)
        ->assertJsonStructure(['message']);

    expect($response->json('message'))->toContain('cannot be closed because it still has an outstanding balance');
});

test('F024-11: closing loan with zero balance marks status as paid_off', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_amount' => 5000.00,
        'interest_rate' => 0,
        'term_months' => 6,
        'loan_status' => 'active',
    ]);

    // Record complete payment
    Transaction::create([
        'account_id' => $this->savingsAccount->id,
        'loan_id' => $loan->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'loan_payment',
        'amount' => 5000.00,
        'status' => 'completed',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->manager->id,
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/loans/{$loan->id}/close");
    $response->assertOk()
        ->assertJson(['message' => 'Loan closed.']);

    $loan->refresh();
    expect($loan->loan_status)->toBe('paid_off');
});

test('F024-12: officer updates loan terms with validation', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_amount' => 5000.00,
        'interest_rate' => 3.0,
        'term_months' => 12,
        'payment_frequency' => 'monthly',
        'loan_status' => 'pending',
    ]);

    $response = $this->actingAs($this->loanOfficer)->putJson("/api/loans/{$loan->id}", [
        'loan_amount' => 6000.00,
        'interest_rate' => 4.0,
        'term_months' => 18,
        'payment_frequency' => 'monthly',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Loan updated.']);

    $loan->refresh();
    expect((float) $loan->loan_amount)->toBe(6000.00)
        ->and((float) $loan->interest_rate)->toBe(4.00)
        ->and((int) $loan->term_months)->toBe(18);
});

test('F024-13: loan deletion requires chairman countersign and only removes an untouched application', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_status' => 'pending',
        'review_status' => null,
        'approved_at' => null,
        'approved_by' => null,
        'due_date' => null,
    ]);

    $response = $this->actingAs($this->loanOfficer)->deleteJson("/api/loans/{$loan->id}");
    $response->assertStatus(202)
        ->assertJsonPath('status', 'pending');

    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));
    expect($request->action_type)->toBe('loan.delete')
        ->and($request->status)->toBe('pending')
        ->and($request->payload['loan_id'])->toBe($loan->id)
        ->and(Loan::find($loan->id))->not->toBeNull();

    app(CountersignService::class)->approve($request, $this->chairman);

    expect(Loan::find($loan->id))->toBeNull();
    expect(Loan::withTrashed()->find($loan->id))->not->toBeNull();
});

test('F024-14: chairman loan deletion also requires a different chairman to countersign', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'branch_id' => $this->mainBranch->id,
        'loan_status' => 'pending',
        'review_status' => null,
        'approved_at' => null,
        'approved_by' => null,
        'due_date' => null,
    ]);
    $secondChairman = User::factory()->create([
        'role' => 'chairman',
        'is_officer' => true,
        'account_status' => 'active',
    ]);
    $secondChairman->assignRole('chairman');

    $response = $this->actingAs($this->chairman)->deleteJson("/api/loans/{$loan->id}");

    $response->assertStatus(202);
    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));
    expect($request->requested_by)->toBe($this->chairman->id)
        ->and(Loan::find($loan->id))->not->toBeNull();

    app(CountersignService::class)->approve($request, $secondChairman);
    expect(Loan::find($loan->id))->toBeNull();
});

test('F024-15: active loan with payment history cannot be deleted or disappear from member loans', function () {
    $loan = Loan::factory()->active()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
    ]);
    Transaction::factory()->create([
        'account_id' => $this->savingsAccount->id,
        'loan_id' => $loan->id,
        'branch_id' => $this->mainBranch->id,
        'recorded_by' => $this->loanOfficer->id,
        'transaction_type' => 'loan_payment',
        'status' => 'completed',
    ]);

    $this->actingAs($this->loanOfficer)
        ->deleteJson("/api/loans/{$loan->id}")
        ->assertStatus(409);

    expect(Loan::find($loan->id))->not->toBeNull();

    $this->actingAs($this->borrower)
        ->getJson('/api/member-loans')
        ->assertOk()
        ->assertJsonPath('data.0.id', $loan->id);
});

test('F024-16: loan list marks only untouched pending applications as deletable', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'branch_id' => $this->mainBranch->id,
        'loan_status' => 'pending',
        'review_status' => null,
        'approved_at' => null,
        'approved_by' => null,
        'due_date' => null,
    ]);

    $this->actingAs($this->loanOfficer)
        ->getJson('/api/loans?search='.$loan->loan_number)
        ->assertOk()
        ->assertJsonPath('data.0.id', $loan->id)
        ->assertJsonPath('data.0.can_delete', true);
});
