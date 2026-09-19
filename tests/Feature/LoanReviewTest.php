<?php

use App\Models\Loan;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->officer = User::where('email', 'loan@example.com')->firstOrFail();
    $this->borrower = User::factory()->create();
});

test('loan officer cannot approve an endorsed loan (role gate)', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'loan_status' => 'pending',
        'review_status' => 'endorsed_for_approval',
    ]);

    actingAs($this->officer)
        ->postJson("/api/loans/{$loan->id}/review", ['action' => 'approve'])
        ->assertForbidden();

    expect($loan->fresh()->loan_status)->toBe('pending');
});

test('admin cannot approve a loan that is not endorsed', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'loan_status' => 'pending',
        'review_status' => 'under_review',
    ]);

    actingAs($this->admin)
        ->postJson("/api/loans/{$loan->id}/review", ['action' => 'approve'])
        ->assertStatus(422)
        ->assertJson(['message' => 'Loan must be endorsed before it can be approved.']);

    expect($loan->fresh()->loan_status)->toBe('pending');
});

test('admin can approve an endorsed loan', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'loan_status' => 'pending',
        'review_status' => 'endorsed_for_approval',
    ]);

    actingAs($this->admin)
        ->postJson("/api/loans/{$loan->id}/review", ['action' => 'approve'])
        ->assertOk();

    $loan->refresh();

    expect($loan->loan_status)->toBe('approved')
        ->and($loan->approved_by)->toBe($this->admin->id)
        ->and($loan->approved_at)->not->toBeNull();
});

test('admin can return an endorsed loan for review with notes', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'loan_status' => 'pending',
        'review_status' => 'endorsed_for_approval',
    ]);

    actingAs($this->admin)
        ->postJson("/api/loans/{$loan->id}/review", [
            'action' => 'return_for_review',
            'notes' => 'Please attach the updated DTI registration.',
        ])
        ->assertOk();

    $loan->refresh();

    expect($loan->review_status)->toBe('under_review')
        ->and($loan->review_notes)->toBe('Please attach the updated DTI registration.')
        ->and($loan->reviewed_by)->toBe($this->admin->id)
        ->and($loan->loan_status)->toBe('pending');
});

test('loan officer can start review', function () {
    $loan = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'loan_status' => 'pending',
        'review_status' => null,
    ]);

    actingAs($this->officer)
        ->postJson("/api/loans/{$loan->id}/review", ['action' => 'start_review'])
        ->assertOk();

    $loan->refresh();

    expect($loan->review_status)->toBe('under_review')
        ->and($loan->reviewed_by)->toBe($this->officer->id);
});

test('review queue only lists pending, not-yet-approved loans', function () {
    $application = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'branch_id' => $this->officer->branch_id,
        'loan_status' => 'pending',
        'approved_by' => null,
        'approved_at' => null,
    ]);
    Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'branch_id' => $this->officer->branch_id,
        'loan_status' => 'active',
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
    ]);

    actingAs($this->officer)
        ->getJson('/api/loans?loan_status=pending&per_page=50')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $application->id);
});

test('pending loan with approval stamp is not treated as an application', function () {
    $encoded = Loan::factory()->create([
        'user_id' => $this->borrower->id,
        'branch_id' => $this->officer->branch_id,
        'loan_status' => 'pending',
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
    ]);

    actingAs($this->officer)
        ->getJson('/api/loans?loan_status=pending&per_page=50')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
