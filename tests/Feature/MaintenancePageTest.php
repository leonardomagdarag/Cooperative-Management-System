<?php

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\MaintenanceState;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(SystemSettingSeeder::class);
    $this->seed(UserSeeder::class);

    MaintenanceState::leave();
});

afterEach(function () {
    // The state file is a real file in storage/app, shared by every test in the
    // suite, so it has to go whether the test passed or not.
    MaintenanceState::leave();

    if (app()->maintenanceMode()->active()) {
        Artisan::call('up');
    }
});

test('the maintenance route serves the page even when nothing is wrong', function () {
    $this->get('/maintenance')
        ->assertOk()
        ->assertSee('Scheduled maintenance');
});

test('the page never reaches for the database or the compiled assets', function () {
    $response = $this->get('/maintenance')->assertOk();

    // A restore replaces the database and the dev build may be absent, so the
    // page must not depend on either. A @vite tag or a session read would show
    // up here.
    expect($response->getContent())
        ->not->toContain('/build/assets/')
        ->not->toContain('laravel_session')
        ->not->toContain('@vite');
});

test('disabling login globally shows the maintenance page instead of the login form', function () {
    SystemSetting::set('service_login_enabled', false);

    $this->get('/login')
        ->assertStatus(503)
        ->assertSee('Sign-in is temporarily unavailable');

    expect(MaintenanceState::current()['reason'])->toBe(MaintenanceState::REASON_LOGIN);
});

test('the api login endpoint honours the global switch', function () {
    SystemSetting::set('service_login_enabled', false);

    // /api/login carries only ['web', 'throttle'], so EnsurePublicLoginEnabled
    // never ran for it and the global switch used to be advisory.
    $this->postJson('/api/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])
        ->assertStatus(403)
        ->assertJsonPath('maintenance', true)
        ->assertJsonPath('reason', MaintenanceState::REASON_LOGIN)
        ->assertJsonPath('redirect', '/maintenance');

    expect(auth()->check())->toBeFalse();
});

test('a member-only block names the audience and points at the page', function () {
    SystemSetting::set('service_login_member_enabled', false);

    $this->postJson('/api/login', [
        'email' => 'arjay.ramos@example.com',
        'password' => 'password',
    ])
        ->assertStatus(403)
        ->assertJsonPath('maintenance', true)
        ->assertJsonPath('audience', MaintenanceState::AUDIENCE_MEMBER);

    expect(MaintenanceState::current()['audience'])->toBe(MaintenanceState::AUDIENCE_MEMBER);
    $this->get('/maintenance')
        ->assertStatus(503)
        ->assertSee('Member sign-in is temporarily unavailable')
        ->assertSee('Cooperative officers are not affected');
});

test('officers are unaffected when only member login is disabled', function () {
    SystemSetting::set('service_login_member_enabled', false);

    $this->postJson('/api/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->assertOk();

    expect(MaintenanceState::isActive())->toBeFalse();
});

test('re-enabling sign-in clears the recorded notice', function () {
    SystemSetting::set('service_login_member_enabled', false);
    $this->get('/maintenance')->assertSee('Member sign-in is temporarily unavailable');

    $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail())
        ->putJson('/api/settings/service-availability', [
            'services' => [
                'login' => ['global' => true, 'member' => true, 'officer' => true],
            ],
            'notify_affected' => false,
        ])
        ->assertOk();

    expect(MaintenanceState::isActive())->toBeFalse();
});

test('maintenance mode serves the restore copy on every route', function () {
    MaintenanceState::enter(MaintenanceState::REASON_RESTORE);
    Artisan::call('down');

    foreach (['/', '/login', '/dashboard'] as $path) {
        $this->get($path)
            ->assertStatus(503)
            ->assertSee('We are restoring your data');
    }

    Artisan::call('up');

    $this->get('/')->assertOk();
});

test('a plain artisan down falls back to generic copy', function () {
    Artisan::call('down');

    $this->get('/')
        ->assertStatus(503)
        ->assertSee('Scheduled maintenance')
        ->assertDontSee('We are restoring your data');
});

test('the page refreshes itself and offers a retry', function () {
    MaintenanceState::enter(MaintenanceState::REASON_RESTORE, ['retry_after' => 30]);

    $response = $this->get('/maintenance')->assertStatus(503);

    expect($response->getContent())
        ->toContain('http-equiv="refresh" content="30"')
        ->toContain('Try again')
        ->toContain('checks back every 30 seconds');
});

test('a stale notice from a crashed restore reads as a plain outage', function () {
    // A process that died between entering maintenance mode and leaving it
    // leaves the file behind; claiming to be restoring forever would be a lie.
    MaintenanceState::enter(MaintenanceState::REASON_RESTORE);
    file_put_contents(
        storage_path('app/maintenance-state.json'),
        json_encode([
            'reason' => MaintenanceState::REASON_RESTORE,
            'audience' => null,
            'retry_after' => 15,
            'started_at' => now()->subDay()->toIso8601String(),
        ]),
    );

    // The stale entry is discarded as debris and sign-in is working, so the page
    // answers 200 with generic copy: claiming to be restoring would be a lie.
    $this->get('/maintenance')
        ->assertOk()
        ->assertSee('Scheduled maintenance')
        ->assertDontSee('We are restoring your data');
});

test('a corrupt state file does not break the page', function () {
    file_put_contents(storage_path('app/maintenance-state.json'), '{not json');

    expect(MaintenanceState::current())->toBeNull();

    $this->get('/maintenance')
        ->assertOk()
        ->assertSee('Scheduled maintenance');
});

test('repeating a login block keeps the original timestamp', function () {
    SystemSetting::set('service_login_enabled', false);

    $this->get('/maintenance');
    $first = MaintenanceState::current()['started_at'];

    $this->travel(5)->minutes();
    $this->get('/maintenance');

    expect(MaintenanceState::current()['started_at'])->toBe($first);
});
