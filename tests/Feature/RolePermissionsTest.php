<?php

use App\Livewire\Content\RolePermissions;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

function makeAdminRoleUser(): User
{
    Role::findOrCreate('admin', 'web');

    $user = User::factory()->create([
        'role' => 'admin',
        'is_officer' => true,
    ]);
    $user->assignRole('admin');

    return $user;
}

test('admin can create a custom role cloned from an existing role', function () {
    $lockedPermissions = ['dashboard.view', 'notifications.view', 'profile.view', 'profile.update', 'settings.view'];
    $editablePermissions = ['members.view', 'reports.view'];

    foreach (array_merge($lockedPermissions, $editablePermissions) as $permissionName) {
        Permission::findOrCreate($permissionName, 'web');
    }

    $managerRole = Role::findOrCreate('manager', 'web');
    $managerRole->syncPermissions(array_merge($lockedPermissions, $editablePermissions));

    actingAs(makeAdminRoleUser());

    Livewire::test(RolePermissions::class)
        ->set('newRoleForm.name', 'Branch Supervisor')
        ->set('newRoleForm.template', 'manager')
        ->call('createRole')
        ->assertHasNoErrors()
        ->assertSet('selectedRole', 'branch-supervisor')
        ->assertSee('Branch Supervisor');

    $createdRole = Role::findByName('branch-supervisor', 'web');
    $expectedPermissions = collect(array_merge($lockedPermissions, $editablePermissions))
        ->sort()
        ->values()
        ->all();

    expect($createdRole->permissions()->pluck('name')->sort()->values()->all())
        ->toBe($expectedPermissions);
});

test('reserved role names cannot be created from role permissions', function () {
    actingAs(makeAdminRoleUser());

    Livewire::test(RolePermissions::class)
        ->set('newRoleForm.name', 'member')
        ->call('createRole')
        ->assertHasErrors(['newRoleForm.name']);

    expect(Role::query()->where('name', 'member')->where('guard_name', 'web')->exists())->toBeFalse();
});

test('create role form can be reset after validation errors', function () {
    actingAs(makeAdminRoleUser());

    Livewire::test(RolePermissions::class)
        ->set('newRoleForm.name', 'member')
        ->call('createRole')
        ->assertHasErrors(['newRoleForm.name'])
        ->call('resetCreateRoleForm')
        ->assertSet('newRoleForm.name', '')
        ->assertSet('newRoleForm.template', '')
        ->assertHasNoErrors();
});

test('admin can rename and delete an unused custom role', function () {
    actingAs(makeAdminRoleUser());

    Role::findOrCreate('branch-supervisor', 'web');

    Livewire::test(RolePermissions::class)
        ->call('renameRole', 'branch-supervisor', 'Compliance Lead')
        ->assertSet('selectedRole', 'compliance-lead');

    expect(Role::query()->where('name', 'branch-supervisor')->where('guard_name', 'web')->exists())->toBeFalse();
    expect(Role::query()->where('name', 'compliance-lead')->where('guard_name', 'web')->exists())->toBeTrue();

    Livewire::test(RolePermissions::class)
        ->call('deleteRole', 'compliance-lead');

    expect(Role::query()->where('name', 'compliance-lead')->where('guard_name', 'web')->exists())->toBeFalse();
});

test('base roles remain protected from deletion', function () {
    actingAs(makeAdminRoleUser());

    Role::findOrCreate('manager', 'web');

    Livewire::test(RolePermissions::class)
        ->call('deleteRole', 'manager');

    expect(Role::query()->where('name', 'manager')->where('guard_name', 'web')->exists())->toBeTrue();
});

test('secretary is restored as a built-in base role', function () {
    actingAs(makeAdminRoleUser());

    Livewire::test(RolePermissions::class)
        ->assertSee('Secretary')
        ->call('deleteRole', 'secretary');

    expect(Role::query()->where('name', 'secretary')->where('guard_name', 'web')->exists())->toBeTrue();
});

test('assigned custom roles cannot be renamed or deleted', function () {
    actingAs(makeAdminRoleUser());

    Role::findOrCreate('branch-supervisor', 'web');

    $officer = User::factory()->create([
        'role' => 'manager',
        'is_officer' => true,
    ]);
    $officer->syncRoles(['branch-supervisor']);

    Livewire::test(RolePermissions::class)
        ->call('renameRole', 'branch-supervisor', 'Operations Lead');

    expect(Role::query()->where('name', 'branch-supervisor')->where('guard_name', 'web')->exists())->toBeTrue();
    expect(Role::query()->where('name', 'operations-lead')->where('guard_name', 'web')->exists())->toBeFalse();

    Livewire::test(RolePermissions::class)
        ->call('deleteRole', 'branch-supervisor');

    expect(Role::query()->where('name', 'branch-supervisor')->where('guard_name', 'web')->exists())->toBeTrue();
});
