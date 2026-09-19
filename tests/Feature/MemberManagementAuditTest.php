<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\PersonalData;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->secretary = User::where('email', 'secretary@example.com')->firstOrFail();
    $this->manager = User::where('email', 'manager@example.com')->firstOrFail();

    $this->mainBranch = Branch::where('code', 'BR000001')->firstOrFail();
    $this->officeBranch = Branch::where('code', 'BR000002')->firstOrFail();

    $this->mainMember = User::where('user_number', 'USR000007')->firstOrFail();
    $this->officeMember = User::where('user_number', 'USR000012')->firstOrFail();

    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
});

test('F017-1: unauthenticated requests to member endpoints return 401', function () {
    $this->getJson('/api/members')->assertUnauthorized();
    $this->getJson("/api/members/{$this->mainMember->id}")->assertUnauthorized();
    $this->postJson('/api/members', [])->assertUnauthorized();
    $this->putJson("/api/members/{$this->mainMember->id}", [])->assertUnauthorized();
    $this->deleteJson("/api/members/{$this->mainMember->id}")->assertUnauthorized();
    $this->postJson("/api/members/{$this->mainMember->id}/toggle-special-loan")->assertUnauthorized();
    $this->postJson('/api/members/export', [])->assertUnauthorized();
});

test('F017-2: regular member without members.view receives 403 on member endpoints', function () {
    $this->actingAs($this->mainMember);

    $this->getJson('/api/members')->assertForbidden();
    $this->postJson('/api/members', [])->assertForbidden();
    $this->putJson("/api/members/{$this->officeMember->id}", [])->assertForbidden();
    $this->postJson("/api/members/{$this->officeMember->id}/toggle-special-loan")->assertForbidden();
    $this->postJson('/api/members/export', [])->assertForbidden();
});

test('F017-3: regular member accessing GET /api/members/{id} views self and is forbidden from others (Fixed F017-02)', function () {
    $this->actingAs($this->mainMember);

    // Self access returns 200 OK
    $responseSelf = $this->getJson("/api/members/{$this->mainMember->id}");
    $responseSelf->assertOk();

    // Access to other member returns 403 Forbidden (not 500 crash)
    $responseOther = $this->getJson("/api/members/{$this->officeMember->id}");
    $responseOther->assertStatus(403);
});

test('F017-4: admin can list members with status filtering and search', function () {
    $this->actingAs($this->admin);

    // All active members
    $response = $this->getJson('/api/members?status=active');
    $response->assertOk()
        ->assertJsonStructure(['data']);
    expect(count($response->json('data')))->toBeGreaterThan(0);

    // Search by name
    $searchResponse = $this->getJson('/api/members?search=Arjay');
    $searchResponse->assertOk();
    $names = collect($searchResponse->json('data'))->pluck('user_number');
    expect($names)->toContain('USR000007');
});

test('F017-5: admin views full member details including personal, accounts, and contact', function () {
    $this->actingAs($this->admin);

    $response = $this->getJson("/api/members/{$this->mainMember->id}");
    $response->assertOk()
        ->assertJsonStructure([
            'id',
            'user_number',
            'email',
            'account_status',
            'personal_data' => ['first_name', 'last_name', 'gender'],
            'contact' => ['phone_no', 'email'],
            'accounts',
        ]);
});

test('F017-6: admin creates new member with personal data, contact, address and accounts', function () {
    $this->actingAs($this->admin);

    $email = 'brandnew.member@example.com';
    $payload = [
        'first_name' => 'Brand',
        'last_name' => 'NewMember',
        'mid_name' => 'Middle',
        'birth_date' => '1995-05-10',
        'gender' => 'male',
        'civil_status' => 'single',
        'email' => $email,
        'phone_number' => '09191234567',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'street' => '100 Mabini St',
        'city' => 'Manila',
        'branch_id' => $this->mainBranch->id,
        'role' => 'member',
    ];

    $response = $this->postJson('/api/members', $payload);
    $response->assertStatus(201)
        ->assertJson(['message' => 'Member created successfully.']);

    $createdUser = User::where('email', $email)->first();
    expect($createdUser)->not->toBeNull()
        ->and($createdUser->email)->toBe($email)
        ->and($createdUser->account_status)->toBe('active')
        ->and($createdUser->hasRole('member'))->toBeTrue();

    // Verify savings and share-capital accounts created automatically
    expect($createdUser->accounts()->where('account_type', 'savings')->exists())->toBeTrue()
        ->and($createdUser->accounts()->where('account_type', 'share-capital')->exists())->toBeTrue();
});

test('F017-7: admin can update an existing member', function () {
    $this->actingAs($this->admin);

    $payload = [
        'first_name' => 'ArjayUpdated',
        'last_name' => 'RamosUpdated',
        'phone_number' => '09189998877',
        'email' => 'arjay.updated@example.com',
    ];

    $response = $this->putJson("/api/members/{$this->mainMember->id}", $payload);
    $response->assertOk()
        ->assertJson(['message' => 'Member updated successfully.']);

    $this->mainMember->refresh();
    expect($this->mainMember->email)->toBe('arjay.updated@example.com');
});

test('F017-8: admin toggles special loan eligibility', function () {
    $this->actingAs($this->admin);

    $initialStatus = (bool) $this->mainMember->special_loan_eligible;

    $response = $this->postJson("/api/members/{$this->mainMember->id}/toggle-special-loan");
    $response->assertOk()
        ->assertJsonPath('special_loan_eligible', ! $initialStatus);

    $this->mainMember->refresh();
    expect((bool) $this->mainMember->special_loan_eligible)->toBe(! $initialStatus);

    // Verify activity log was recorded
    $log = Activity::where('event', 'member.special-loan-eligibility')
        ->where('subject_id', $this->mainMember->id)
        ->first();
    expect($log)->not->toBeNull();
});

test('F017-9: admin triggers password change OTP challenge for a member', function () {
    $this->actingAs($this->admin);

    $response = $this->postJson("/api/members/{$this->mainMember->id}/password/send-otp");
    $response->assertOk()
        ->assertJsonStructure(['message', 'expires_at']);
});

test('F017-10: member export validates dates and generates export response', function () {
    $this->actingAs($this->admin);

    // Invalid date range: end_date before start_date
    $invalidResponse = $this->postJson('/api/members/export', [
        'export_type' => 'excel',
        'start_date' => '2026-06-01',
        'end_date' => '2026-05-01',
    ]);
    $invalidResponse->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);

    // Valid date range (excel)
    $validResponse = $this->postJson('/api/members/export', [
        'export_type' => 'excel',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $validResponse->assertOk();
});

test('F017-11: admin can soft-delete member using DELETE /api/members/{id} (Fixed F017-01)', function () {
    $this->actingAs($this->admin);

    $response = $this->deleteJson("/api/members/{$this->mainMember->id}");
    $response->assertOk()
        ->assertJson(['message' => 'Member deleted.']);
    $this->assertSoftDeleted('users', ['id' => $this->mainMember->id]);
});

test('F017-12: admin restore on soft-deleted member and force-destroy on member without accounts', function () {
    $this->actingAs($this->admin);

    // 1. Soft-delete directly via Eloquent model to bypass the destroy controller bug
    $this->mainMember->delete();
    $this->assertSoftDeleted('users', ['id' => $this->mainMember->id]);

    // 2. Restore
    $restoreResponse = $this->postJson("/api/members/{$this->mainMember->id}/restore");
    $restoreResponse->assertOk()
        ->assertJson(['message' => 'Member restored.']);
    $this->assertNotSoftDeleted('users', ['id' => $this->mainMember->id]);

    // 3. Force-destroy on an archived member without accounts succeeds
    $target = User::factory()->create([
        'email' => 'noaccounts.member@example.com',
        'account_status' => 'inactive',
    ]);
    $target->assignRole('member');
    $target->delete();

    $forceResponse = $this->deleteJson("/api/members/{$target->id}/force");
    $forceResponse->assertOk()
        ->assertJson(['message' => 'Member permanently deleted.']);
    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});

test('F017-13: forceDestroy on member with accounts succeeds cleanly (Fixed F017-03)', function () {
    $this->actingAs($this->admin);

    // mainMember has accounts (created by AccountSeeder)
    $this->mainMember->delete();

    $forceResponse = $this->deleteJson("/api/members/{$this->mainMember->id}/force");
    $forceResponse->assertOk()
        ->assertJson(['message' => 'Member permanently deleted.']);
    $this->assertDatabaseMissing('users', ['id' => $this->mainMember->id]);
});
