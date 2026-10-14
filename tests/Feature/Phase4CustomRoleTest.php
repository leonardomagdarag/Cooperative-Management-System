<?php

use App\Models\PrivilegedActionRequest;
use App\Models\User;
use App\Services\CountersignService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $this->chairman = User::factory()->create(['role' => 'chairman', 'is_officer' => true]);
    $this->chairman->assignRole('chairman');
});

test('admin can assign a custom dynamic role to a user', function () {
    // Create custom role in Spatie
    Role::findOrCreate('compliance_officer', 'web');

    actingAs($this->admin);

    $oldestBirthDate = now()->subYears(25)->toDateString();

    $payload = [
        'first_name' => 'Carlos',
        'last_name' => 'Santana',
        'birth_date' => $oldestBirthDate,
        'gender' => 'male',
        'civil_status' => 'single',
        'email' => 'carlos.compliance@example.com',
        'phone_number' => '09171112233',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'compliance_officer',
    ];

    $response = $this->withSession([
        'verified_officer_email' => $payload['email'],
        'verified_officer_phone' => $payload['phone_number'],
    ])->postJson('/api/members', $payload)
        ->assertAccepted()
        ->assertJsonPath('status', 'pending');

    $createdUser = User::where('email', 'carlos.compliance@example.com')->firstOrFail();
    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));

    expect($createdUser->role)->toBe('inactive')
        ->and($createdUser->hasRole('inactive'))->toBeTrue()
        ->and($createdUser->hasRole('compliance_officer'))->toBeFalse();
    app(CountersignService::class)->approve($request, $this->chairman);
    expect($createdUser->fresh()->hasRole('compliance_officer'))->toBeTrue()
        ->and((bool) $createdUser->fresh()->is_officer)->toBeTrue();

    // Now update the user to another custom role
    Role::findOrCreate('auditor', 'web');

    $updateResponse = $this->putJson("/api/members/{$createdUser->id}", [
        'first_name' => 'Carlos',
        'last_name' => 'Santana',
        'birth_date' => $oldestBirthDate,
        'gender' => 'male',
        'civil_status' => 'single',
        'email' => 'carlos.compliance@example.com',
        'phone_number' => '09171112233',
        'role' => 'auditor',
    ])->assertAccepted()->assertJsonPath('status', 'pending');

    $updateRequest = PrivilegedActionRequest::findOrFail($updateResponse->json('request_id'));
    expect($createdUser->fresh()->role)->toBe('compliance_officer')
        ->and($createdUser->fresh()->hasRole('auditor'))->toBeFalse();
    app(CountersignService::class)->approve($updateRequest, $this->chairman);
    expect($createdUser->fresh()->role)->toBe('auditor')
        ->and($createdUser->fresh()->hasRole('auditor'))->toBeTrue();
});

test('non-existent role fails validation', function () {
    actingAs($this->admin);

    $oldestBirthDate = now()->subYears(25)->toDateString();

    $payload = [
        'first_name' => 'Invalid',
        'last_name' => 'RoleTest',
        'birth_date' => $oldestBirthDate,
        'gender' => 'male',
        'civil_status' => 'single',
        'email' => 'invalid.role@example.com',
        'phone_number' => '09179998877',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'non_existent_super_role',
    ];

    $this->postJson('/api/members', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});
