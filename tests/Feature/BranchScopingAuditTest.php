<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\MemberApplication;
use App\Models\OfficerPermissionOverride;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BranchScope;
use Database\Seeders\AccountSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->chairman = User::where('user_number', 'USR000002')->firstOrFail();
    $this->accountant = User::where('user_number', 'USR000004')->firstOrFail();
    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();

    $this->mainBranch = Branch::where('code', 'BR000001')->firstOrFail();
    $this->officeBranch = Branch::where('code', 'BR000002')->firstOrFail();
    $this->accountant->update(['branch_id' => $this->officeBranch->id]);

    $this->mainMember = User::where('user_number', 'USR000007')->firstOrFail();
    $this->officeMember = User::where('user_number', 'USR000012')->firstOrFail();
});

test('F016-1: unauthenticated requests to branch endpoints return 401', function () {
    $this->getJson('/api/branches')->assertUnauthorized();
    $this->getJson('/api/branches/options')->assertUnauthorized();
    $this->postJson('/api/branches', ['name' => 'Test Branch'])->assertUnauthorized();
    $this->putJson("/api/branches/{$this->officeBranch->id}", ['name' => 'Updated'])->assertUnauthorized();
    $this->deleteJson("/api/branches/{$this->officeBranch->id}")->assertUnauthorized();
});

test('F016-2: regular member cannot access branch management (403)', function () {
    $this->actingAs($this->mainMember);
    $this->getJson('/api/branches')->assertForbidden();
    $this->postJson('/api/branches', ['name' => 'Member Branch'])->assertForbidden();
    $this->putJson("/api/branches/{$this->officeBranch->id}", ['name' => 'Hacked'])->assertForbidden();
    $this->deleteJson("/api/branches/{$this->officeBranch->id}")->assertForbidden();
});

test('F016-3: audit logs are recorded when admin creates and updates a branch', function () {
    $this->actingAs($this->admin);

    $response = $this->postJson('/api/branches', [
        'name' => 'Audit Test Branch',
        'address' => '789 Test St',
        'contact_number' => '09181112233',
    ])->assertCreated();

    $branchId = $response->json('id');
    expect($branchId)->not->toBeNull();

    $createdLog = Activity::where('event', 'branch.created')
        ->where('subject_id', $branchId)
        ->first();
    expect($createdLog)->not->toBeNull()
        ->and($createdLog->causer_id)->toBe($this->admin->id);

    $this->putJson("/api/branches/{$branchId}", [
        'name' => 'Audit Test Branch Updated',
    ])->assertOk();

    $updatedLog = Activity::where('event', 'branch.updated')
        ->where('subject_id', $branchId)
        ->first();
    expect($updatedLog)->not->toBeNull();
});

test('F016-4: admin cannot delete branch with active loans (422)', function () {
    $branch = Branch::create([
        'name' => 'Loan Branch',
        'address' => '456 Loan Rd',
    ]);

    $product = LoanProduct::first() ?? LoanProduct::create([
        'name' => 'General Loan',
        'code' => 'GEN01',
        'interest_rate' => 5,
        'min_amount' => 1000,
        'max_amount' => 50000,
        'min_term_months' => 6,
        'max_term_months' => 24,
    ]);

    Loan::create([
        'user_id' => $this->mainMember->id,
        'loan_product_id' => $product->id,
        'branch_id' => $branch->id,
        'loan_amount' => 5000,
        'loan_status' => 'active',
        'interest_rate' => 5,
        'term_months' => 12,
    ]);

    $this->actingAs($this->admin);
    $response = $this->deleteJson("/api/branches/{$branch->id}");
    $response->assertStatus(422)
        ->assertJson(['message' => 'Cannot delete branch because it has active or pending loans.']);
});

test('F016-5: admin cannot delete branch with pending transactions (422)', function () {
    $branch = Branch::create([
        'name' => 'Pending Txn Branch',
        'address' => '123 Pending St',
    ]);

    $account = Account::where('user_id', $this->mainMember->id)->firstOrFail();

    Transaction::create([
        'account_id' => $account->id,
        'branch_id' => $branch->id,
        'transaction_type' => 'deposit',
        'amount' => 1000,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'recorded_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);
    $response = $this->deleteJson("/api/branches/{$branch->id}");
    $response->assertStatus(422)
        ->assertJson(['message' => 'Cannot delete branch because it has pending transactions.']);
});

test('F016-6: admin cannot delete branch with pending member applications (422)', function () {
    $branch = Branch::create([
        'name' => 'Pending App Branch',
        'address' => '123 App St',
    ]);

    MemberApplication::create([
        'user_id' => $this->mainMember->id,
        'branch_id' => $branch->id,
        'original_email' => 'applicant@example.com',
        'first_name' => 'Applicant',
        'last_name' => 'Test',
        'status' => 'pending',
        'submitted_at' => now(),
    ]);

    $this->actingAs($this->admin);
    $response = $this->deleteJson("/api/branches/{$branch->id}");
    $response->assertStatus(422)
        ->assertJson(['message' => 'Cannot delete branch because it has pending member applications.']);
});

test('built-in officer roles with branch bypass can see all branches regardless of assignment', function () {
    expect($this->admin->can('branches.bypass'))->toBeTrue()
        ->and($this->chairman->can('branches.bypass'))->toBeTrue()
        ->and($this->accountant->can('branches.bypass'))->toBeTrue()
        ->and($this->manager->can('branches.bypass'))->toBeTrue()
        ->and($this->accountant->can('branches.bypass'))->toBeTrue();

    $product = LoanProduct::first() ?? LoanProduct::create([
        'name' => 'Standard Loan',
        'code' => 'STD01',
        'interest_rate' => 5,
        'min_amount' => 1000,
        'max_amount' => 50000,
        'min_term_months' => 6,
        'max_term_months' => 24,
    ]);

    // Create loan in mainBranch while the accountant is assigned to officeBranch.
    Loan::create([
        'user_id' => $this->mainMember->id,
        'loan_product_id' => $product->id,
        'branch_id' => $this->mainBranch->id,
        'loan_amount' => 10000,
        'loan_status' => 'pending',
        'interest_rate' => 5,
        'term_months' => 12,
    ]);

    // Create loan in officeBranch
    Loan::create([
        'user_id' => $this->officeMember->id,
        'loan_product_id' => $product->id,
        'branch_id' => $this->officeBranch->id,
        'loan_amount' => 8000,
        'loan_status' => 'pending',
        'interest_rate' => 5,
        'term_months' => 12,
    ]);

    $this->actingAs($this->accountant);
    $response = $this->getJson('/api/loans?loan_status=pending');
    $response->assertOk();

    $branchIds = collect($response->json('data'))->pluck('branch_id')->filter()->unique()->values()->all();
    expect($branchIds)->toEqualCanonicalizing([$this->mainBranch->id, $this->officeBranch->id]);

    $memberBranchIds = BranchScope::applyToUsers(User::query()->memberUsers(), $this->accountant)
        ->pluck('branch_id')->unique()->values()->all();
    expect($memberBranchIds)->toEqualCanonicalizing([$this->mainBranch->id, $this->officeBranch->id]);
});

test('an accountant assigned to a secondary branch can view loans across branches', function () {
    $product = LoanProduct::first() ?? LoanProduct::create([
        'name' => 'Standard Loan',
        'code' => 'STD01',
        'interest_rate' => 5,
        'min_amount' => 1000,
        'max_amount' => 50000,
        'min_term_months' => 6,
        'max_term_months' => 24,
    ]);

    Loan::create([
        'user_id' => $this->mainMember->id,
        'loan_product_id' => $product->id,
        'branch_id' => $this->mainBranch->id,
        'loan_amount' => 10000,
        'loan_status' => 'pending',
        'interest_rate' => 5,
        'term_months' => 12,
    ]);
    Loan::create([
        'user_id' => $this->officeMember->id,
        'loan_product_id' => $product->id,
        'branch_id' => $this->officeBranch->id,
        'loan_amount' => 8000,
        'loan_status' => 'pending',
        'interest_rate' => 5,
        'term_months' => 12,
    ]);

    $this->actingAs($this->accountant);
    $accountantResponse = $this->getJson('/api/loans?loan_status=pending')->assertOk();
    $accountantBranches = collect($accountantResponse->json('data'))->pluck('branch_id')->filter()->unique()->values()->all();
    expect($accountantBranches)->toEqualCanonicalizing([$this->mainBranch->id, $this->officeBranch->id]);
});

test('a per-officer deny override keeps the officer scoped despite the role bypass permission', function () {
    $product = LoanProduct::first() ?? LoanProduct::create([
        'name' => 'Standard Loan',
        'code' => 'STD01',
        'interest_rate' => 5,
        'min_amount' => 1000,
        'max_amount' => 50000,
        'min_term_months' => 6,
        'max_term_months' => 24,
    ]);

    $mainLoan = Loan::create([
        'user_id' => $this->mainMember->id,
        'loan_product_id' => $product->id,
        'branch_id' => $this->mainBranch->id,
        'loan_amount' => 10000,
        'loan_status' => 'pending',
        'interest_rate' => 5,
        'term_months' => 12,
    ]);

    Loan::create([
        'user_id' => $this->officeMember->id,
        'loan_product_id' => $product->id,
        'branch_id' => $this->officeBranch->id,
        'loan_amount' => 8000,
        'loan_status' => 'pending',
        'interest_rate' => 5,
        'term_months' => 12,
    ]);

    OfficerPermissionOverride::create([
        'user_id' => $this->accountant->id,
        'permission' => 'branches.bypass',
        'effect' => OfficerPermissionOverride::EFFECT_DISALLOW,
    ]);

    $this->actingAs($this->accountant);
    $response = $this->getJson('/api/loans?loan_status=pending');
    $response->assertOk();

    $branchIds = collect($response->json('data'))->pluck('branch_id')->filter()->unique()->values()->all();
    expect($branchIds)->toBe([$this->officeBranch->id]);
    expect($this->accountant->can('view', $mainLoan))->toBeFalse();

    $memberBranchIds = BranchScope::applyToUsers(User::query()->memberUsers(), $this->accountant)
        ->pluck('branch_id')->unique()->values()->all();
    expect($memberBranchIds)->toBe([$this->officeBranch->id]);
});

test('an accountant without branch bypass sees only ledger transactions for their branch', function () {
    OfficerPermissionOverride::create([
        'user_id' => $this->accountant->id,
        'permission' => 'branches.bypass',
        'effect' => OfficerPermissionOverride::EFFECT_DISALLOW,
    ]);

    $mainAccount = Account::factory()->create(['user_id' => $this->mainMember->id, 'account_type' => 'savings']);
    $officeAccount = Account::factory()->create(['user_id' => $this->officeMember->id, 'account_type' => 'savings']);
    Transaction::factory()->deposit()->create([
        'account_id' => $mainAccount->id,
        'branch_id' => $this->mainBranch->id,
        'status' => 'completed',
        'transaction_mode' => 'counter',
        'recorded_by' => $this->accountant->id,
    ]);
    Transaction::factory()->deposit()->create([
        'account_id' => $officeAccount->id,
        'branch_id' => $this->officeBranch->id,
        'status' => 'completed',
        'transaction_mode' => 'counter',
        'recorded_by' => $this->accountant->id,
    ]);

    $this->actingAs($this->accountant);
    $response = $this->getJson('/api/ledger')->assertOk();
    $branchNames = collect($response->json('data'))->pluck('branch_name')->unique()->values()->all();
    expect($branchNames)->toBe(['Branch Office']);
});

test('an officer without bypass and without a branch receives no branch scoped data', function () {
    $this->accountant->update(['branch_id' => null]);
    OfficerPermissionOverride::create([
        'user_id' => $this->accountant->id,
        'permission' => 'branches.bypass',
        'effect' => OfficerPermissionOverride::EFFECT_DISALLOW,
    ]);

    $this->actingAs($this->accountant);
    $members = BranchScope::applyToUsers(User::query()->memberUsers(), $this->accountant)->get();
    expect($members)->toBeEmpty();
});
