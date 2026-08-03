<?php

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Transaction;
use App\Models\User;
use App\Support\PersonPasswordPolicy;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
});

test('person password policy rejects predictable name plus digits', function () {
    $rules = PersonPasswordPolicy::passwordRules(false, PersonPasswordPolicy::normalizedNameTokens(['John', 'Doe']));

    $fails = validator(['password' => 'John12345'], ['password' => $rules])->fails();

    expect($fails)->toBeTrue();
});

test('person password policy requires letters and numbers', function () {
    $rules = PersonPasswordPolicy::passwordRules(false, []);

    expect(validator(['password' => 'abcdef'], ['password' => $rules])->fails())->toBeTrue()
        ->and(validator(['password' => 'password123'], ['password' => $rules])->passes())->toBeTrue()
        ->and(validator(['password' => 'short1'], ['password' => $rules])->fails())->toBeTrue();
});

test('edit password policy allows blank passwords', function () {
    $rules = PersonPasswordPolicy::passwordRules(true, []);

    expect(validator(['password' => ''], ['password' => $rules])->passes())->toBeTrue();
});

test('member create rejects a predictable password through the api', function () {
    actingAs($this->admin);

    $this->postJson('/api/members', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'birth_date' => now()->subYears(30)->toDateString(),
        'gender' => 'male',
        'civil_status' => 'single',
        'email' => 'john.predictable@example.com',
        'phone_number' => '09171234567',
        'password' => 'John12345',
        'password_confirmation' => 'John12345',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

test('member edit allows a blank password', function () {
    $member = User::factory()->create([
        'email' => 'jane.edit@example.com',
    ]);

    actingAs($this->admin);

    $this->putJson('/api/members/'.$member->id, [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.edit@example.com',
    ])->assertOk();
});

test('loan total payable applies interest per month across the term', function () {
    $loan = Loan::factory()->make([
        'loan_amount' => 3000,
        'interest_rate' => 3,
        'term_months' => 3,
    ]);

    expect($loan->totalInterest())->toBe(270.0)
        ->and($loan->totalPayable())->toBe(3270.0)
        ->and($loan->monthlyPayment())->toBe(1090.0);
});

test('counter loan payment covers the full payable amount and marks the loan paid off', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $account = Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 5000,
    ]);

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'payment_frequency' => 'monthly',
        'loan_status' => 'active',
    ]);

    actingAs($member);

    $this->postJson('/api/services/loan-payment', [
        'loan_id' => $loan->id,
        'account_id' => $account->id,
        'amount' => $loan->totalPayable(),
        'transaction_mode' => 'counter',
    ])->assertCreated();

    expect($loan->fresh()->loan_status)->toBe('paid_off')
        ->and((float) $loan->fresh()->remainingBalance())->toBe(0.0);
});

test('online loan payment stays pending and does not change balances', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $account = Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 5000,
    ]);

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'payment_frequency' => 'monthly',
        'loan_status' => 'active',
    ]);

    actingAs($member);

    $this->postJson('/api/services/loan-payment', [
        'loan_id' => $loan->id,
        'account_id' => $account->id,
        'amount' => 620,
        'transaction_mode' => 'online',
    ])->assertCreated();

    $payment = Transaction::query()
        ->where('loan_id', $loan->id)
        ->where('transaction_type', 'loan_payment')
        ->latest('id')
        ->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe('pending')
        ->and($loan->fresh()->loan_status)->toBe('active')
        ->and((float) $account->fresh()->balance)->toBe(5000.0);
});

test('online deposits require proof of payment', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 0,
    ]);

    actingAs($member);

    $this->postJson('/api/services/deposit', [
        'amount' => 1000,
        'account_type' => 'savings',
        'transaction_mode' => 'online',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('proof');
});

test('online deposits with proof stay pending until verification', function () {
    Storage::fake('public');

    $member = User::factory()->create();
    $member->assignRole('member');

    Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 0,
    ]);

    actingAs($member);

    $this->postJson('/api/services/deposit', [
        'amount' => 1000,
        'account_type' => 'savings',
        'transaction_mode' => 'online',
        'proof' => UploadedFile::fake()->create('proof.jpg', 200, 'image/jpeg'),
    ])->assertCreated();

    $transaction = Transaction::query()
        ->where('transaction_type', 'deposit')
        ->latest('id')
        ->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->status)->toBe('pending')
        ->and($transaction->proof_of_payment_path)->not->toBeNull();
});

test('reserved role names cannot be created', function () {
    actingAs($this->admin);

    $this->postJson('/api/roles', ['name' => 'terminated'])->assertUnprocessable();

    expect(Role::query()->where('name', 'terminated')->where('guard_name', 'web')->count())->toBe(1);
});

test('base roles are protected from deletion and renaming', function () {
    actingAs($this->admin);

    $this->deleteJson('/api/roles/manager')->assertUnprocessable();
    $this->putJson('/api/roles/manager/rename', ['name' => 'boss'])->assertUnprocessable();

    expect(Role::query()->where('name', 'manager')->where('guard_name', 'web')->exists())->toBeTrue();
});

test('assigned custom roles cannot be renamed or deleted', function () {
    actingAs($this->admin);

    $this->postJson('/api/roles', ['name' => 'Branch Supervisor'])->assertCreated();

    $officer = User::factory()->create();
    $officer->syncRoles(['branch-supervisor']);

    $this->putJson('/api/roles/branch-supervisor/rename', ['name' => 'Operations Lead'])->assertUnprocessable();
    $this->deleteJson('/api/roles/branch-supervisor')->assertUnprocessable();

    expect(Role::query()->where('name', 'branch-supervisor')->where('guard_name', 'web')->exists())->toBeTrue();
});

test('admin can rename and delete an unused custom role', function () {
    actingAs($this->admin);

    $this->postJson('/api/roles', ['name' => 'Compliance Officer'])->assertCreated();

    $this->putJson('/api/roles/compliance-officer/rename', ['name' => 'Compliance Lead'])->assertOk();
    expect(Role::query()->where('name', 'compliance-officer')->where('guard_name', 'web')->exists())->toBeFalse()
        ->and(Role::query()->where('name', 'compliance-lead')->where('guard_name', 'web')->exists())->toBeTrue();

    $this->deleteJson('/api/roles/compliance-lead')->assertOk();
    expect(Role::query()->where('name', 'compliance-lead')->where('guard_name', 'web')->exists())->toBeFalse();
});

test('loan product duplicate code is rejected on create but allowed on update', function () {
    actingAs($this->admin);

    $payload = [
        'code' => 'veteran',
        'name' => 'Veteran Loan',
        'base_type' => 'special',
        'interest_rate' => 2,
        'max_amount' => 50000,
        'max_term' => 24,
        'minimum_membership_years' => 1,
        'required_co_makers' => 1,
        'sort_order' => 5,
    ];

    $this->postJson('/api/loan-products', $payload)->assertCreated();

    $this->postJson('/api/loan-products', $payload)->assertUnprocessable();

    $product = LoanProduct::where('code', 'veteran')->firstOrFail();

    $this->putJson('/api/loan-products/'.$product->id, array_merge($payload, ['name' => 'Veteran Loan Plus']))->assertOk();
});

test('non-admin cannot encode loans', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    actingAs($member);

    $this->postJson('/api/loans', [
        'user_id' => $member->id,
        'loan_product' => 'group',
        'loan_type' => 'group',
        'loan_status' => 'pending',
        'loan_amount' => 1000,
        'interest_rate' => 1.5,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'applied_at' => now()->toDateString(),
    ])->assertForbidden();
});

test('seeders create secretary account with secretary role', function () {
    $this->seed(RolePermissionSeeder::class);

    $secretary = User::where('email', 'secretary@example.com')->first();

    expect($secretary)->not->toBeNull()
        ->and($secretary->role)->toBe('secretary')
        ->and((bool) $secretary->is_officer)->toBeTrue()
        ->and($secretary->hasRole('secretary'))->toBeTrue()
        ->and($secretary->can('loans.view'))->toBeTrue()
        ->and($secretary->can('members.create'))->toBeTrue()
        ->and($secretary->cannot('admin'))->toBeTrue();
});

test('permission names referenced by policies exist in the catalog', function () {
    $permissions = Permission::pluck('name')->all();

    foreach (['members.create', 'members.update', 'members.view', 'settings.update', 'loans.view'] as $name) {
        expect(in_array($name, $permissions, true))->toBeTrue("Permission [{$name}] missing");
    }
});
