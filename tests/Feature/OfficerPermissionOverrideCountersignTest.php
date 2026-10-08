<?php

use App\Models\OfficerPermissionOverride;
use App\Models\PrivilegedActionRequest;
use App\Models\User;
use App\Services\CountersignService;
use App\Services\SecurityOtpService;
use Database\Seeders\SpatieRolePermissionSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->admin = User::factory()->create(['role' => 'admin', 'is_officer' => true]);
    $this->admin->assignRole('admin');
    $this->chairman = User::factory()->create(['role' => 'chairman', 'is_officer' => true]);
    $this->chairman->assignRole('chairman');
    $this->target = User::factory()->create(['role' => 'manager', 'is_officer' => true]);
    $this->target->assignRole('manager');
});

test('officer permission overrides stay unchanged until chairman approves with OTP', function () {
    $response = $this->actingAs($this->admin)->putJson("/api/officers/{$this->target->id}/permissions", [
        'overrides' => ['loans.approve' => OfficerPermissionOverride::EFFECT_DISALLOW],
    ])->assertAccepted()->assertJsonPath('status', 'pending');

    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));
    expect($request->action_type)->toBe('officer.permissions.update')
        ->and($this->target->fresh()->can('loans.approve'))->toBeTrue()
        ->and(OfficerPermissionOverride::where('user_id', $this->target->id)->exists())->toBeFalse();

    $this->actingAs($this->chairman)
        ->postJson("/api/oversight/requests/{$request->id}/decision", [
            'decision' => 'approve',
            'otp_code' => '000000',
        ])->assertUnprocessable();
    expect($request->fresh()->status)->toBe('pending')
        ->and($this->target->fresh()->can('loans.approve'))->toBeTrue();

    $otp = app(SecurityOtpService::class);
    $challenge = $otp->issueChallenge($this->chairman, 'privileged_action_approval', [
        'request_id' => $request->id,
        'decision' => 'approve',
    ]);
    $this->postJson("/api/oversight/requests/{$request->id}/decision", [
        'decision' => 'approve',
        'otp_code' => $challenge['otp_code'],
    ])->assertOk();

    expect($request->fresh()->status)->toBe('executed')
        ->and($this->target->fresh()->can('loans.approve'))->toBeFalse()
        ->and(OfficerPermissionOverride::where('user_id', $this->target->id)->value('effect'))->toBe(OfficerPermissionOverride::EFFECT_DISALLOW);

    $clear = $this->actingAs($this->admin)->putJson("/api/officers/{$this->target->id}/permissions", ['overrides' => []])
        ->assertAccepted()->assertJsonPath('status', 'pending');
    expect($this->target->fresh()->can('loans.approve'))->toBeFalse();
    app(CountersignService::class)->approve(PrivilegedActionRequest::findOrFail($clear->json('request_id')), $this->chairman);
    expect($this->target->fresh()->can('loans.approve'))->toBeTrue()
        ->and(OfficerPermissionOverride::where('user_id', $this->target->id)->exists())->toBeFalse();
});
