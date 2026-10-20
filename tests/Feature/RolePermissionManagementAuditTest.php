<?php

use App\Models\PrivilegedActionRequest;
use App\Models\User;
use App\Services\CountersignService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->accountant = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $this->member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $this->chairman = User::factory()->create(['email' => 'role-approval-chairman@example.com', 'role' => 'chairman', 'is_officer' => true]);
    $this->chairman->assignRole('chairman');
});

test('F019-1: unauthenticated requests to roles endpoints return 401', function () {
    $this->getJson('/api/roles')->assertUnauthorized();
    $this->postJson('/api/roles', ['name' => 'auditor'])->assertUnauthorized();
    $this->putJson('/api/roles/manager/permissions', ['permissions' => []])->assertUnauthorized();
    $this->getJson('/api/roles/manager/restore-preset')->assertUnauthorized();
    $this->putJson('/api/roles/custom-role/rename', ['name' => 'new-name'])->assertUnauthorized();
    $this->deleteJson('/api/roles/custom-role')->assertUnauthorized();
});

test('F019-2: non-admin users receive 403 on all roles endpoints', function () {
    // Manager receives 403
    $this->actingAs($this->manager);
    $this->getJson('/api/roles')->assertForbidden();
    $this->postJson('/api/roles', ['name' => 'auditor'])->assertForbidden();
    $this->putJson('/api/roles/loan/permissions', ['permissions' => []])->assertForbidden();
    $this->getJson('/api/roles/loan/restore-preset')->assertForbidden();
    $this->deleteJson('/api/roles/loan')->assertForbidden();

    // Regular member receives 403
    $this->actingAs($this->member);
    $this->getJson('/api/roles')->assertForbidden();
});

test('F019-3: admin lists roles with permissions, counts, and catalog groups', function () {
    $this->actingAs($this->admin);

    $response = $this->getJson('/api/roles');
    $response->assertOk()
        ->assertJsonStructure([
            'roles',
            'role_permissions',
            'role_user_counts',
            'role_display_names',
            'role_descriptions',
            'role_presets',
            'base_roles',
            'groups',
        ]);

    $roles = $response->json('roles');
    expect($roles)->toContain('manager')
        ->and($roles)->toContain('accountant')
        ->and($roles)->toContain('loan');
});

test('F019-4: reserved roles are excluded from editable roles list', function () {
    $this->actingAs($this->admin);

    $response = $this->getJson('/api/roles');
    $roles = $response->json('roles');

    expect($roles)->not->toContain('admin')
        ->and($roles)->not->toContain('developer')
        ->and($roles)->not->toContain('member')
        ->and($roles)->not->toContain('inactive')
        ->and($roles)->not->toContain('terminated');
});

test('F019-5: admin creates custom role with validation and template inheritance', function () {
    $this->actingAs($this->admin);

    // Reserved role name is rejected
    $this->postJson('/api/roles', ['name' => 'admin'])
        ->assertStatus(422)
        ->assertJson(['message' => 'That role name is reserved.']);

    // Duplicate role name is rejected
    $this->postJson('/api/roles', ['name' => 'manager'])
        ->assertStatus(422)
        ->assertJson(['message' => 'That role already exists.']);

    // Valid creation copying permissions from accountant template
    $response = $this->postJson('/api/roles', [
        'name' => 'Compliance Inspector',
        'template' => 'accountant',
    ]);
    $response->assertAccepted()
        ->assertJsonStructure(['message', 'role', 'permissions', 'request_id'])
        ->assertJsonPath('status', 'pending');

    expect($response->json('role'))->toBe('compliance-inspector');

    expect(Role::where('name', 'compliance-inspector')->exists())->toBeFalse();
    app(CountersignService::class)->approve(PrivilegedActionRequest::findOrFail($response->json('request_id')), $this->chairman);

    // Verify Spatie role exists and has inherited permissions + locked base permissions
    $role = Role::findByName('compliance-inspector', 'web');
    expect($role)->not->toBeNull()
        ->and($role->hasPermissionTo('dashboard.view'))->toBeTrue()
        ->and($role->hasPermissionTo('members.view'))->toBeTrue()
        ->and($role->hasPermissionTo('branches.bypass'))->toBeFalse();
});

test('F019-6: admin updates role permissions preserving locked core permissions', function () {
    $this->actingAs($this->admin);

    // Attempt to update manager permissions omitting core locked permissions
    $response = $this->putJson('/api/roles/manager/permissions', [
        'permissions' => ['deposits.view'],
    ]);
    $response->assertStatus(202)
        ->assertJson(['message' => 'Permission update requested and requires countersign approval.']);

    $requestId = $response->json('request_id');
    $request = PrivilegedActionRequest::findOrFail($requestId);

    // Simulate chairman approval
    $chairman = User::factory()->create(['email' => 'chairman2@example.com']);
    $chairman->assignRole('chairman');
    $chairman->givePermissionTo('oversight.approve');

    $countersignService = app(CountersignService::class);
    $countersignService->approve($request, $chairman);

    $role = Role::findByName('manager', 'web');
    $permissions = $role->permissions->pluck('name')->toArray();

    // Locked core permissions must be automatically preserved
    expect($permissions)->toContain('dashboard.view')
        ->and($permissions)->toContain('notifications.view')
        ->and($permissions)->toContain('profile.view')
        ->and($permissions)->toContain('profile.update')
        ->and($permissions)->toContain('settings.view')
        ->and($permissions)->not->toContain('branches.bypass')
        // Role-specific locked permissions for manager must also be preserved
        ->and($permissions)->toContain('members.view')
        ->and($permissions)->toContain('loans.view')
        ->and($permissions)->toContain('services.view');
});

test('F019-7: admin restores default preset for a base role', function () {
    $this->actingAs($this->admin);

    // Valid preset restoration for accountant
    $response = $this->getJson('/api/roles/accountant/restore-preset');
    $response->assertOk()
        ->assertJsonStructure(['permissions']);

    $preset = $response->json('permissions');
    expect($preset)->toContain('transactions.view')
        ->and($preset)->toContain('reports.financial');

    // Custom role without preset returns 404
    $this->getJson('/api/roles/unknown-custom-role/restore-preset')
        ->assertNotFound();
});

test('F019-8: base roles cannot be renamed or deleted (422)', function () {
    $this->actingAs($this->admin);

    // Rename base role
    $this->putJson('/api/roles/manager/rename', ['name' => 'Chief Manager'])
        ->assertStatus(422)
        ->assertJson(['message' => 'Base roles cannot be renamed.']);

    // Delete base role
    $this->deleteJson('/api/roles/manager')
        ->assertStatus(422)
        ->assertJson(['message' => 'Base roles cannot be deleted.']);
});

test('F019-9: custom role cannot be renamed or deleted while assigned to users (422)', function () {
    $this->actingAs($this->admin);

    // Create custom role
    $create = $this->postJson('/api/roles', ['name' => 'Branch Auditor'])->assertAccepted();
    app(CountersignService::class)->approve(PrivilegedActionRequest::findOrFail($create->json('request_id')), $this->chairman);

    // Assign custom role to a user
    $user = User::factory()->create(['account_status' => 'active', 'role' => 'branch-auditor']);
    $user->assignRole('branch-auditor');

    // Rename attempt blocked
    $this->putJson('/api/roles/branch-auditor/rename', ['name' => 'Senior Auditor'])
        ->assertStatus(422)
        ->assertJson(['message' => 'Unassign role from 1 user(s) first.']);

    // Delete attempt blocked
    $this->deleteJson('/api/roles/branch-auditor')
        ->assertStatus(422)
        ->assertJson(['message' => 'Unassign role from 1 user(s) first.']);
});

test('F019-10: custom role lifecycle — rename and delete unassigned custom role', function () {
    $this->actingAs($this->admin);

    // 1. Create
    $create = $this->postJson('/api/roles', ['name' => 'Temp Role'])->assertAccepted();
    app(CountersignService::class)->approve(PrivilegedActionRequest::findOrFail($create->json('request_id')), $this->chairman);

    // 2. Rename
    $renameResponse = $this->putJson('/api/roles/temp-role/rename', ['name' => 'Renamed Role']);
    $renameResponse->assertAccepted()->assertJsonPath('role', 'renamed-role')->assertJsonPath('status', 'pending');
    app(CountersignService::class)->approve(PrivilegedActionRequest::findOrFail($renameResponse->json('request_id')), $this->chairman);

    expect(Role::where('name', 'temp-role')->exists())->toBeFalse()
        ->and(Role::where('name', 'renamed-role')->exists())->toBeTrue();

    // 3. Delete
    $deleteResponse = $this->deleteJson('/api/roles/renamed-role');
    $deleteResponse->assertAccepted()->assertJsonPath('status', 'pending');
    app(CountersignService::class)->approve(PrivilegedActionRequest::findOrFail($deleteResponse->json('request_id')), $this->chairman);

    expect(Role::where('name', 'renamed-role')->exists())->toBeFalse();
});
