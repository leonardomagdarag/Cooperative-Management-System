<?php

use App\Livewire\Content\Settings;
use App\Models\User;
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
