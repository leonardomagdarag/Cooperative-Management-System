<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->manager = User::where('email', 'manager@example.com')->firstOrFail();

    $this->main = Branch::where('code', 'BR000001')->firstOrFail();
    $this->office = Branch::where('code', 'BR000002')->firstOrFail();

    $this->mainMember = User::where('user_number', 'USR000007')->firstOrFail();
    $this->officeMember = User::where('user_number', 'USR000012')->firstOrFail();
});

test('seeded branches exist with a head office', function () {
    expect(Branch::count())->toBe(2)
        ->and($this->main->is_head_office)->toBeTrue()
        ->and($this->office->is_head_office)->toBeFalse();

    expect($this->admin->branch_id)->toBe($this->main->id)
        ->and($this->manager->branch_id)->toBe($this->main->id)
        ->and($this->officeMember->branch_id)->toBe($this->office->id);
});

test('admin can list, create, update and delete branches', function () {
    $this->actingAs($this->admin);

    $this->getJson('/api/branches')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Main')
        ->assertJsonPath('data.0.is_head_office', true);

    $created = $this->postJson('/api/branches', [
        'name' => 'Downtown',
        'address' => '123 Rizal Ave',
        'contact_number' => '09171234567',
    ])->assertCreated()->json('id');

    $this->assertDatabaseHas('branches', ['id' => $created, 'name' => 'Downtown']);

    $this->putJson("/api/branches/{$created}", ['name' => 'Downtown-2'])
        ->assertOk();

    $this->assertDatabaseHas('branches', ['id' => $created, 'name' => 'Downtown-2']);

    $this->deleteJson("/api/branches/{$created}")->assertOk();
    $this->assertSoftDeleted('branches', ['id' => $created]);
});

test('non-admin cannot manage branches', function () {
    $this->actingAs($this->manager);

    $this->getJson('/api/branches')->assertForbidden();
    $this->postJson('/api/branches', ['name' => 'Nope'])->assertForbidden();
});

test('branch options endpoint lists active branches to authorized users', function () {
    $this->actingAs($this->manager);

    $this->getJson('/api/branches/options')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Main');
});

test('admin can filter members by branch', function () {
    $this->actingAs($this->admin);

    $response = $this->getJson('/api/members?branch_id='.$this->office->id);

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('user_number');
    expect($names)->toContain('USR000012')->not->toContain('USR000007');
});

test('manager with branch bypass sees members from all branches', function () {
    $this->actingAs($this->manager);

    $response = $this->getJson('/api/members');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->not->toBeEmpty()
        ->and(collect($data)->pluck('branch_name')->unique()->values()->all())
        ->toEqualCanonicalizing(['Main', 'Branch']);
    expect(collect($data)->pluck('user_number'))->toContain('USR000012');
});

test('manager with branch bypass can narrow the member list via the branch filter', function () {
    $this->actingAs($this->manager);

    $response = $this->getJson('/api/members?branch_id='.$this->office->id);

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('branch_name')->unique()->values()->all())->toBe(['Branch']);
});

test('manager with branch bypass can view a member from another branch', function () {
    $this->actingAs($this->manager);

    $this->getJson('/api/members/'.$this->officeMember->id)
        ->assertOk()
        ->assertJsonPath('user_number', 'USR000012');
});

test('branch-scoped manager can view members of their own branch', function () {
    $this->actingAs($this->manager);

    $this->getJson('/api/members/'.$this->mainMember->id)
        ->assertOk()
        ->assertJsonPath('user_number', 'USR000007');
});

test('expenses expose all branches for an officer holding branch bypass', function () {
    Expense::create([
        'category' => 'Utilities',
        'description' => 'Main rent',
        'amount' => 1000,
        'expense_date' => now(),
        'recorded_by' => $this->manager->id,
        'branch_id' => $this->main->id,
    ]);
    Expense::create([
        'category' => 'Utilities',
        'description' => 'Office rent',
        'amount' => 500,
        'expense_date' => now(),
        'recorded_by' => $this->manager->id,
        'branch_id' => $this->office->id,
    ]);

    $this->actingAs($this->manager);

    $response = $this->getJson('/api/expenses');

    $response->assertOk();
    $branches = collect($response->json('data'))->pluck('branch_name')->unique()->values()->all();
    expect($branches)->toEqualCanonicalizing(['Main', 'Branch']);
});

test('ledger transactions expose all branches for an officer holding branch bypass', function () {
    $mainAccount = Account::factory()->create([
        'user_id' => $this->mainMember->id,
        'account_type' => 'savings',
    ]);
    $officeAccount = Account::factory()->create([
        'user_id' => $this->officeMember->id,
        'account_type' => 'savings',
    ]);

    Transaction::factory()->deposit()->create([
        'account_id' => $mainAccount->id,
        'branch_id' => $this->main->id,
        'status' => 'completed',
        'transaction_mode' => 'counter',
        'recorded_by' => $this->manager->id,
    ]);
    Transaction::factory()->deposit()->create([
        'account_id' => $officeAccount->id,
        'branch_id' => $this->office->id,
        'status' => 'completed',
        'transaction_mode' => 'counter',
        'recorded_by' => $this->manager->id,
    ]);

    $this->actingAs($this->manager);

    $response = $this->getJson('/api/ledger');

    $response->assertOk();
    $branches = collect($response->json('data'))->pluck('branch_name')->unique()->values()->all();
    expect($branches)->toEqualCanonicalizing(['Main', 'Branch']);
});

test('manager role preset does not grant branch management', function () {
    expect($this->manager->can('branches.view'))->toBeFalse()
        ->and($this->manager->can('branches.create'))->toBeFalse();
});

test('admin cannot delete head office branch', function () {
    $this->actingAs($this->admin);

    $response = $this->deleteJson("/api/branches/{$this->main->id}");

    $response->assertStatus(422)
        ->assertJson(['message' => 'The Head Office branch cannot be deleted.']);

    $this->assertNotSoftDeleted('branches', ['id' => $this->main->id]);
});

test('admin cannot delete branch with assigned users', function () {
    $this->actingAs($this->admin);

    $response = $this->deleteJson("/api/branches/{$this->office->id}");

    $response->assertStatus(422)
        ->assertJson(['message' => 'Cannot delete branch because it has assigned users or members.']);

    $this->assertNotSoftDeleted('branches', ['id' => $this->office->id]);
});
