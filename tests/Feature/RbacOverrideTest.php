<?php

use App\Livewire\Content\OfficerOverrideManager;
use App\Livewire\Content\RolePermissionManager;
use App\Models\OfficerPermissionOverride;
use App\Models\User;
use App\Support\PermissionCatalog;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use function Pest\Laravel\actingAs;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function ensureRolesAndPermissions(): void
{
    foreach (PermissionCatalog::all() as $perm) {
        Permission::findOrCreate($perm, 'web');
    }
    foreach (['admin', 'manager', 'secretary', 'loan', 'treasurer', 'member'] as $r) {
        Role::findOrCreate($r, 'web');
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function makeAdmin(): User
{
    ensureRolesAndPermissions();
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin', 'is_officer' => false]);
    $admin->syncRoles(['admin']);
    // Give admin all permissions via role (Gate::before handles the rest)
    Role::findByName('admin', 'web')->syncPermissions(Permission::all());
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    return $admin->fresh();
}

function makeOfficer(string $role = 'secretary'): User
{
    ensureRolesAndPermissions();
    /** @var User $officer */
    $officer = User::factory()->create(['role' => $role, 'is_officer' => true]);
    $officer->syncRoles([$role]);
    $officer->syncPermissions([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    return $officer->fresh();
}

// ---------------------------------------------------------------------------
// PermissionCatalog unit tests
// ---------------------------------------------------------------------------

describe('PermissionCatalog', function () {
    test('groups() returns non-empty associative array of string arrays', function () {
        $groups = PermissionCatalog::groups();

        expect($groups)->toBeArray()->not->toBeEmpty();

        foreach ($groups as $name => $perms) {
            expect($name)->toBeString()->not->toBeEmpty();
            expect($perms)->toBeArray()->not->toBeEmpty();
            foreach ($perms as $perm) {
                expect($perm)->toBeString()->not->toBeEmpty();
            }
        }
    });

    test('all() is the flat union of all groups', function () {
        $fromGroups = collect(PermissionCatalog::groups())->flatten()->sort()->values()->all();
        $fromAll    = collect(PermissionCatalog::all())->sort()->values()->all();

        expect($fromAll)->toBe($fromGroups);
    });

    test('rolePresets() contains entries for all four officer roles', function () {
        $presets = PermissionCatalog::rolePresets();

        foreach (['manager', 'secretary', 'loan', 'treasurer'] as $role) {
            expect($presets)->toHaveKey($role);
            expect($presets[$role])->toBeArray()->not->toBeEmpty();
        }
    });

    test('all rolePreset permissions exist in all()', function () {
        $all = PermissionCatalog::all();
        foreach (PermissionCatalog::rolePresets() as $role => $perms) {
            foreach ($perms as $perm) {
                expect($all)->toContain($perm);
            }
        }
    });

    test('formatLabel produces human-readable string', function () {
        // Format is "Resource: Action"
        expect(PermissionCatalog::formatLabel('members.view'))->toBe('Members: View');
        expect(PermissionCatalog::formatLabel('loans.approve'))->toBe('Loans: Approve');
        expect(PermissionCatalog::formatLabel('officers.manage-permissions'))->toBe('Officers: Manage Permissions');
    });
});

// ---------------------------------------------------------------------------
// OfficerPermissionOverride model
// ---------------------------------------------------------------------------

describe('OfficerPermissionOverride model', function () {
    beforeEach(fn() => OfficerPermissionOverride::clearCache());

    test('officer can have allow and disallow overrides stored', function () {
        $officer = makeOfficer();

        OfficerPermissionOverride::create([
            'user_id'    => $officer->id,
            'permission' => 'loans.view',
            'effect'     => OfficerPermissionOverride::EFFECT_ALLOW,
        ]);
        OfficerPermissionOverride::create([
            'user_id'    => $officer->id,
            'permission' => 'loans.approve',
            'effect'     => OfficerPermissionOverride::EFFECT_DISALLOW,
        ]);

        expect($officer->permissionOverrides()->count())->toBe(2);
    });

    test('unique constraint prevents duplicate permission overrides per user', function () {
        $officer = makeOfficer();

        OfficerPermissionOverride::create([
            'user_id'    => $officer->id,
            'permission' => 'loans.view',
            'effect'     => 'allow',
        ]);

        expect(fn() => OfficerPermissionOverride::create([
            'user_id'    => $officer->id,
            'permission' => 'loans.view',
            'effect'     => 'disallow',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});

// ---------------------------------------------------------------------------
// Gate::after override logic
// ---------------------------------------------------------------------------

describe('Gate::after officer override interception', function () {
    beforeEach(function () {
        ensureRolesAndPermissions();
        OfficerPermissionOverride::clearCache();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    });

    test('officer without override uses role permission (role grants → allowed)', function () {
        $officer = makeOfficer('secretary');
        Role::findByName('secretary', 'web')->givePermissionTo('loans.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        expect($officer->fresh()->can('loans.view'))->toBeTrue();
    });

    test('disallow override denies permission even when role grants it', function () {
        $officer = makeOfficer('secretary');
        Role::findByName('secretary', 'web')->givePermissionTo('loans.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        OfficerPermissionOverride::create([
            'user_id'    => $officer->id,
            'permission' => 'loans.view',
            'effect'     => OfficerPermissionOverride::EFFECT_DISALLOW,
        ]);
        OfficerPermissionOverride::clearCache();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        expect($officer->fresh()->can('loans.view'))->toBeFalse();
    });

    test('allow override grants permission even when role does not have it', function () {
        $officer = makeOfficer('secretary');
        Role::findByName('secretary', 'web')->revokePermissionTo('loans.approve');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        OfficerPermissionOverride::create([
            'user_id'    => $officer->id,
            'permission' => 'loans.approve',
            'effect'     => OfficerPermissionOverride::EFFECT_ALLOW,
        ]);
        OfficerPermissionOverride::clearCache();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        expect($officer->fresh()->can('loans.approve'))->toBeTrue();
    });

    test('non-officer users are unaffected by override table', function () {
        ensureRolesAndPermissions();
        $member = User::factory()->create(['role' => 'member', 'is_officer' => false]);
        $member->syncRoles(['member']);
        $member->syncPermissions([]);
        // member role does not get loans.view
        Role::findByName('member', 'web')->revokePermissionTo('loans.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        OfficerPermissionOverride::create([
            'user_id'    => $member->id,
            'permission' => 'loans.view',
            'effect'     => OfficerPermissionOverride::EFFECT_ALLOW,
        ]);
        OfficerPermissionOverride::clearCache();

        // Gate::after skips non-officers, so the allow override is ignored
        expect($member->fresh()->can('loans.view'))->toBeFalse();
    });

    test('admin is never affected by officer override interception', function () {
        $admin = makeAdmin();

        OfficerPermissionOverride::create([
            'user_id'    => $admin->id,
            'permission' => 'loans.view',
            'effect'     => OfficerPermissionOverride::EFFECT_DISALLOW,
        ]);
        OfficerPermissionOverride::clearCache();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Gate::before returns true for admin before Gate::after runs
        expect($admin->fresh()->can('loans.view'))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// OfficerOverrideManager Livewire component
// ---------------------------------------------------------------------------

describe('OfficerOverrideManager', function () {
    beforeEach(function () {
        ensureRolesAndPermissions();
        OfficerPermissionOverride::clearCache();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    });

    test('renders for admin managing an officer', function () {
        $admin   = makeAdmin();
        $officer = makeOfficer();

        actingAs($admin);
        Livewire::test(OfficerOverrideManager::class, ['officerId' => $officer->id])
            ->assertStatus(200);
    });

    test('403 when user lacks officers.manage-permissions', function () {
        $secretary = makeOfficer('secretary');
        // Remove the permission from the role so the officer can't manage permissions
        Role::findByName('secretary', 'web')->revokePermissionTo('officers.manage-permissions');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        OfficerPermissionOverride::clearCache();

        actingAs($secretary->fresh());

        Livewire::test(OfficerOverrideManager::class, ['officerId' => $secretary->id])
            ->assertStatus(403);
    });

    test('setOverride updates in-memory state', function () {
        $admin   = makeAdmin();
        $officer = makeOfficer();

        actingAs($admin);
        Livewire::test(OfficerOverrideManager::class, ['officerId' => $officer->id])
            ->call('setOverride', 'loans.view', 'disallow')
            ->assertSet('overrides', fn($v) => ($v['loans.view'] ?? null) === 'disallow');
    });

    test('saveOverrides persists disallow to DB', function () {
        $admin   = makeAdmin();
        $officer = makeOfficer();

        actingAs($admin);
        Livewire::test(OfficerOverrideManager::class, ['officerId' => $officer->id])
            ->call('setOverride', 'loans.view', 'disallow')
            ->call('saveOverrides');

        expect(
            OfficerPermissionOverride::where('user_id', $officer->id)
                ->where('permission', 'loans.view')
                ->value('effect')
        )->toBe('disallow');
    });

    test('saveOverrides with inherit deletes existing override', function () {
        $admin   = makeAdmin();
        $officer = makeOfficer();

        OfficerPermissionOverride::create([
            'user_id'    => $officer->id,
            'permission' => 'loans.view',
            'effect'     => 'disallow',
        ]);

        actingAs($admin);
        Livewire::test(OfficerOverrideManager::class, ['officerId' => $officer->id])
            ->call('setOverride', 'loans.view', 'inherit')
            ->call('saveOverrides');

        expect(
            OfficerPermissionOverride::where('user_id', $officer->id)
                ->where('permission', 'loans.view')
                ->exists()
        )->toBeFalse();
    });

    test('clearAllOverrides resets all to inherit', function () {
        $admin   = makeAdmin();
        $officer = makeOfficer();

        actingAs($admin);
        Livewire::test(OfficerOverrideManager::class, ['officerId' => $officer->id])
            ->call('setOverride', 'loans.view', 'disallow')
            ->call('setOverride', 'loans.approve', 'allow')
            ->call('clearAllOverrides')
            ->assertSet(
                'overrides',
                fn($v) => ($v['loans.view'] ?? null) === 'inherit' &&
                    ($v['loans.approve'] ?? null) === 'inherit'
            );
    });
});

// ---------------------------------------------------------------------------
// RolePermissionManager Livewire component
// ---------------------------------------------------------------------------

describe('RolePermissionManager', function () {
    beforeEach(function () {
        ensureRolesAndPermissions();
        OfficerPermissionOverride::clearCache();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    });

    test('renders for admin', function () {
        $admin = makeAdmin();
        actingAs($admin);
        Livewire::test(RolePermissionManager::class)->assertStatus(200);
    });

    test('403 for non-admin users', function () {
        $officer = makeOfficer('secretary');
        actingAs($officer);

        Livewire::test(RolePermissionManager::class)
            ->assertStatus(403);
    });

    test('applyPreset loads preset permissions for selected role', function () {
        $admin = makeAdmin();
        actingAs($admin);

        Livewire::test(RolePermissionManager::class)
            ->set('selectedRole', 'secretary')
            ->call('applyPreset')
            ->assertSet('selectedPermissions', PermissionCatalog::rolePresets()['secretary']);
    });

    test('clearAll empties selectedPermissions', function () {
        $admin = makeAdmin();
        actingAs($admin);

        Livewire::test(RolePermissionManager::class)
            ->set('selectedRole', 'secretary')
            ->call('applyPreset')
            ->call('clearAll')
            ->assertSet('selectedPermissions', []);
    });

    test('saveRolePermissions syncs permissions to the Spatie role', function () {
        $admin = makeAdmin();
        actingAs($admin);

        Livewire::test(RolePermissionManager::class)
            ->set('selectedRole', 'treasurer')
            ->set('selectedPermissions', ['dashboard.view', 'reports.view'])
            ->call('saveRolePermissions');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::findByName('treasurer', 'web');
        expect($role->permissions->pluck('name')->sort()->values()->all())
            ->toBe(['dashboard.view', 'reports.view']);
    });

    test('switching role reloads permissions', function () {
        $admin = makeAdmin();
        actingAs($admin);

        // Pre-assign a known permission set to secretary
        Role::findByName('secretary', 'web')->syncPermissions(['dashboard.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::test(RolePermissionManager::class)
            ->set('selectedRole', 'secretary')
            ->assertSet('selectedPermissions', ['dashboard.view']);
    });
});
