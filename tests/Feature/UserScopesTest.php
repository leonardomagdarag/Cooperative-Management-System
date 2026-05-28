<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('memberUsers scope only returns non-officer users with the member role', function () {
    Role::findOrCreate('member', 'web');

    $legacyOnlyMember = User::factory()->create([
        'role' => 'member',
        'is_officer' => false,
    ]);

    $spatieMember = User::factory()->create([
        'role' => 'member',
        'is_officer' => false,
    ]);
    $spatieMember->assignRole('member');

    $officerWithMemberRole = User::factory()->create([
        'role' => 'manager',
        'is_officer' => true,
    ]);
    $officerWithMemberRole->assignRole('member');

    $memberIds = User::query()->memberUsers()->pluck('id')->all();

    expect($memberIds)->toContain($spatieMember->id);
    expect($memberIds)->not->toContain($legacyOnlyMember->id);
    expect($memberIds)->not->toContain($officerWithMemberRole->id);
});
