<?php

use App\Livewire\Content\Logs;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function createAdminWithLogsPermission(): User
{
    $user = User::factory()->create([
        'role' => 'admin',
    ]);

    $permission = Permission::findOrCreate('logs.view', 'web');
    $user->givePermissionTo($permission);

    return $user;
}

test('admin with logs permission can view logs component', function () {
    $admin = createAdminWithLogsPermission();

    activity('auth')
        ->causedBy($admin)
        ->event('auth.login_success')
        ->log('User logged in successfully.');

    Livewire::actingAs($admin)
        ->test(Logs::class)
        ->assertStatus(200)
        ->assertSee('Audit Logs')
        ->assertSee('auth.login_success');
});

test('non-admin cannot view logs component', function () {
    $member = User::factory()->create([
        'role' => 'member',
    ]);

    Permission::findOrCreate('logs.view', 'web');
    $member->givePermissionTo('logs.view');

    Livewire::actingAs($member)
        ->test(Logs::class)
        ->assertForbidden();
});

test('logs can be filtered by tag and event', function () {
    $admin = createAdminWithLogsPermission();

    activity('auth')
        ->causedBy($admin)
        ->event('auth.login_success')
        ->log('Auth log entry.');

    activity('transaction')
        ->causedBy($admin)
        ->event('transaction.created')
        ->log('Transaction log entry.');

    Livewire::actingAs($admin)
        ->test(Logs::class)
        ->set('tagFilter', 'transaction')
        ->set('eventFilter', 'transaction.created')
        ->assertSee('Transaction log entry.')
        ->assertDontSee('Auth log entry.');
});

test('logs search matches description and actor email', function () {
    $admin = createAdminWithLogsPermission();

    activity('member')
        ->causedBy($admin)
        ->event('member.updated')
        ->log('Member profile updated by admin.');

    Livewire::actingAs($admin)
        ->test(Logs::class)
        ->set('search', 'profile updated')
        ->assertSee('Member profile updated by admin.')
        ->set('search', $admin->email)
        ->assertSee('Member profile updated by admin.');
});

test('logs date range filter limits old records', function () {
    $admin = createAdminWithLogsPermission();

    activity('auth')
        ->causedBy($admin)
        ->event('auth.login_success')
        ->log('Recent auth entry.');

    activity('auth')
        ->causedBy($admin)
        ->event('auth.login_success')
        ->log('Old auth entry.');

    $old = Activity::query()->where('description', 'Old auth entry.')->latest('id')->first();
    $old->forceFill(['created_at' => now()->subDays(10)])->save();

    Livewire::actingAs($admin)
        ->test(Logs::class)
        ->set('dateRange', 'today')
        ->assertSee('Recent auth entry.')
        ->assertDontSee('Old auth entry.');
});
