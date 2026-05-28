<?php

use App\Livewire\Dashboard;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('seeders create secretary account and permissions', function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $secretary = User::query()->where('email', 'secretary@example.com')->first();

    expect($secretary)->not->toBeNull();
    expect($secretary->role)->toBe('secretary');
    expect((bool) $secretary->is_officer)->toBeTrue();
    expect($secretary->hasRole('secretary'))->toBeTrue();

    $secretaryRole = Role::findByName('secretary', 'web');
    $secretaryPermissions = $secretaryRole->permissions()->pluck('name')->sort()->values()->all();

    expect($secretaryPermissions)->toBe([
        'announcements.create',
        'announcements.update',
        'announcements.view',
        'dashboard.view',
        'loans.view',
        'members.create',
        'members.update',
        'members.view',
        'notifications.view',
        'profile.update',
        'profile.view',
        'reports.export',
        'reports.view',
        'settings.view',
        'transactions.export',
        'transactions.view',
    ]);

    expect(DB::table('role_permissions')->where('role', 'secretary')->pluck('section')->sort()->values()->all())
        ->toBe([
            'announcements',
            'dashboard',
            'ledger',
            'members',
            'messages',
            'reports',
            'settings',
            'tickets',
        ]);
});

test('secretary receives secretary dashboard overview', function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SpatieRolePermissionSeeder::class);

    $secretary = User::query()->where('email', 'secretary@example.com')->firstOrFail();

    $this->actingAs($secretary);

    Livewire::test(Dashboard::class)
        ->assertViewHas('currentSectionPath', 'overviews.secretary-overview')
        ->assertSee('Secretary');
});
