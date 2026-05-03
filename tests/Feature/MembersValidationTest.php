<?php

use App\Livewire\Content\Members;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

test('member login tab renders password guidance and strength meter', function () {
    Livewire::test(Members::class)
        ->call('addMember')
        ->assertSee('Password guidance')
        ->assertSee('Strength:')
        ->assertSeeHtml('id="member-password-strength-bar"');
});

test('member password rejects predictable name plus digits', function () {
    Livewire::test(Members::class)
        ->call('addMember')
        ->set('form.first_name', 'John')
        ->set('form.last_name', 'Doe')
        ->set('form.password', 'John12345')
        ->call('validateField', 'form.password')
        ->assertHasErrors(['form.password']);
});

test('password confirmation errors stay on the confirmation field', function () {
    Livewire::test(Members::class)
        ->call('addMember')
        ->set('form.password', 'Strong12!')
        ->set('form.password_confirmation', 'Mismatch34!')
        ->call('validateField', 'form.password_confirmation')
        ->assertHasErrors(['form.password_confirmation'])
        ->assertHasNoErrors(['form.password']);
});

test('birth date validation uses the configured minimum age', function () {
    config()->set('settings.requirement.age', 21);

    Livewire::test(Members::class)
        ->call('addMember')
        ->set('form.birth_date', now()->subYears(20)->format('Y-m-d'))
        ->call('validateField', 'form.birth_date')
        ->assertHasErrors(['form.birth_date']);
});

test('birth date passes when it meets the configured minimum age', function () {
    config()->set('settings.requirement.age', 21);

    Livewire::test(Members::class)
        ->call('addMember')
        ->set('form.birth_date', now()->subYears(21)->format('Y-m-d'))
        ->call('validateField', 'form.birth_date')
        ->assertHasNoErrors(['form.birth_date']);
});

test('edit mode allows blank member passwords', function () {
    Livewire::test(Members::class)
        ->call('addMember')
        ->set('isEdit', true)
        ->set('form.id', 1)
        ->set('form.password', '')
        ->set('form.password_confirmation', '')
        ->call('validateField', 'form.password')
        ->assertHasNoErrors(['form.password', 'form.password_confirmation']);
});

test('opening a member profile closes the add member modal', function () {
    $member = User::factory()->create(['role' => 'member']);

    Livewire::test(Members::class)
        ->call('addMember')
        ->assertSet('showEditModal', true)
        ->call('viewProfile', $member->id)
        ->assertSet('showEditModal', false)
        ->assertSet('showModal', true);
});

test('opening the add member modal closes the member profile modal', function () {
    $member = User::factory()->create(['role' => 'member']);

    Livewire::test(Members::class)
        ->call('viewProfile', $member->id)
        ->assertSet('showModal', true)
        ->call('addMember')
        ->assertSet('showModal', false)
        ->assertSet('showEditModal', true);
});
