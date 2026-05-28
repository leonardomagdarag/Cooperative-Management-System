<?php

use App\Livewire\Content\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

test('member can update their own profile from the profile page modal', function () {
    Role::findOrCreate('member', 'web');

    $user = User::factory()->create(['role' => 'member']);
    $user->assignRole('member');

    actingAs($user);

    Livewire::test(Profile::class)
        ->call('openEditModal')
        ->assertSet('showEditModal', true)
        ->set('profileForm.first_name', 'Self')
        ->set('profileForm.last_name', 'Editor')
        ->set('profileForm.email', 'self.editor@example.com')
        ->set('profileForm.phone_no', '09171234567')
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertSet('showEditModal', false);

    $user->refresh();

    expect($user->email)->toBe('self.editor@example.com')
        ->and($user->personalData->first_name)->toBe('Self')
        ->and($user->personalData->last_name)->toBe('Editor')
        ->and($user->personalData->contact->phone_no)->toBe('09171234567');
});

test('profile page rejects non-image profile photo uploads', function () {
    Storage::fake('public');
    Role::findOrCreate('member', 'web');

    $user = User::factory()->create(['role' => 'member']);
    $user->assignRole('member');

    actingAs($user);

    Livewire::test(Profile::class)
        ->call('openEditModal')
        ->set('profilePhotoUpload', UploadedFile::fake()->create('profile.txt', 16, 'text/plain'))
        ->call('saveProfile')
        ->assertHasErrors(['profilePhotoUpload']);

    $user->refresh();

    expect($user->personalData?->profile_picture)->toBeNull();
    Storage::disk('public')->assertDirectoryEmpty('profile-photos');
});