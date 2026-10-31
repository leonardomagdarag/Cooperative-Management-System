<?php

use App\Models\User;
use App\Services\CountersignService;
use App\Support\PermissionCatalog;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->developer = User::where('email', 'developer@example.com')->firstOrFail();
});

test('developer role is populated from the permission catalog', function () {
    $granted = Role::findByName('developer', 'web')->permissions()->pluck('name')->all();

    $expected = array_values(array_diff(
        PermissionCatalog::allPermissions(),
        ['oversight.approve', 'transactions.approve-own'],
    ));

    expect($granted)->toEqualCanonicalizing($expected)
        ->and((bool) $this->developer->is_officer)->toBeTrue()
        ->and($this->developer->profile_completion_required)->toBeFalse();
});

test('developer uses the normal permission gates for administration pages', function () {
    $this->actingAs($this->developer);

    $this->getJson('/api/roles')->assertOk();
    $this->getJson('/api/settings/service-availability')->assertOk();
    $this->getJson('/api/settings/xendit')->assertOk();
    $this->getJson('/api/public-content')
        ->assertOk()
        ->assertJsonPath('can_manage', true);
});

test('developer cannot countersign privileged actions', function () {
    expect($this->developer->can('oversight.approve'))->toBeFalse()
        ->and(Gate::forUser($this->developer)->allows('oversight.approve'))->toBeFalse()
        ->and(app(CountersignService::class)->canDecide($this->developer))->toBeFalse();
});
