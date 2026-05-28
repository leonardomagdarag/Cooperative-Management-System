<?php

use App\Livewire\Content\Settings;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

function makeMemberUser(): User
{
    Role::findOrCreate('member', 'web');

    $user = User::factory()->create(['role' => 'member']);
    $user->assignRole('member');

    return $user;
}

function makeOfficerUser(string $role = 'loan'): User
{
    Role::findOrCreate($role, 'web');

    $user = User::factory()->create(['role' => $role]);
    $user->assignRole($role);

    return $user;
}

beforeEach(function () {
    actingAs(makeMemberUser());
});

test('member settings shows the member specific content', function () {
    Livewire::test(Settings::class)
        ->assertSee('Account Settings')
        ->call('setMemberTab', 'privacy')
        ->assertSee('Privacy and information use')
        ->call('setMemberTab', 'security')
        ->assertSee('Change password');
});

test('officer settings can open their own account settings', function () {
    actingAs(makeOfficerUser());

    Livewire::test(Settings::class)
        ->assertSee('My Account Settings')
        ->call('openPersonalAccountSettings')
        ->assertSee('Manage your account, privacy, and security')
        ->assertSee('Back to Settings')
        ->call('backToSystemSettings')
        ->assertSee('Role Permission Management');
});

test('member profile settings can update their contact and personal data', function () {
    $user = auth()->user();

    Livewire::test(Settings::class)
        ->set('profileForm.first_name', 'Updated')
        ->set('profileForm.last_name', 'Member')
        ->set('profileForm.email', 'Updated.Member@Example.com')
        ->set('profileForm.phone_no', '09171234567')
        ->call('saveMemberProfile')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->email)->toBe('updated.member@example.com')
        ->and($user->personalData->first_name)->toBe('Updated')
        ->and($user->personalData->last_name)->toBe('Member')
        ->and($user->personalData->contact->phone_no)->toBe('09171234567');
});

test('member password change invalidates other active sessions and trusted devices', function () {
    $user = auth()->user();
    $currentSessionId = 'member-current-session';
    $otherSessionId = 'member-other-session';
    $originalRememberToken = (string) $user->remember_token;

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
        'fingerprint' => 'member-device-fingerprint',
        'device_label' => 'Member browser',
        'last_ip_address' => '127.0.0.2',
        'user_agent' => 'Other session browser',
        'trusted_until' => now()->addDays(30),
        'last_used_at' => now(),
        'is_active' => true,
    ]);

    Livewire::test(Settings::class)
        ->set('passwordForm.current_password', 'password')
        ->set('passwordForm.password', 'new-strong-password')
        ->set('passwordForm.password_confirmation', 'new-strong-password')
        ->call('changePassword')
        ->assertHasNoErrors();

    $user->refresh();
    $trustedDevice->refresh();

    expect(Hash::check('new-strong-password', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe($originalRememberToken)
        ->and(DB::table('sessions')->where('id', $otherSessionId)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBeLessThanOrEqual(1)
        ->and(auth()->id())->toBe($user->id)
        ->and($trustedDevice->is_active)->toBeFalse();
});
