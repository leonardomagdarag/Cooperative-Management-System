<?php

use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->manager = User::where('email', 'manager@example.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@example.com')->firstOrFail();
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

    $response = $this->postJson('/api/members', $payload)
        ->assertStatus(201);

    $createdUser = User::where('email', 'carlos.compliance@example.com')->firstOrFail();

    expect($createdUser->role)->toBe('compliance_officer')
        ->and($createdUser->hasRole('compliance_officer'))->toBeTrue()
        ->and((bool) $createdUser->is_officer)->toBeTrue();

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
    ])->assertOk();

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
