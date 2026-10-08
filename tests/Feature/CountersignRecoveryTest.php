<?php

use App\Models\PrivilegedActionRequest;
use App\Models\User;
use App\Services\CountersignService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->admin = User::factory()->create(['role' => 'admin', 'is_officer' => true]);
    $this->admin->assignRole('admin');
    $this->chairman = User::factory()->create(['role' => 'chairman', 'is_officer' => true]);
    $this->chairman->assignRole('chairman');
    $this->manager = User::factory()->create(['role' => 'manager', 'is_officer' => true]);
    $this->manager->assignRole('manager');
    $this->target = User::factory()->create(['role' => 'member']);
    $this->target->assignRole('member');
    Storage::fake('local');
});

test('oversight queue exposes stalled executions and chairman can reconcile without replay', function () {
    $request = PrivilegedActionRequest::create([
        'action_type' => 'role.assign',
        'status' => 'executing',
        'payload' => ['user_id' => $this->target->id, 'roles' => ['chairman']],
        'requested_by' => $this->admin->id,
        'idempotency_key' => Str::uuid()->toString(),
        'expires_at' => now()->addHours(24),
        'started_at' => now()->subMinutes(CountersignService::EXECUTION_TIMEOUT_MINUTES + 1),
    ]);

    $this->actingAs($this->chairman)->getJson('/api/oversight/requests')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'executing');

    app(CountersignService::class)->reconcile($request, $this->chairman, 'failed', 'Verified that no role change was committed.');

    expect($request->fresh()->status)->toBe('failed')
        ->and($request->fresh()->execution_result['reconciled'])->toBeTrue()
        ->and($this->target->fresh()->hasRole('member'))->toBeTrue()
        ->and($this->target->fresh()->hasRole('chairman'))->toBeFalse()
        ->and(Storage::disk('local')->exists('privileged-actions.jsonl'))->toBeTrue();
});

test('manager cannot reconcile a stalled privileged action', function () {
    $request = PrivilegedActionRequest::create([
        'action_type' => 'role.assign',
        'status' => 'executing',
        'payload' => ['user_id' => $this->target->id, 'roles' => ['chairman']],
        'requested_by' => $this->admin->id,
        'idempotency_key' => Str::uuid()->toString(),
        'expires_at' => now()->addHours(24),
        'started_at' => now()->subMinutes(CountersignService::EXECUTION_TIMEOUT_MINUTES + 1),
    ]);

    expect(fn () => app(CountersignService::class)->reconcile($request, $this->manager, 'failed', 'No execution occurred.'))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect($request->fresh()->status)->toBe('executing');
});
