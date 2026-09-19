<?php

use App\Models\Branch;
use App\Models\OfficerPermissionOverride;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\AccountSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->manager = User::where('email', 'manager@example.com')->firstOrFail();
    $this->secretary = User::where('email', 'secretary@example.com')->firstOrFail();
    $this->loanOfficer = User::where('email', 'loan@example.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@example.com')->firstOrFail();
    $this->mainBranch = Branch::where('code', 'BR000001')->firstOrFail();
});

test('F018-1: unauthenticated requests to officer permission endpoints return 401', function () {
    $this->getJson("/api/officers/{$this->manager->id}/permissions")->assertUnauthorized();
    $this->putJson("/api/officers/{$this->manager->id}/permissions", [])->assertUnauthorized();
});

test('F018-2: user without officers.manage-permissions receives 403', function () {
    // Secretary role does not have officers.manage-permissions in preset
    $this->actingAs($this->secretary);
    $this->getJson("/api/officers/{$this->manager->id}/permissions")->assertForbidden();
    $this->putJson("/api/officers/{$this->manager->id}/permissions", ['overrides' => []])->assertForbidden();

    // Regular member receives 403
    $this->actingAs($this->member);
    $this->getJson("/api/officers/{$this->manager->id}/permissions")->assertForbidden();
});

test('F018-3: requesting permission overrides for non-officer returns 404', function () {
    $this->actingAs($this->admin);

    // arjay.ramos is a regular member (is_officer is false)
    $response = $this->getJson("/api/officers/{$this->member->id}/permissions");
    $response->assertNotFound();
});

test('F018-4: admin can retrieve officer permission details and overrides', function () {
    $this->actingAs($this->admin);

    $response = $this->getJson("/api/officers/{$this->manager->id}/permissions");
    $response->assertOk()
        ->assertJsonStructure([
            'officer' => ['id', 'name', 'email', 'role', 'role_label'],
            'role_permissions',
            'overrides',
            'groups',
        ]);

    expect($response->json('officer.id'))->toBe($this->manager->id)
        ->and($response->json('officer.role'))->toBe('manager');
});

test('F018-5: admin updates officer overrides with allow and disallow effects', function () {
    $this->actingAs($this->admin);

    $payload = [
        'overrides' => [
            'backups.view' => 'allow',
            'members.delete' => 'disallow',
        ],
    ];

    $response = $this->putJson("/api/officers/{$this->manager->id}/permissions", $payload);
    $response->assertOk()
        ->assertJson(['message' => 'Officer permission overrides saved.']);

    $overrides = $response->json('overrides');
    expect($overrides['backups.view'])->toBe('allow')
        ->and($overrides['members.delete'])->toBe('disallow');

    // Verify database state
    $allowRow = OfficerPermissionOverride::where('user_id', $this->manager->id)
        ->where('permission', 'backups.view')
        ->first();
    expect($allowRow)->not->toBeNull()
        ->and($allowRow->effect)->toBe(OfficerPermissionOverride::EFFECT_ALLOW);

    $disallowRow = OfficerPermissionOverride::where('user_id', $this->manager->id)
        ->where('permission', 'members.delete')
        ->first();
    expect($disallowRow)->not->toBeNull()
        ->and($disallowRow->effect)->toBe(OfficerPermissionOverride::EFFECT_DISALLOW);
});

test('F018-6: officer permission overrides validate effect values', function () {
    $this->actingAs($this->admin);

    $response = $this->putJson("/api/officers/{$this->manager->id}/permissions", [
        'overrides' => [
            'backups.view' => 'invalid_effect',
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['overrides.backups.view']);
});

test('F018-7: permission overrides immediately alter the officer active capabilities', function () {
    // 1. Initial check: manager does NOT have backups.view
    expect($this->manager->can('backups.view'))->toBeFalse();

    // 2. Apply ALLOW override for backups.view
    OfficerPermissionOverride::create([
        'user_id' => $this->manager->id,
        'permission' => 'backups.view',
        'effect' => OfficerPermissionOverride::EFFECT_ALLOW,
    ]);
    OfficerPermissionOverride::forgetAllCachedEffects($this->manager->id);

    // 3. Manager now has backups.view via override
    expect($this->manager->fresh()->can('backups.view'))->toBeTrue();

    // 4. In API: calling /api/user reflects backups.view
    $userResponse = $this->actingAs($this->manager)->getJson('/api/user');
    $userResponse->assertOk();
    expect($userResponse->json('permissions'))->toContain('backups.view');
});

test('F018-8: disallow override immediately revokes a role-granted capability', function () {
    // 1. Manager has members.update in preset
    expect($this->manager->can('members.update'))->toBeTrue();

    // 2. Apply DISALLOW override for members.update
    OfficerPermissionOverride::create([
        'user_id' => $this->manager->id,
        'permission' => 'members.update',
        'effect' => OfficerPermissionOverride::EFFECT_DISALLOW,
    ]);
    OfficerPermissionOverride::forgetAllCachedEffects($this->manager->id);

    // 3. Manager can no longer update members
    expect($this->manager->fresh()->can('members.update'))->toBeFalse();

    // 4. Calling PUT /api/members/{id} as manager now returns 403 Forbidden
    $response = $this->actingAs($this->manager)->putJson("/api/members/{$this->member->id}", [
        'first_name' => 'ShouldBeBlocked',
    ]);
    $response->assertForbidden();
});

test('F018-9: GET /api/members with is_officer=1 lists only officer accounts', function () {
    $this->actingAs($this->admin);

    $response = $this->getJson('/api/members?is_officer=1');
    $response->assertOk()
        ->assertJsonStructure(['data']);

    $officers = $response->json('data');
    expect(count($officers))->toBeGreaterThan(0);

    // Verify all returned records have is_officer === true
    foreach ($officers as $officer) {
        expect((bool) $officer['is_officer'])->toBeTrue();
    }
});

test('F018-10: privilege escalation prevention — non-admin cannot assign admin role', function () {
    $this->actingAs($this->manager);

    $payload = [
        'first_name' => 'Hacker',
        'last_name' => 'Escalated',
        'birth_date' => '1990-01-01',
        'gender' => 'male',
        'civil_status' => 'single',
        'email' => 'hacker.admin@example.com',
        'phone_number' => '09171234567',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'admin',
    ];

    $response = $this->postJson('/api/members', $payload);
    $response->assertStatus(403);
});

test('F018-11: creating officer with valid officer role requires officers.create permission', function () {
    // Secretary lacks officers.create permission
    $this->actingAs($this->secretary);

    $payload = [
        'first_name' => 'New',
        'last_name' => 'LoanOfficer',
        'birth_date' => '1990-01-01',
        'gender' => 'male',
        'civil_status' => 'single',
        'email' => 'new.loan@example.com',
        'phone_number' => '09171234567',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'loan',
    ];

    $response = $this->postJson('/api/members', $payload);
    $response->assertStatus(403);
});
