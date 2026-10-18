<?php

use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

test('react api login establishes an authenticated session', function () {
    $user = User::factory()->create([
        'email' => 'react-api@example.com',
        'password' => 'password',
        'role' => 'admin',
    ]);

    $this->get('/sanctum/csrf-cookie')->assertNoContent();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('user.email', $user->email);

    $this->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});

test('new member device login requires otp and blocks protected api access', function () {
    $user = User::factory()->create([
        'email' => 'new-device@example.com',
        'password' => 'password',
        'role' => 'member',
    ]);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('requires_otp', true)
        ->assertJsonPath('redirect', route('otp.challenge.show'));

    $this->getJson('/api/user')
        ->assertStatus(423)
        ->assertJsonPath('redirect', route('otp.challenge.show'));
});

test('login OTP bypass lets every user skip new-device verification when enabled', function () {
    config(['auth.login_otp_bypass' => true]);
    $user = User::factory()->create([
        'email' => 'otp-bypass-member@example.com',
        'password' => 'password',
        'role' => 'member',
    ]);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonMissingPath('requires_otp');
});

test('new-device login OTP is required for officer accounts when bypass is disabled', function () {
    config(['auth.login_otp_bypass' => false]);
    $user = User::factory()->create([
        'email' => 'otp-officer@example.com',
        'password' => 'password',
        'role' => 'admin',
    ]);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('requires_otp', true);
});

test('public announcement api returns json visibility records', function () {
    $creator = User::factory()->create();

    Announcement::factory()->create([
        'title' => 'Public API Announcement',
        'content' => 'This should be visible from the React news page.',
        'type' => Announcement::TYPE_ANNOUNCEMENT,
        'visibility' => [Announcement::VISIBILITY_PUBLIC],
        'created_by' => $creator->id,
    ]);

    $this->getJson('/api/announcements/public')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Public API Announcement')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'content', 'type', 'image_url', 'created_at', 'creator_name'],
            ],
            'pagination',
        ]);
});

test('seeded admin can access protected react api endpoints', function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();

    $this->actingAs($admin)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email', 'leo.baluyot039+admin@gmail.com')
        ->assertJsonPath('primary_role', 'admin');

    foreach ([
        '/api/dashboard',
        '/api/members',
        '/api/services',
        '/api/loan-products',
        '/api/ledger',
        '/api/announcements',
        '/api/logs',
        '/api/roles',
    ] as $endpoint) {
        $this->actingAs($admin)
            ->getJson($endpoint)
            ->assertOk();
    }
});
