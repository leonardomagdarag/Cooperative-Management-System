<?php

use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();

    $this->member = User::factory()->create([
        'profile_completion_required' => false,
    ]);
    $this->member->assignRole('member');
});

test('admin can create a custom loan product and encode a loan against it', function () {
    $this->actingAs($this->admin);

    // 1. Create custom loan product
    $productResponse = $this->postJson('/api/loan-products', [
        'code' => 'calamity-relief',
        'name' => 'Calamity Relief Loan',
        'description' => 'Emergency financial aid for disaster-stricken members.',
        'base_type' => 'special',
        'icon' => 'hand-holding-medical',
        'interest_rate' => 1.0,
        'max_amount' => 25000,
        'max_term' => 24,
        'is_active' => true,
        'requires_no_existing_loan' => false,
        'minimum_share_capital' => 0,
        'minimum_membership_years' => 0,
        'required_co_makers' => 0,
        'requires_interview' => false,
        'requires_special_eligibility' => false,
        'sort_order' => 10,
    ]);

    $productResponse->assertCreated()
        ->assertJsonPath('data.code', 'calamity-relief')
        ->assertJsonPath('data.base_type', 'special');

    // 2. Loan officer encodes loan for member referencing the custom product
    $loanOfficer = User::where('email', 'loan@example.com')->firstOrFail();
    $this->actingAs($loanOfficer);

    $loanResponse = $this->postJson('/api/loans', [
        'user_id' => $this->member->id,
        'loan_product' => 'calamity-relief',
        'loan_status' => 'pending',
        'loan_amount' => 15000,
        'interest_rate' => 1.0,
        'term_months' => 12,
        'payment_frequency' => 'monthly',
        'applied_at' => now()->toDateString(),
    ]);

    $loanResponse->assertCreated();

    $loanId = $loanResponse->json('data.id');
    $loan = Loan::findOrFail($loanId);

    expect($loan->loan_product)->toBe('calamity-relief')
        ->and($loan->loan_type)->toBe('special')
        ->and($loan->loan_amount)->toBe('15000.00');

    // 3. Loan show endpoint surfaces custom product code and name
    $showResponse = $this->getJson("/api/loans/{$loanId}");
    $showResponse->assertOk()
        ->assertJsonPath('loan_product', 'calamity-relief')
        ->assertJsonPath('loan_product_name', 'Calamity Relief Loan')
        ->assertJsonPath('loan_type', 'special');
});

test('encoding loan rejects inactive custom loan product with 422', function () {
    $this->actingAs($this->admin);

    LoanProduct::create([
        'code' => 'legacy-loan',
        'name' => 'Legacy Discontinued Loan',
        'base_type' => 'business',
        'interest_rate' => 5.0,
        'max_amount' => 10000,
        'max_term' => 6,
        'is_active' => false,
        'requires_no_existing_loan' => false,
        'minimum_share_capital' => 0,
        'minimum_membership_years' => 0,
        'required_co_makers' => 0,
        'requires_interview' => false,
        'requires_special_eligibility' => false,
        'sort_order' => 99,
    ]);

    $loanOfficer = User::where('email', 'loan@example.com')->firstOrFail();
    $this->actingAs($loanOfficer);

    $this->postJson('/api/loans', [
        'user_id' => $this->member->id,
        'loan_product' => 'legacy-loan',
        'loan_status' => 'pending',
        'loan_amount' => 5000,
        'interest_rate' => 5.0,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'applied_at' => now()->toDateString(),
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['loan_product']);
});

test('member can apply for a custom loan product via services endpoint', function () {
    // Create custom product with 0 co-makers and no interview
    LoanProduct::create([
        'code' => 'micro-livelihood',
        'name' => 'Micro Livelihood Loan',
        'base_type' => 'business',
        'interest_rate' => 2.5,
        'max_amount' => 20000,
        'max_term' => 12,
        'is_active' => true,
        'requires_no_existing_loan' => false,
        'minimum_share_capital' => 0,
        'minimum_membership_years' => 0,
        'required_co_makers' => 0,
        'requires_interview' => false,
        'requires_special_eligibility' => false,
        'sort_order' => 5,
    ]);

    $this->actingAs($this->member);

    $response = $this->postJson('/api/services/loan-application', [
        'loan_product_code' => 'micro-livelihood',
        'amount' => 10000,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'purpose' => 'Inventory purchase',
    ]);

    $response->assertCreated();

    $loan = Loan::findOrFail($response->json('data.id'));
    expect($loan->loan_product)->toBe('micro-livelihood')
        ->and($loan->loan_type)->toBe('business');
});
