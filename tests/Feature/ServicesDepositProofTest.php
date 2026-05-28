<?php

use App\Livewire\Content\Services;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use function Pest\Laravel\actingAs;

test('members with self service permissions still use the member services view', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('services.view', 'web');
    Permission::findOrCreate('services.create', 'web');

    $role = Role::findOrCreate('member', 'web');
    $role->givePermissionTo(['services.view', 'services.create']);

    $user = User::factory()->create(['role' => 'member', 'is_officer' => false]);
    $user->assignRole('member');

    Account::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'savings',
        'balance' => 1000,
    ]);

    actingAs($user);

    Livewire::test(Services::class)
        ->assertSet('canManageMemberServices', false)
        ->assertDontSee('Select Member');
});

test('secretary officers with services view use the officer services view', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('services.view', 'web');

    $role = Role::findOrCreate('secretary', 'web');
    $role->givePermissionTo(['services.view']);

    $user = User::factory()->create(['role' => 'secretary', 'is_officer' => true]);
    $user->assignRole('secretary');

    actingAs($user);

    Livewire::test(Services::class)
        ->assertSet('canManageMemberServices', true)
        ->assertSee('Select Member')
        ->assertSee('Start typing to search for a member');
});

test('loan officers only see loan-focused services for selected members', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::findOrCreate('loans.update', 'web');

    $role = Role::findOrCreate('loan', 'web');
    $role->syncPermissions(['loans.update']);

    $officer = User::factory()->create(['role' => 'loan', 'is_officer' => true]);
    $officer->assignRole('loan');

    $member = User::factory()->create(['role' => 'member', 'is_officer' => false]);

    Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 2500,
    ]);

    Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'share-capital',
        'balance' => 4000,
    ]);

    actingAs($officer);

    Livewire::test(Services::class)
        ->assertDontSee('Savings Balance')
        ->assertDontSee('Share Capital')
        ->assertViewHas('savingsAccount', null)
        ->assertViewHas('shareCapitalAccount', null)
        ->call('selectMember', $member->id)
        ->assertSee('Loan Applications')
        ->assertSee('Apply for Loan')
        ->assertSee('Make Payment')
        ->assertDontSee('Deposit Money')
        ->assertDontSee('Withdraw Money')
        ->assertDontSee('Savings Balance')
        ->assertDontSee('Share Capital')
        ->assertViewHas('savingsAccount', null)
        ->assertViewHas('shareCapitalAccount', null)
        ->call('openService', 'deposit')
        ->assertSet('activeService', null)
        ->assertDispatched('warning');
});

test('online deposits require proof of payment', function () {
    Storage::fake('public');
    Role::findOrCreate('member', 'web');

    $user = User::factory()->create(['role' => 'member']);
    $user->assignRole('member');

    Account::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'savings',
        'balance' => 1000,
    ]);

    actingAs($user);

    Livewire::test(Services::class)
        ->set('depositAccountType', 'savings')
        ->set('depositAmount', '500')
        ->set('depositTransactionMode', 'online')
        ->call('submitDeposit')
        ->assertHasErrors(['depositProof' => 'required_if']);
});

test('online deposits store proof and remain pending until verification', function () {
    Storage::fake('public');
    Role::findOrCreate('member', 'web');

    $user = User::factory()->create(['role' => 'member']);
    $user->assignRole('member');

    $account = Account::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'savings',
        'balance' => 1000,
    ]);

    actingAs($user);

    Livewire::test(Services::class)
        ->set('depositAccountType', 'savings')
        ->set('depositAmount', '500')
        ->set('depositTransactionMode', 'online')
        ->set('depositProof', UploadedFile::fake()->image('deposit-proof.png'))
        ->call('submitDeposit')
        ->assertHasNoErrors()
        ->assertSet('depositStep', 99);

    $transaction = $account->transactions()->latest('id')->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->status)->toBe('pending')
        ->and($transaction->proof_of_payment_path)->not->toBeNull()
        ->and((float) $account->fresh()->balance)->toBe(1000.0);

    Storage::disk('public')->assertExists($transaction->proof_of_payment_path);
});