<?php

use App\Livewire\Content\Loans;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function createLoanEncodingAdmin(): User
{
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('loans.view', 'web');
    Permission::findOrCreate('loans.encode', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create([
        'role' => 'admin',
        'is_officer' => true,
    ]);

    $user->assignRole('admin');
    $user->givePermissionTo(['loans.view', 'loans.encode']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function createMemberUser(array $overrides = []): User
{
    Role::findOrCreate('member', 'web');

    $user = User::factory()->create(array_merge([
        'role' => 'member',
        'is_officer' => false,
    ], $overrides));

    $user->syncRoles(['member']);

    return $user;
}

test('admin with encoding permission can create an encoded loan using a dynamic loan product and status', function () {
    $admin = createLoanEncodingAdmin();
    $member = createMemberUser([
        'user_number' => 'USR900001',
    ]);
    $product = LoanProduct::factory()->create([
        'code' => 'education-loan',
        'name' => 'Education Loan',
        'base_type' => 'special',
        'interest_rate' => 4.25,
        'max_term' => 18,
        'is_active' => true,
    ]);

    $member->personalData()->update([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
    ]);

    Livewire::actingAs($admin)
        ->test(Loans::class)
        ->call('openCreateModal')
        ->assertSet('showCreateModal', true)
        ->assertSee('Education Loan')
        ->call('selectMember', $member->id)
        ->set('encodedLoanProduct', $product->code)
        ->assertSet('encodedLoanType', 'special')
        ->set('encodedLoanAmount', '15000')
        ->set('encodedLoanStatus', 'active')
        ->set('encodedInterestRate', '4.25')
        ->set('encodedLoanTerm', 18)
        ->set('encodedPaymentFrequency', 'monthly')
        ->set('appliedAt', now()->toDateString())
        ->call('saveEncodedLoan')
        ->assertHasNoErrors()
        ->assertSet('showCreateModal', false)
        ->assertDispatched('success');

    $loan = Loan::query()->latest('id')->first();

    expect($loan)->not->toBeNull();
    expect($loan->user_id)->toBe($member->id);
    expect($loan->loan_type)->toBe('special');
    expect($loan->loan_status)->toBe('active');
    expect((float) $loan->loan_amount)->toBe(15000.0);
    expect((float) $loan->interest_rate)->toBe(4.25);
    expect($loan->approved_at)->not->toBeNull();
    expect($loan->approved_by)->toBe($admin->id);
    expect($loan->due_date)->not->toBeNull();
});

test('member search matches names and ids in the encode modal', function () {
    $admin = createLoanEncodingAdmin();
    $member = createMemberUser([
        'user_number' => 'USR123456',
    ]);

    $member->personalData()->update([
        'first_name' => 'Lucia',
        'last_name' => 'Reyes',
    ]);

    Livewire::actingAs($admin)
        ->test(Loans::class)
        ->call('openCreateModal')
        ->set('memberSearch', 'Lucia')
        ->assertSee('Lucia Reyes')
        ->set('memberSearch', '123456')
        ->assertSee('USR123456');
});

test('non admin cannot encode loans even with the raw permission assigned', function () {
    Role::findOrCreate('loan', 'web');
    Permission::findOrCreate('loans.view', 'web');
    Permission::findOrCreate('loans.encode', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $loanOfficer = User::factory()->create([
        'role' => 'loan',
        'is_officer' => true,
    ]);

    $loanOfficer->assignRole('loan');
    $loanOfficer->givePermissionTo(['loans.view', 'loans.encode']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Livewire::actingAs($loanOfficer)
        ->test(Loans::class)
        ->call('openCreateModal')
        ->assertSet('showCreateModal', false)
        ->assertDispatched('error');

    Livewire::actingAs($loanOfficer)
        ->test(Loans::class)
        ->set('selectedMemberId', createMemberUser()->id)
        ->set('encodedLoanType', 'business')
        ->set('encodedLoanAmount', '5000')
        ->set('encodedInterestRate', '2.50')
        ->set('encodedLoanTerm', 6)
        ->set('encodedPaymentFrequency', 'monthly')
        ->set('appliedAt', now()->toDateString())
        ->call('saveEncodedLoan')
        ->assertForbidden();
});

test('members only see and open their own loans in the shared loans section', function () {
    $memberRole = Role::findOrCreate('member', 'web');
    Permission::findOrCreate('loans.view', 'web');
    Permission::findOrCreate('loans.create', 'web');
    $memberRole->givePermissionTo(['loans.view', 'loans.create']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $member = createMemberUser([
        'user_number' => 'USR200001',
    ]);
    $otherMember = createMemberUser([
        'user_number' => 'USR200002',
    ]);

    $member->personalData()->update([
        'first_name' => 'Alice',
        'last_name' => 'Rivera',
    ]);

    $otherMember->personalData()->update([
        'first_name' => 'Brian',
        'last_name' => 'Santos',
    ]);

    $ownLoan = Loan::factory()->create([
        'user_id' => $member->id,
    ]);

    $otherLoan = Loan::factory()->create([
        'user_id' => $otherMember->id,
    ]);

    Livewire::actingAs($member)
        ->test(Loans::class)
        ->assertSee('Alice')
        ->assertSee('Rivera')
        ->assertDontSee('Brian')
        ->assertDontSee('Santos')
        ->call('viewDetails', $ownLoan->id)
        ->assertSet('showModal', true);

    Livewire::actingAs($member)
        ->test(Loans::class)
        ->call('viewDetails', $otherLoan->id)
        ->assertForbidden();
});
