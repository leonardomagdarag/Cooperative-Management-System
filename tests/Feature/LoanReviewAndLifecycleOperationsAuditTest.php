<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
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

test('F024-7: manager disburses approved loan, crediting savings balance and creating transaction', function () {
    $loanAmount = 15000.00;
    $initialBalance = (float) $this->savingsAccount->balance;

    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_amount' => $loanAmount,
        'loan_status' => 'approved',
        'term_months' => 12,
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/loans/{$loan->id}/disburse");
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

test('F024-8: disburse rejects loans not in approved status with 422', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_status' => 'pending',
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/loans/{$loan->id}/disburse");
    $response->assertStatus(422)
        ->assertJson(['message' => 'Only approved loans can be disbursed.']);
});

test('F024-9: disburse rejects borrowers without savings account with 422', function () {
    $newUser = User::factory()->create(['branch_id' => $this->mainBranch->id]);

    $loan = Loan::factory()->create([
        'user_id' => $newUser->id,
        'branch_id' => $this->mainBranch->id,
        'loan_status' => 'approved',
    ]);

    $response = $this->actingAs($this->manager)->postJson("/api/loans/{$loan->id}/disburse");
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

test('F024-13: officer deletes loan record with audit trail', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'account_id' => $this->savingsAccount->id,
        'branch_id' => $this->mainBranch->id,
        'loan_status' => 'pending',
    ]);

    $response = $this->actingAs($this->loanOfficer)->deleteJson("/api/loans/{$loan->id}");
    $response->assertOk()
        ->assertJson(['message' => 'Loan deleted.']);

    expect(Loan::find($loan->id))->toBeNull();
    expect(Loan::withTrashed()->find($loan->id))->not->toBeNull();
});
