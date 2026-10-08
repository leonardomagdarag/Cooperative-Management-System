<?php

use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->accountant = User::where('email', 'accountant@example.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@example.com')->firstOrFail();
});

test('F032-1: unauthenticated requests to /api/logs return 401', function () {
    $this->getJson('/api/logs')->assertUnauthorized();
});

test('F032-2: regular member receives 403 on /api/logs', function () {
    $this->actingAs($this->member)->getJson('/api/logs')->assertForbidden();
});

test('F032-3: officer without logs.view receives 403 on /api/logs', function () {
    $this->actingAs($this->accountant)->getJson('/api/logs')->assertForbidden();
});

test('F032-4: admin lists audit logs with pagination and filters metadata', function () {
    activity('security')
        ->causedBy($this->admin)
        ->event('login')
        ->log('Admin user logged in.');

    $response = $this->actingAs($this->admin)->getJson('/api/logs');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'log_name',
                    'description',
                    'event',
                    'subject_type',
                    'subject_id',
                    'causer',
                    'properties',
                    'created_at',
                ],
            ],
            'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
            'filters' => ['tags', 'events'],
        ]);
});

test('F032-5: audit logs filter by search query matching description or causer', function () {
    activity('system')
        ->causedBy($this->admin)
        ->event('config_update')
        ->log('UniqueSearchStringConfigUpdated');

    activity('auth')
        ->causedBy($this->admin)
        ->event('logout')
        ->log('Regular logout event');

    $this->actingAs($this->admin);

    $response = $this->getJson('/api/logs?search=UniqueSearchStringConfigUpdated');
    $response->assertOk();

    $descriptions = collect($response->json('data'))->pluck('description')->all();
    expect($descriptions)->toContain('UniqueSearchStringConfigUpdated')
        ->and($descriptions)->not->toContain('Regular logout event');
});

test('F032-6: audit logs filter by tag (log_name) and event', function () {
    activity('finance')
        ->causedBy($this->admin)
        ->event('transaction_approved')
        ->log('Deposit approved for member.');

    activity('security')
        ->causedBy($this->admin)
        ->event('password_reset')
        ->log('Password reset requested.');

    $this->actingAs($this->admin);

    // Filter tag=finance
    $tagRes = $this->getJson('/api/logs?tag=finance');
    $tagRes->assertOk();
    $tags = collect($tagRes->json('data'))->pluck('log_name')->unique()->all();
    expect($tags)->toEqual(['finance']);

    // Filter event=password_reset
    $eventRes = $this->getJson('/api/logs?event=password_reset');
    $eventRes->assertOk();
    $events = collect($eventRes->json('data'))->pluck('event')->unique()->all();
    expect($events)->toEqual(['password_reset']);
});

test('F032-7: audit logs filter by date_range presets', function () {
    $this->actingAs($this->admin);

    $resToday = $this->getJson('/api/logs?date_range=today');
    $resToday->assertOk();

    $resWeek = $this->getJson('/api/logs?date_range=week');
    $resWeek->assertOk();

    $resMonth = $this->getJson('/api/logs?date_range=month');
    $resMonth->assertOk();
});

test('F032-8: audit logs sort by created_at, event, and log_name', function () {
    $this->actingAs($this->admin);

    $resSortAsc = $this->getJson('/api/logs?sort=created_at&direction=asc');
    $resSortAsc->assertOk();

    $resSortEvent = $this->getJson('/api/logs?sort=event&direction=desc');
    $resSortEvent->assertOk();
});
