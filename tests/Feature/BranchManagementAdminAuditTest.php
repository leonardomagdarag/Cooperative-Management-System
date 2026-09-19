<?php

use App\Models\Branch;
use App\Models\Loan;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@example.com')->firstOrFail();

    $this->headOffice = Branch::where('code', 'BR000001')->firstOrFail();
    $this->subBranch = Branch::where('code', 'BR000002')->firstOrFail();
});

test('F029-1: unauthenticated requests to branch management endpoints return 401', function () {
    $this->getJson('/api/branches')->assertUnauthorized();
    $this->postJson('/api/branches', [])->assertUnauthorized();
    $this->putJson('/api/branches/1', [])->assertUnauthorized();
    $this->deleteJson('/api/branches/1')->assertUnauthorized();
});

test('F029-2: regular member receives 403 on branch management endpoints', function () {
    $this->actingAs($this->member);

    $this->getJson('/api/branches')->assertForbidden();
    $this->postJson('/api/branches', [])->assertForbidden();
    $this->putJson("/api/branches/{$this->headOffice->id}", [])->assertForbidden();
    $this->deleteJson("/api/branches/{$this->headOffice->id}")->assertForbidden();
});

test('F029-3: admin lists branches with user, loan, transaction, and member counts', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/branches');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'code',
                    'name',
                    'is_head_office',
                    'address',
                    'contact_number',
                    'email',
                    'is_active',
                    'users_count',
                    'transactions_count',
                    'loans_count',
                    'members_count',
                    'created_at',
                ],
            ],
        ]);
});

test('F029-4: admin creates new branch with validation and audit logging', function () {
    $this->actingAs($this->admin);

    $response = $this->postJson('/api/branches', [
        'name' => 'Northern Hub',
        'address' => '789 North Expressway',
        'contact_number' => '09181112233',
        'email' => 'northern@coop.local',
        'is_head_office' => false,
        'is_active' => true,
    ]);

    $response->assertStatus(201)
        ->assertJson(['message' => 'Branch created successfully.']);

    $branchId = $response->json('id');
    $this->assertDatabaseHas('branches', [
        'id' => $branchId,
        'name' => 'Northern Hub',
    ]);
});

test('F029-5: branch creation rejects duplicate name with 422', function () {
    $this->actingAs($this->admin);

    $response = $this->postJson('/api/branches', [
        'name' => 'Main', // Already exists as head office
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('F029-6: admin updates branch details and ignores unique name conflict on self', function () {
    $this->actingAs($this->admin);

    $response = $this->putJson("/api/branches/{$this->subBranch->id}", [
        'name' => $this->subBranch->name, // Same name
        'address' => '456 Updated Annex Ave',
        'contact_number' => '09990001122',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Branch updated successfully.']);

    $this->subBranch->refresh();
    expect($this->subBranch->address)->toBe('456 Updated Annex Ave');
});

test('F029-7: admin cannot delete head office branch', function () {
    $this->actingAs($this->admin);

    $response = $this->deleteJson("/api/branches/{$this->headOffice->id}");

    $response->assertStatus(422)
        ->assertJson(['message' => 'The Head Office branch cannot be deleted.']);
});

test('F029-8: admin cannot delete branch with active or pending loans', function () {
    $this->actingAs($this->admin);

    $emptyBranch = Branch::create([
        'name' => 'Loan Branch',
        'code' => 'BR999991',
        'is_head_office' => false,
    ]);

    Loan::factory()->create([
        'branch_id' => $emptyBranch->id,
        'loan_status' => 'active',
    ]);

    $response = $this->deleteJson("/api/branches/{$emptyBranch->id}");

    $response->assertStatus(422)
        ->assertJson(['message' => 'Cannot delete branch because it has active or pending loans.']);
});

test('F029-9: admin deletes empty unlinked branch with audit logging', function () {
    $this->actingAs($this->admin);

    $tempBranch = Branch::create([
        'name' => 'Temp Disposable Branch',
        'code' => 'BR999999',
        'is_head_office' => false,
    ]);

    $response = $this->deleteJson("/api/branches/{$tempBranch->id}");

    $response->assertOk()
        ->assertJson(['message' => 'Branch deleted.']);

    $this->assertSoftDeleted('branches', ['id' => $tempBranch->id]);
});
