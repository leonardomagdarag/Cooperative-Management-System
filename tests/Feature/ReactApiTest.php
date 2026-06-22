<?php

use App\Models\Announcement;
use App\Models\User;

test('react api login establishes an authenticated session', function () {
    $user = User::factory()->create([
        'email' => 'react-api@example.com',
        'password' => 'password',
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
    $this->seed(\Database\Seeders\SpatieRolePermissionSeeder::class);
    $this->seed(\Database\Seeders\UserSeeder::class);

    $admin = User::where('email', 'admin@example.com')->firstOrFail();

    $this->actingAs($admin)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email', 'admin@example.com')
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
