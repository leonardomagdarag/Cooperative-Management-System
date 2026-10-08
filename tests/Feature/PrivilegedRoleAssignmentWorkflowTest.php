<?php

use App\Models\PrivilegedActionRequest;
use App\Models\User;
use App\Services\CountersignService;
use Database\Seeders\SpatieRolePermissionSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->admin = User::factory()->create(['role' => 'admin', 'is_officer' => true]);
    $this->admin->assignRole('admin');
    $this->chairman = User::factory()->create(['role' => 'chairman', 'is_officer' => true]);
    $this->chairman->assignRole('chairman');
    $this->applicant = User::factory()->create(['role' => 'member']);
    $this->applicant->assignRole('member');
});

function requestPrivilegedRoleChange($test, User $target)
{
    return $test->actingAs($test->admin)->putJson("/api/members/{$target->id}", [
        'first_name' => 'Role',
        'last_name' => 'Applicant',
        'birth_date' => '1990-01-01',
        'gender' => 'male',
        'civil_status' => 'single',
        'email' => $target->email,
        'role' => 'chairman',
    ]);
}

test('privileged role stays unchanged until chairman approves the request', function () {
    $response = requestPrivilegedRoleChange($this, $this->applicant);
    $response->assertAccepted()->assertJsonPath('status', 'pending');

    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));
    expect($request->action_type)->toBe('role.assign')
        ->and($request->status)->toBe('pending')
        ->and($this->applicant->fresh()->hasRole('member'))->toBeTrue()
        ->and($this->applicant->fresh()->hasRole('chairman'))->toBeFalse();

    app(CountersignService::class)->approve($request, $this->chairman);

    expect($request->fresh()->status)->toBe('executed')
        ->and($this->applicant->fresh()->hasRole('chairman'))->toBeTrue()
        ->and($this->applicant->fresh()->hasRole('member'))->toBeFalse()
        ->and($this->applicant->fresh()->account_status)->toBe('active');
});

test('rejected privileged role request leaves the applicant role unchanged', function () {
    $response = requestPrivilegedRoleChange($this, $this->applicant);
    $response->assertAccepted();

    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));
    app(CountersignService::class)->reject($request, $this->chairman, 'Role assignment not approved.');

    expect($request->fresh()->status)->toBe('rejected')
        ->and($this->applicant->fresh()->hasRole('member'))->toBeTrue()
        ->and($this->applicant->fresh()->hasRole('chairman'))->toBeFalse();
});

test('new privileged account remains inactive and unassigned until chairman approval', function () {
    $payload = [
        'first_name' => 'New',
        'last_name' => 'Chairman',
        'birth_date' => '1990-01-01',
        'gender' => 'female',
        'civil_status' => 'single',
        'email' => 'new.chairman@example.com',
        'phone_number' => '09171234567',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'chairman',
    ];

    $response = $this->actingAs($this->admin)
        ->withSession([
            'verified_officer_email' => $payload['email'],
            'verified_officer_phone' => $payload['phone_number'],
        ])
        ->postJson('/api/members', $payload)
        ->assertAccepted()
        ->assertJsonPath('status', 'pending');

    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));
    $newUser = User::where('email', $payload['email'])->firstOrFail();
    expect($newUser->account_status)->toBe('inactive')
        ->and($newUser->hasRole('inactive'))->toBeTrue()
        ->and($newUser->hasRole('chairman'))->toBeFalse();

    app(CountersignService::class)->approve($request, $this->chairman);

    expect($newUser->fresh()->account_status)->toBe('active')
        ->and($newUser->fresh()->hasRole('chairman'))->toBeTrue()
        ->and($newUser->fresh()->hasRole('inactive'))->toBeFalse();
});

test('chairman bootstrap refuses an admin and replaces other roles on an eligible account', function () {
    $this->chairman->syncRoles(['member']);
    $this->chairman->forceFill(['role' => 'member'])->save();

    $this->artisan('oversight:bootstrap-chairman', ['--email' => $this->admin->email])->assertExitCode(1);
    expect(User::role('chairman')->exists())->toBeFalse()
        ->and($this->admin->fresh()->hasRole('admin'))->toBeTrue();

    $this->artisan('oversight:bootstrap-chairman', ['--email' => $this->applicant->email])->assertExitCode(0);
    expect($this->applicant->fresh()->getRoleNames()->all())->toBe(['chairman'])
        ->and($this->applicant->fresh()->role)->toBe('chairman');
});
