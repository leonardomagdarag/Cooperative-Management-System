<?php

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;

use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(SystemSettingSeeder::class);
    $this->seed(UserSeeder::class);
});

function seedAuthAvailability(array $overrides = []): void
{
    $defaults = [
        'service_login_enabled' => true,
        'service_login_member_enabled' => true,
        'service_login_officer_enabled' => true,
    ];

    foreach (array_replace($defaults, $overrides) as $key => $value) {
        SystemSetting::set($key, (bool) $value);
    }
}

test('global login availability drives the public login route', function () {
    seedAuthAvailability(['service_login_enabled' => false]);

    // The maintenance page replaces the old silent redirect to the home page.
    $this->get('/login')
        ->assertStatus(503)
        ->assertSee('Sign-in is temporarily unavailable');

    post('/login', ['email' => 'leo.baluyot039+admin@gmail.com', 'password' => 'password'])
        ->assertStatus(503);

    seedAuthAvailability(['service_login_enabled' => true]);
    $this->get('/login')->assertOk();
});

test('public registration stays unavailable regardless of legacy settings', function () {
    SystemSetting::set('service_registration_enabled', true);
    SystemSetting::set('service_registration_member_enabled', true);
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});

test('member login is blocked when member login availability is disabled', function () {
    seedAuthAvailability(['service_login_member_enabled' => false]);

    $response = postJson('/api/login', [
        'email' => 'leo.baluyot039+arjay.ramos@gmail.com',
        'password' => 'password',
    ]);

    $response->assertStatus(403)
        ->assertJsonStructure(['message']);

    expect(auth()->check())->toBeFalse();
});

test('officer login is blocked when officer login availability is disabled', function () {
    seedAuthAvailability(['service_login_officer_enabled' => false]);

    $response = postJson('/api/login', [
        'email' => 'leo.baluyot039+admin@gmail.com',
        'password' => 'password',
    ]);

    $response->assertStatus(403)
        ->assertJsonStructure(['message']);

    expect(auth()->check())->toBeFalse();
});

test('officer login still works when only member login availability is disabled', function () {
    seedAuthAvailability(['service_login_member_enabled' => false]);

    $response = postJson('/api/login', [
        'email' => 'leo.baluyot039+admin@gmail.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true);
});

test('member login still works when only officer login availability is disabled', function () {
    seedAuthAvailability(['service_login_officer_enabled' => false]);

    $response = postJson('/api/login', [
        'email' => 'leo.baluyot039+arjay.ramos@gmail.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonMissing(['message' => 'Login is currently unavailable for your account. Please try again later.']);
});

test('public content availability reflects login toggles', function () {
    seedAuthAvailability([
        'service_login_officer_enabled' => false,
    ]);

    $this->get('/api/public-content')
        ->assertOk()
        ->assertJsonMissingPath('availability.registration')
        ->assertJsonPath('availability.login.officer', false)
        ->assertJsonPath('availability.login.member', true);
});

test('seeded system setting defaults enable login for everyone', function () {
    expect((bool) SystemSetting::get('service_login_enabled', true))->toBeTrue()
        ->and((bool) SystemSetting::get('service_login_member_enabled', true))->toBeTrue()
        ->and((bool) SystemSetting::get('service_login_officer_enabled', true))->toBeTrue();
});

test('role users exist for the seeded auth availability tests', function () {
    expect(User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->first())->not->toBeNull()
        ->and(User::where('email', 'leo.baluyot039+admin@gmail.com')->first())->not->toBeNull();
});
