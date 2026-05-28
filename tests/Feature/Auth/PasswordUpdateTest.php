<?php

use App\Models\User;
use App\Models\TrustedDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

test('password can be updated', function () {
    $user = User::factory()->create();
    $currentSessionId = 'profile-current-session';
    $otherSessionId = 'profile-other-session';

    session()->setId($currentSessionId);
    session()->start();

    DB::table('sessions')->insert([
        [
            'id' => $currentSessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Current session',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => $otherSessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.2',
            'user_agent' => 'Other session',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ],
    ]);

    $trustedDevice = TrustedDevice::query()->create([
        'user_id' => $user->id,
        'fingerprint' => 'profile-password-device',
        'device_label' => 'Saved browser',
        'last_ip_address' => '127.0.0.2',
        'user_agent' => 'Saved browser agent',
        'trusted_until' => now()->addDays(30),
        'last_used_at' => now(),
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    expect(DB::table('sessions')->where('id', $otherSessionId)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1);

    $trustedDevice->refresh();
    expect($trustedDevice->is_active)->toBeFalse();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', 'current_password')
        ->assertRedirect('/profile');
});
