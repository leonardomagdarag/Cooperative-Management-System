<?php

use App\Livewire\Content\Officers;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

function makeAdminUser(): User
{
    $user = User::factory()->create(['role' => 'admin']);

    Role::findOrCreate('admin', 'web');
    $user->assignRole('admin');

    return $user;
}

beforeEach(function () {
    actingAs(makeAdminUser());
});

test('officer login tab renders shared password guidance and strength meter', function () {
    Livewire::test(Officers::class)
        ->call('addOfficer')
        ->assertSee('Password guidance')
        ->assertSee('Strength:')
        ->assertSeeHtml('id="officer-password-strength-bar"')
        ->assertSeeHtml('data-password-form="officer"')
        ->assertSeeHtml('minlength="6"');
});

test('opening an officer profile closes the add officer modal', function () {
    $officer = User::factory()->create(['role' => 'manager']);

    Livewire::test(Officers::class)
        ->call('addOfficer')
        ->assertSet('showEditModal', true)
        ->call('viewProfile', $officer->id)
        ->assertSet('showEditModal', false)
        ->assertSet('showModal', true);
});

test('officer password rejects predictable name plus digits', function () {
    Livewire::test(Officers::class)
        ->call('addOfficer')
        ->set('form.first_name', 'John')
        ->set('form.last_name', 'Doe')
        ->set('form.password', 'John12345')
        ->call('validateField', 'form.password')
        ->assertHasErrors(['form.password']);
});

    test('officer password requires letters and numbers', function () {
        Livewire::test(Officers::class)
        ->call('addOfficer')
        ->set('form.password', 'abcdef')
        ->call('validateField', 'form.password')
        ->assertHasErrors(['form.password']);
    });

test('officer password confirmation errors stay on the confirmation field', function () {
    Livewire::test(Officers::class)
        ->call('addOfficer')
        ->set('form.password', 'Strong12!')
        ->set('form.password_confirmation', 'Mismatch34!')
        ->call('validateField', 'form.password_confirmation')
        ->assertHasErrors(['form.password_confirmation'])
        ->assertHasNoErrors(['form.password']);
});

test('officer birth date uses the configured minimum age', function () {
    config()->set('settings.requirement.age', 21);

    Livewire::test(Officers::class)
        ->call('addOfficer')
        ->set('form.birth_date', now()->subYears(20)->format('Y-m-d'))
        ->call('validateField', 'form.birth_date')
        ->assertHasErrors(['form.birth_date']);
});

test('officer birth date passes when it meets the configured minimum age', function () {
    /** @var User $user */
    $user = makeAdminUser();
    actingAs($user);

    config()->set('settings.requirement.age', 21);

    Livewire::test(Officers::class)
        ->call('addOfficer')
        ->set('form.birth_date', now()->subYears(21)->format('Y-m-d'))
        ->call('validateField', 'form.birth_date')
        ->assertHasNoErrors(['form.birth_date']);
});

test('add-mode next step advances from personal to contact', function () {
    /** @var User $user */
    $user = makeAdminUser();
    actingAs($user);

    Livewire::test(Officers::class)
        ->call('addOfficer')
        ->set('form.first_name', 'Maria')
        ->set('form.last_name', 'Santos')
        ->set('form.birth_date', now()->subYears(25)->format('Y-m-d'))
        ->set('form.gender', 'female')
        ->set('form.civil_status', 'single')
        ->set('form.title', 'Secretary')
        ->call('nextStep', 'personal')
        ->assertDispatched('wizard-advance');
});

test('edit mode allows blank officer passwords', function () {
    /** @var User $user */
    $user = makeAdminUser();
    actingAs($user);

    Livewire::test(Officers::class)
        ->call('addOfficer')
        ->set('isEdit', true)
        ->set('form.password', '')
        ->set('form.password_confirmation', '')
        ->call('validateTab', 'login')
        ->assertHasNoErrors(['form.password', 'form.password_confirmation', 'form.current_password']);
});

test('edit mode requires current password when changing password without officer update permission', function () {
    Permission::findOrCreate('officers.create', 'web');

    $role = Role::findOrCreate('officer-create-only', 'web');
    $role->syncPermissions(['officers.create']);

    /** @var User $user */
    $user = User::factory()->create(['role' => 'manager']);
    $user->assignRole($role);
    actingAs($user);

    Livewire::test(Officers::class)
        ->call('addOfficer')
        ->set('isEdit', true)
        ->set('form.password', 'Strong12!')
        ->set('form.password_confirmation', 'Strong12!')
        ->call('validateTab', 'login')
        ->assertHasErrors(['form.current_password']);
});
