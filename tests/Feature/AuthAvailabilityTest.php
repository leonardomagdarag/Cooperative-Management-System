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
        'service_registration_enabled' => true,
        'service_registration_member_enabled' => true,
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

    $this->get('/login')->assertRedirect(route('home_page', absolute: false));
    post('/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertRedirect(route('home_page', absolute: false));

    seedAuthAvailability(['service_login_enabled' => true]);
    $this->get('/login')->assertOk();
});

test('global registration availability drives the public registration route', function () {
    seedAuthAvailability(['service_registration_enabled' => false]);

    $this->get('/register')->assertRedirect(route('home_page', absolute: false));

    seedAuthAvailability(['service_registration_enabled' => true]);
    $this->get('/register')->assertOk();
});

test('member login is blocked when member login availability is disabled', function () {
    seedAuthAvailability(['service_login_member_enabled' => false]);

    $response = postJson('/api/login', [
        'email' => 'arjay.ramos@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(403)
        ->assertJsonStructure(['message']);

    expect(auth()->check())->toBeFalse();
});

test('officer login is blocked when officer login availability is disabled', function () {
    seedAuthAvailability(['service_login_officer_enabled' => false]);

    $response = postJson('/api/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(403)
        ->assertJsonStructure(['message']);

    expect(auth()->check())->toBeFalse();
});

test('officer login still works when only member login availability is disabled', function () {
    seedAuthAvailability(['service_login_member_enabled' => false]);

    $response = postJson('/api/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true);
});

test('member login still works when only officer login availability is disabled', function () {
    seedAuthAvailability(['service_login_officer_enabled' => false]);

    $response = postJson('/api/login', [
        'email' => 'arjay.ramos@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonMissing(['message' => 'Login is currently unavailable for your account. Please try again later.']);
});

test('public content availability reflects the registration and login toggles', function () {
    seedAuthAvailability([
        'service_registration_member_enabled' => false,
        'service_login_officer_enabled' => false,
    ]);

    $this->get('/api/public-content')
        ->assertOk()
        ->assertJsonPath('availability.registration.global', true)
        ->assertJsonPath('availability.registration.member', false)
        ->assertJsonPath('availability.login.officer', false)
        ->assertJsonPath('availability.login.member', true);
});

test('seeded system setting defaults enable registration and login for everyone', function () {
    expect((bool) SystemSetting::get('service_registration_enabled', true))->toBeTrue()
        ->and((bool) SystemSetting::get('service_registration_member_enabled', true))->toBeTrue()
        ->and((bool) SystemSetting::get('service_login_enabled', true))->toBeTrue()
        ->and((bool) SystemSetting::get('service_login_member_enabled', true))->toBeTrue()
        ->and((bool) SystemSetting::get('service_login_officer_enabled', true))->toBeTrue();
});

test('role users exist for the seeded auth availability tests', function () {
    expect(User::where('email', 'arjay.ramos@example.com')->first())->not->toBeNull()
        ->and(User::where('email', 'admin@example.com')->first())->not->toBeNull();
});
