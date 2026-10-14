<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\CollectionRun;
use App\Models\CollectionRunItem;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\MemberApplication;
use App\Models\OfficerPermissionOverride;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->loanOfficer = User::where('email', 'leo.baluyot039+loan@gmail.com')->firstOrFail();

    $this->mainBranch = Branch::where('code', 'BR000001')->firstOrFail();
    $this->officeBranch = Branch::where('code', 'BR000002')->firstOrFail();
    $this->loanOfficer->update(['branch_id' => $this->officeBranch->id]);

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

test('branch officers stay in branch while main office and manager users can see all branches', function () {
    expect($this->loanOfficer->can('branches.bypass'))->toBeFalse();

    $product = LoanProduct::first() ?? LoanProduct::create([
        'name' => 'Standard Loan',
        'code' => 'STD01',
        'interest_rate' => 5,
        'min_amount' => 1000,
        'max_amount' => 50000,
        'min_term_months' => 6,
        'max_term_months' => 24,
    ]);

    // Create loan in mainBranch (loanOfficer is in officeBranch)
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

    $this->actingAs($this->loanOfficer);
    $response = $this->getJson('/api/loans?loan_status=pending');
    $response->assertOk();

    $branchIds = collect($response->json('data'))->pluck('branch_id')->filter()->unique()->values()->all();
    expect($branchIds)->toBe([$this->officeBranch->id]);

    $this->loanOfficer->update(['branch_id' => $this->mainBranch->id]);
    $this->actingAs($this->loanOfficer->fresh());
    $mainOfficerResponse = $this->getJson('/api/loans?loan_status=pending')->assertOk();
    $mainOfficerBranches = collect($mainOfficerResponse->json('data'))->pluck('branch_id')->filter()->unique()->values()->all();
    expect($mainOfficerBranches)->toEqualCanonicalizing([$this->mainBranch->id, $this->officeBranch->id]);

    $this->manager->update(['branch_id' => $this->officeBranch->id]);
    $this->actingAs($this->manager->fresh());
    $managerResponse = $this->getJson('/api/loans?loan_status=pending')->assertOk();
    $managerBranches = collect($managerResponse->json('data'))->pluck('branch_id')->filter()->unique()->values()->all();
    expect($this->manager->can('branches.bypass'))->toBeTrue()
        ->and($managerBranches)->toEqualCanonicalizing([$this->mainBranch->id, $this->officeBranch->id]);
});

test('a branch officer cannot expand branch scope with a bypass permission override', function () {
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

    OfficerPermissionOverride::create([
        'user_id' => $this->loanOfficer->id,
        'permission' => 'branches.bypass',
        'effect' => OfficerPermissionOverride::EFFECT_ALLOW,
    ]);

    $this->actingAs($this->loanOfficer);
    $response = $this->getJson('/api/loans?loan_status=pending');
    $response->assertOk();

    $branchIds = collect($response->json('data'))->pluck('branch_id')->filter()->unique()->values()->all();
    expect($branchIds)->toBe([$this->officeBranch->id]);
});

test('branch officer cannot add, edit, or remove items from a draft in another branch', function () {
    $product = LoanProduct::first() ?? LoanProduct::create([
        'name' => 'Standard Loan',
        'code' => 'STD01',
        'interest_rate' => 5,
        'min_amount' => 1000,
        'max_amount' => 50000,
        'min_term_months' => 6,
        'max_term_months' => 24,
    ]);
    $loan = Loan::create([
        'user_id' => $this->mainMember->id,
        'loan_product_id' => $product->id,
        'branch_id' => $this->mainBranch->id,
        'loan_amount' => 10000,
        'loan_status' => 'active',
        'interest_rate' => 5,
        'term_months' => 12,
    ]);
    $run = CollectionRun::create([
        'run_number' => 'CR-BRANCH-ISOLATION',
        'loan_officer_id' => $this->loanOfficer->id,
        'collection_date' => today(),
        'branch_id' => $this->mainBranch->id,
        'status' => 'draft',
    ]);
    $item = CollectionRunItem::create([
        'collection_run_id' => $run->id,
        'member_id' => $this->mainMember->id,
        'recorded_by' => $this->loanOfficer->id,
        'loan_id' => $loan->id,
        'account_id' => null,
        'amount' => 100,
        'visited' => true,
    ]);

    $this->actingAs($this->loanOfficer);
    $this->postJson("/api/collections/{$run->id}/items", [
        'member_id' => $this->mainMember->id,
        'loan_id' => $loan->id,
        'amount' => 100,
    ])->assertForbidden();
    $this->putJson("/api/collections/{$run->id}/items/{$item->id}", [
        'amount' => 200,
        'visited' => true,
    ])->assertForbidden();
    $this->deleteJson("/api/collections/{$run->id}/items/{$item->id}")->assertForbidden();

    expect($item->fresh()->amount)->toBe('100.00');
});
