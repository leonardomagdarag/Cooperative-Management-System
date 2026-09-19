<?php

use App\Jobs\SendOtpEmailJob;
use App\Models\OtpChallenge;
use App\Models\SystemSetting;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\SecurityOtpService;
use App\Services\TurnstileService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);
});

test('revoking trusted devices sets is_active false and rotates remember_token', function () {
    $user = User::factory()->create([
        'role' => 'member',
        'account_status' => 'active',
        'remember_token' => 'original-token',
    ]);
    $user->syncRoles(['member']);

    TrustedDevice::create([
        'user_id' => $user->id,
        'fingerprint' => 'fp-abc-123',
        'device_label' => 'Chrome on Windows',
        'is_active' => true,
        'trusted_until' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->postJson('/api/settings/revoke-devices')
        ->assertOk()
        ->assertJsonPath('message', 'Trusted devices revoked.');

    expect(TrustedDevice::where('user_id', $user->id)->first()->is_active)->toBeFalse()
        ->and($user->fresh()->remember_token)->not->toBe('original-token')
        ->and(Activity::where('event', 'member.security.devices-revoked')->exists())->toBeTrue();
});

test('password change requires valid OTP and invalidates trusted devices', function () {
    Queue::fake();

    $user = User::factory()->create([
        'role' => 'member',
        'password' => bcrypt('CurrentPassword123!'),
        'account_status' => 'active',
    ]);
    $user->syncRoles(['member']);

    TrustedDevice::create([
        'user_id' => $user->id,
        'fingerprint' => 'fp-xyz',
        'device_label' => 'Safari on iPhone',
        'is_active' => true,
        'trusted_until' => now()->addDays(30),
    ]);

    // 1. Request OTP for password change
    $sendOtpRes = $this->actingAs($user)
        ->postJson('/api/settings/password/send-otp')
        ->assertOk()
        ->assertJsonStructure(['message', 'expires_at']);

    $challenge = OtpChallenge::where('user_id', $user->id)
        ->where('purpose', 'password_change')
        ->latest()
        ->first();

    expect($challenge)->not->toBeNull();

    // 2. Reject with invalid OTP code
    $this->actingAs($user)
        ->putJson('/api/settings/password', [
            'current_password' => 'CurrentPassword123!',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
            'otp_code' => '999999',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invalid or expired verification code.');

    // 3. Emulate correct OTP code verification
    $correctOtp = '123456';
    $challenge->update(['code_hash' => Hash::make($correctOtp)]);

    $this->actingAs($user)
        ->putJson('/api/settings/password', [
            'current_password' => 'CurrentPassword123!',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
            'otp_code' => $correctOtp,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Password updated. Trusted devices were revoked for safety.');

    // Password updated and trusted devices revoked
    expect(Hash::check('NewSecurePassword456!', $user->fresh()->password))->toBeTrue()
        ->and(TrustedDevice::where('user_id', $user->id)->first()->is_active)->toBeFalse()
        ->and(Activity::where('event', 'member.security.password-changed')->exists())->toBeTrue();
});

test('deactivating account changes status to inactive, revokes devices and clears session', function () {
    $user = User::factory()->create([
        'role' => 'member',
        'password' => bcrypt('MyPassword123!'),
        'account_status' => 'active',
    ]);
    $user->syncRoles(['member']);

    TrustedDevice::create([
        'user_id' => $user->id,
        'fingerprint' => 'device-1',
        'is_active' => true,
        'trusted_until' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->postJson('/api/settings/deactivate-account', [
            'current_password' => 'MyPassword123!',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Account deactivated.');

    expect($user->fresh()->account_status)->toBe('inactive')
        ->and(TrustedDevice::where('user_id', $user->id)->first()->is_active)->toBeFalse()
        ->and(Auth::check())->toBeFalse()
        ->and(Activity::where('event', 'member.account.deactivated')->exists())->toBeTrue();
});

test('inactive and terminated users cannot authenticate', function () {
    $inactiveUser = User::factory()->create([
        'email' => 'inactive@example.com',
        'password' => bcrypt('Secret123!'),
        'account_status' => 'inactive',
    ]);
    $inactiveUser->syncRoles(['member']);

    $this->postJson('/api/login', [
        'email' => 'inactive@example.com',
        'password' => 'Secret123!',
    ])->assertStatus(403);

    $terminatedUser = User::factory()->create([
        'email' => 'terminated@example.com',
        'password' => bcrypt('Secret123!'),
        'account_status' => 'terminated',
    ]);
    $terminatedUser->syncRoles(['member']);

    $this->postJson('/api/login', [
        'email' => 'terminated@example.com',
        'password' => 'Secret123!',
    ])->assertStatus(403);
});

test('turnstile service verifies token with Cloudflare API', function () {
    config(['services.turnstile.secret_key' => 'test-turnstile-secret']);

    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true], 200),
    ]);

    $turnstile = app(TurnstileService::class);
    expect($turnstile->verify('valid-token', '127.0.0.1'))->toBeTrue();

    // Verify empty token returns false immediately without HTTP request
    expect($turnstile->verify(null))->toBeFalse()
        ->and($turnstile->verify(''))->toBeFalse();
});
