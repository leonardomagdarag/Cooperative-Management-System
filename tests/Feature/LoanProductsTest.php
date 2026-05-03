<?php

use App\Livewire\Content\LoanProducts;
use App\Models\LoanProduct;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function adminUser(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $user->assignRole('admin');

    return $user;
}

function sampleProductPayload(array $overrides = []): array
{
    return array_merge([
        'code'                      => 'test_loan',
        'name'                      => 'Test Loan',
        'description'               => 'A test product.',
        'baseType'                  => 'business',
        'icon'                      => 'briefcase',
        'interestRate'              => '2.5',
        'maxAmount'                 => '20000',
        'maxTerm'                   => 12,
        'minimumShareCapital'       => '1000',
        'minimumMembershipYears'    => 1,
        'requiredCoMakers'          => 0,
        'sortOrder'                 => 10,
        'isActive'                  => true,
        'requiresNoExistingLoan'    => false,
        'requiresInterview'         => false,
        'requiresSpecialEligibility' => false,
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Modal state tests
// ---------------------------------------------------------------------------

test('admin can open the add modal', function () {
    $this->actingAs(adminUser());

    Livewire::test(LoanProducts::class)
        ->assertSet('showModal', false)
        ->call('openAddModal')
        ->assertSet('showModal', true)
        ->assertSet('editingLoanProductId', null);
});

test('non-admin cannot open the add modal', function () {
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
    $user = User::factory()->create(['role' => 'member']);
    $user->assignRole('member');
    $this->actingAs($user);

    Livewire::test(LoanProducts::class)
        ->call('openAddModal')
        ->assertSet('showModal', false)
        ->assertDispatched('error');
});

test('closeModal resets form and hides modal', function () {
    $this->actingAs(adminUser());

    Livewire::test(LoanProducts::class)
        ->call('openAddModal')
        ->assertSet('showModal', true)
        ->set('name', 'Temporary Name')
        ->call('closeModal')
        ->assertSet('showModal', false)
        ->assertSet('name', '');
});

test('editLoanProduct loads product data and opens modal', function () {
    $this->actingAs(adminUser());

    $product = LoanProduct::factory()->create([
        'name'          => 'Farm Loan',
        'code'          => 'farm_loan',
        'base_type'     => 'group',
        'interest_rate' => 1.75,
        'max_amount'    => 8000,
        'max_term'      => 6,
    ]);

    Livewire::test(LoanProducts::class)
        ->call('editLoanProduct', $product->id)
        ->assertSet('showModal', true)
        ->assertSet('editingLoanProductId', $product->id)
        ->assertSet('name', 'Farm Loan')
        ->assertSet('code', 'farm_loan')
        ->assertSet('baseType', 'group');
});

// ---------------------------------------------------------------------------
// Create / persist tests
// ---------------------------------------------------------------------------

test('admin can create a new loan product', function () {
    $this->actingAs(adminUser());

    $payload = sampleProductPayload();

    Livewire::test(LoanProducts::class)
        ->call('openAddModal')
        ->set('code', $payload['code'])
        ->set('name', $payload['name'])
        ->set('description', $payload['description'])
        ->set('baseType', $payload['baseType'])
        ->set('icon', $payload['icon'])
        ->set('interestRate', $payload['interestRate'])
        ->set('maxAmount', $payload['maxAmount'])
        ->set('maxTerm', $payload['maxTerm'])
        ->set('minimumShareCapital', $payload['minimumShareCapital'])
        ->set('minimumMembershipYears', $payload['minimumMembershipYears'])
        ->set('requiredCoMakers', $payload['requiredCoMakers'])
        ->set('sortOrder', $payload['sortOrder'])
        ->call('saveLoanProduct')
        ->assertHasNoErrors()
        ->assertSet('showModal', false)
        ->assertDispatched('success');

    $this->assertDatabaseHas('loan_products', [
        'code'      => 'test_loan',
        'name'      => 'Test Loan',
        'base_type' => 'business',
    ]);
});

test('loan product list refreshes after creation', function () {
    $this->actingAs(adminUser());

    $component = Livewire::test(LoanProducts::class);
    expect($component->get('loanProducts'))->toHaveCount(0);

    $component
        ->call('openAddModal')
        ->set('code', 'quick_loan')
        ->set('name', 'Quick Loan')
        ->set('baseType', 'business')
        ->set('interestRate', '3.00')
        ->set('maxAmount', '15000')
        ->set('maxTerm', 12)
        ->set('minimumMembershipYears', 0)
        ->set('requiredCoMakers', 0)
        ->set('sortOrder', 1)
        ->call('saveLoanProduct');

    expect($component->get('loanProducts'))->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// Update / persist tests
// ---------------------------------------------------------------------------

test('admin can update an existing loan product', function () {
    $this->actingAs(adminUser());

    $product = LoanProduct::factory()->create([
        'name'          => 'Old Name',
        'code'          => 'old_code',
        'base_type'     => 'business',
        'interest_rate' => 2.00,
        'max_amount'    => 10000,
        'max_term'      => 12,
    ]);

    Livewire::test(LoanProducts::class)
        ->call('editLoanProduct', $product->id)
        ->assertSet('showModal', true)
        ->set('name', 'Updated Name')
        ->set('maxAmount', '25000')
        ->set('interestRate', '3.50')
        ->call('saveLoanProduct')
        ->assertHasNoErrors()
        ->assertSet('showModal', false)
        ->assertDispatched('success');

    $this->assertDatabaseHas('loan_products', [
        'id'        => $product->id,
        'name'      => 'Updated Name',
        'max_amount' => 25000.00,
    ]);
});

test('updated product is reflected in the component list', function () {
    $this->actingAs(adminUser());

    $product = LoanProduct::factory()->create([
        'name'          => 'Original',
        'code'          => 'orig_code',
        'base_type'     => 'business',
        'interest_rate' => 2.00,
        'max_amount'    => 10000,
        'max_term'      => 12,
    ]);

    $component = Livewire::test(LoanProducts::class);

    $component
        ->call('editLoanProduct', $product->id)
        ->set('name', 'Revised')
        ->call('saveLoanProduct');

    $names = collect($component->get('loanProducts'))->pluck('name');
    expect($names)->toContain('Revised');
    expect($names)->not->toContain('Original');
});

// ---------------------------------------------------------------------------
// Validation tests
// ---------------------------------------------------------------------------

test('code is required to save a loan product', function () {
    $this->actingAs(adminUser());

    Livewire::test(LoanProducts::class)
        ->call('openAddModal')
        ->set('code', '')
        ->set('name', 'Some Loan')
        ->set('baseType', 'business')
        ->set('interestRate', '2')
        ->set('maxAmount', '5000')
        ->set('maxTerm', 6)
        ->set('minimumMembershipYears', 0)
        ->set('requiredCoMakers', 0)
        ->set('sortOrder', 1)
        ->call('saveLoanProduct')
        ->assertHasErrors(['code']);
});

test('duplicate code is rejected on create', function () {
    $this->actingAs(adminUser());

    LoanProduct::factory()->create(['code' => 'existing_code', 'name' => 'First', 'base_type' => 'business', 'interest_rate' => 1, 'max_amount' => 5000, 'max_term' => 6]);

    Livewire::test(LoanProducts::class)
        ->call('openAddModal')
        ->set('code', 'existing_code')
        ->set('name', 'Second')
        ->set('baseType', 'business')
        ->set('interestRate', '2')
        ->set('maxAmount', '5000')
        ->set('maxTerm', 6)
        ->set('minimumMembershipYears', 0)
        ->set('requiredCoMakers', 0)
        ->set('sortOrder', 2)
        ->call('saveLoanProduct')
        ->assertHasErrors(['code']);
});

test('duplicate code is allowed when updating the same record', function () {
    $this->actingAs(adminUser());

    $product = LoanProduct::factory()->create(['code' => 'my_code', 'name' => 'My Loan', 'base_type' => 'business', 'interest_rate' => 1, 'max_amount' => 5000, 'max_term' => 6]);

    Livewire::test(LoanProducts::class)
        ->call('editLoanProduct', $product->id)
        ->set('name', 'My Updated Loan')
        ->call('saveLoanProduct')
        ->assertHasNoErrors(['code']);
});

// ---------------------------------------------------------------------------
// Permission guard tests
// ---------------------------------------------------------------------------

test('non-admin save attempt is rejected and does not write to DB', function () {
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
    $user = User::factory()->create(['role' => 'member']);
    $user->assignRole('member');
    $this->actingAs($user);

    Livewire::test(LoanProducts::class)
        ->set('code', 'hack_loan')
        ->set('name', 'Hack Loan')
        ->set('baseType', 'business')
        ->set('interestRate', '1')
        ->set('maxAmount', '5000')
        ->set('maxTerm', 6)
        ->set('minimumMembershipYears', 0)
        ->set('requiredCoMakers', 0)
        ->set('sortOrder', 1)
        ->call('saveLoanProduct')
        ->assertDispatched('error');

    $this->assertDatabaseMissing('loan_products', ['code' => 'hack_loan']);
});
