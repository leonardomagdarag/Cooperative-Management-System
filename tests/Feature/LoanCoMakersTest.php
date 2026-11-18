<?php

use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $branch = Branch::create(['name' => 'Borrower Branch']);
    $this->borrower = User::factory()->create([
        'branch_id' => $branch->id,
        'account_status' => 'active',
        'profile_completion_required' => false,
    ])->assignRole('member');
    $this->coMaker = User::factory()->create([
        'branch_id' => $branch->id,
        'account_status' => 'active',
    ])->assignRole('member');
    $this->encoder = User::factory()->create([
        'branch_id' => $branch->id,
        'role' => 'manager',
        'is_officer' => true,
        'profile_completion_required' => false,
    ])->assignRole('manager');
    $this->product = LoanProduct::factory()->create([
        'base_type' => 'business',
        'max_amount' => 10000,
        'max_term' => 12,
        'required_co_makers' => 0,
    ]);
    $this->application = [
        'loan_product_code' => $this->product->code,
        'amount' => 1000,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'purpose' => 'Purchase supplies',
    ];
});

test('a member can find and add an optional co-maker and see them on the loan', function () {
    $this->actingAs($this->borrower)
        ->getJson('/api/loans/search-members?'.http_build_query([
            'q' => $this->coMaker->user_number,
            'co_maker_for' => $this->borrower->id,
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->coMaker->id);

    $loanId = $this->postJson('/api/services/loan-application', $this->application + [
        'co_makers' => [$this->coMaker->id],
    ])->assertCreated()->json('data.id');

    $this->assertDatabaseHas('group_loan_members', [
        'loan_id' => $loanId,
        'user_id' => $this->coMaker->id,
        'has_approved' => false,
    ]);

    expect(Notification::query()->where('user_id', $this->coMaker->id)->where('title', 'Listed as Loan Co-Maker')->count())->toBe(1);

    $this->getJson('/api/member-loans')
        ->assertOk()
        ->assertJsonPath('data.0.group_members.0.name', $this->coMaker->personalData->first_name.' '.$this->coMaker->personalData->last_name)
        ->assertJsonPath('data.0.group_members.0.user_number', $this->coMaker->user_number);

    $this->actingAs($this->encoder)->getJson("/api/loans/{$loanId}")
        ->assertOk()
        ->assertJsonPath('group_members.0.id', $this->coMaker->id)
        ->assertJsonPath('co_makers.0.id', $this->coMaker->id);

    $this->getJson('/api/loans?loan_status=pending')
        ->assertOk()
        ->assertJsonPath('data.0.co_makers.0.id', $this->coMaker->id);
});

test('an officer can add co-makers through both intake paths and encoding enforces the product count', function () {
    $this->product->update(['required_co_makers' => 1]);
    $this->actingAs($this->encoder)->postJson('/api/services/loan-application', $this->application + [
        'user_id' => $this->borrower->id,
        'co_makers' => [$this->coMaker->id],
    ])->assertCreated()->assertJsonPath('data.encoded_by', $this->encoder->id);

    $encoding = [
        'user_id' => $this->borrower->id,
        'loan_product' => $this->product->code,
        'loan_status' => 'pending',
        'loan_amount' => 1000,
        'interest_rate' => 3,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'applied_at' => now()->toDateString(),
    ];

    $this->postJson('/api/loans', $encoding)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('co_makers');

    $loanId = $this->postJson('/api/loans', $encoding + ['co_makers' => [$this->coMaker->id]])
        ->assertCreated()
        ->json('data.id');

    $this->assertDatabaseHas('group_loan_members', ['loan_id' => $loanId, 'user_id' => $this->coMaker->id]);
});

test('the co-maker search and submission reject ineligible members, the borrower, and duplicates', function () {
    $inactive = User::factory()->create([
        'branch_id' => $this->borrower->branch_id,
        'account_status' => 'inactive',
    ])->assignRole('member');
    $outsideBranch = User::factory()->create([
        'branch_id' => Branch::create(['name' => 'Other Branch'])->id,
        'account_status' => 'active',
    ])->assignRole('member');

    $this->actingAs($this->borrower);
    foreach ([$this->borrower, $this->encoder, $inactive, $outsideBranch] as $ineligible) {
        $this->getJson('/api/loans/search-members?'.http_build_query([
            'q' => $ineligible->user_number,
            'co_maker_for' => $this->borrower->id,
        ]))->assertOk()->assertJsonPath('data', []);

        $this->postJson('/api/services/loan-application', $this->application + [
            'co_makers' => [$ineligible->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('co_makers');
    }

    $this->postJson('/api/services/loan-application', $this->application + [
        'co_makers' => [$this->coMaker->id, $this->coMaker->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('co_makers');

    $this->assertDatabaseCount('loans', 0);
});

test('pending loans can update co-makers, active loans reject it, and member directory search is gated', function () {
    $replacement = User::factory()->create([
        'branch_id' => $this->borrower->branch_id,
        'account_status' => 'active',
    ])->assignRole('member');

    $this->actingAs($this->borrower)
        ->getJson('/api/loans/search-members?q='.$this->coMaker->user_number)
        ->assertForbidden();

    $loanId = $this->postJson('/api/services/loan-application', $this->application + [
        'co_makers' => [$this->coMaker->id],
    ])->assertCreated()->json('data.id');

    $this->actingAs($this->encoder)
        ->putJson("/api/loans/{$loanId}", [
            'loan_type' => 'business',
            'loan_amount' => 1500,
            'interest_rate' => 3,
            'term_months' => 6,
            'payment_frequency' => 'monthly',
            'co_makers' => [$this->coMaker->id, $replacement->id],
        ])
        ->assertOk();

    $this->assertDatabaseHas('group_loan_members', ['loan_id' => $loanId, 'user_id' => $replacement->id]);
    expect(Notification::query()->where('user_id', $this->coMaker->id)->where('title', 'Listed as Loan Co-Maker')->count())->toBe(1);
    expect(Notification::query()->where('user_id', $replacement->id)->where('title', 'Listed as Loan Co-Maker')->count())->toBe(1);

    Loan::query()->whereKey($loanId)->update(['loan_status' => 'active', 'approved_at' => now(), 'disbursed_at' => now()]);

    $this->putJson("/api/loans/{$loanId}", [
        'loan_type' => 'business',
        'loan_amount' => 1500,
        'interest_rate' => 3,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'co_makers' => [$this->coMaker->id],
    ])->assertUnprocessable();
});
