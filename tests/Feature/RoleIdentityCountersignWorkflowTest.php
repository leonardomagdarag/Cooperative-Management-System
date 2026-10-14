<?php

use App\Models\PrivilegedActionRequest;
use App\Models\User;
use App\Services\CountersignService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->admin = User::factory()->create(['role' => 'admin', 'is_officer' => true]);
    $this->admin->assignRole('admin');
    $this->chairman = User::factory()->create(['role' => 'chairman', 'is_officer' => true]);
    $this->chairman->assignRole('chairman');
});

test('role create rename and delete take effect only after chairman approval', function () {
    $create = $this->actingAs($this->admin)->postJson('/api/roles', ['name' => 'compliance review', 'template' => 'manager'])
        ->assertAccepted()->assertJsonPath('status', 'pending');

    expect(Role::query()->where('name', 'compliance-review')->exists())->toBeFalse();
    $createRequest = PrivilegedActionRequest::findOrFail($create->json('request_id'));
    app(CountersignService::class)->approve($createRequest, $this->chairman);
    expect(Role::query()->where('name', 'compliance-review')->exists())->toBeTrue();

    $rename = $this->actingAs($this->admin)->putJson('/api/roles/compliance-review/rename', ['name' => 'compliance analyst'])
        ->assertAccepted()->assertJsonPath('status', 'pending');
    expect(Role::query()->where('name', 'compliance-review')->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'compliance-analyst')->exists())->toBeFalse();
    app(CountersignService::class)->approve(PrivilegedActionRequest::findOrFail($rename->json('request_id')), $this->chairman);
    expect(Role::query()->where('name', 'compliance-analyst')->exists())->toBeTrue();

    $delete = $this->actingAs($this->admin)->deleteJson('/api/roles/compliance-analyst')
        ->assertAccepted()->assertJsonPath('status', 'pending');
    expect(Role::query()->where('name', 'compliance-analyst')->exists())->toBeTrue();
    app(CountersignService::class)->approve(PrivilegedActionRequest::findOrFail($delete->json('request_id')), $this->chairman);
    expect(Role::query()->where('name', 'compliance-analyst')->exists())->toBeFalse();
});

test('custom roles cannot copy reserved system roles and reserved roles cannot be edited through role permissions', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/roles', ['name' => 'shadow admin', 'template' => 'admin'])
        ->assertUnprocessable();

    $this->putJson('/api/roles/admin/permissions', ['permissions' => ['dashboard.view']])
        ->assertUnprocessable();

    expect(Role::query()->where('name', 'shadow-admin')->exists())->toBeFalse()
        ->and(PrivilegedActionRequest::query()->where('action_type', 'role.create')->exists())->toBeFalse();
});

test('role permission requests cannot create a missing role as a side effect', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/roles/ghost-role/permissions', ['permissions' => ['dashboard.view']])
        ->assertNotFound();

    expect(Role::query()->where('name', 'ghost-role')->exists())->toBeFalse();
});

test('a role assigned while deletion waits for approval is preserved', function () {
    $this->actingAs($this->admin);
    $create = $this->postJson('/api/roles', ['name' => 'late assigned role'])->assertAccepted();
    app(CountersignService::class)->approve(PrivilegedActionRequest::findOrFail($create->json('request_id')), $this->chairman);

    $delete = $this->deleteJson('/api/roles/late-assigned-role')->assertAccepted();
    $target = User::factory()->create(['role' => 'late-assigned-role']);
    $target->assignRole('late-assigned-role');

    expect(fn () => app(CountersignService::class)->approve(
        PrivilegedActionRequest::findOrFail($delete->json('request_id')),
        $this->chairman,
    ))->toThrow(HttpException::class);

    expect(Role::query()->where('name', 'late-assigned-role')->exists())->toBeTrue()
        ->and($target->fresh()->hasRole('late-assigned-role'))->toBeTrue()
        ->and(PrivilegedActionRequest::findOrFail($delete->json('request_id'))->status)->toBe('failed');
});
