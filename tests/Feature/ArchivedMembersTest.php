<?php

use App\Models\MemberApplication;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->accountant = User::where('email', 'accountant@example.com')->firstOrFail();
});

function archivedMemberFactory(string $email): User
{
    $user = User::factory()->create([
        'email' => $email,
        'account_status' => 'inactive',
    ]);
    $user->assignRole('member');

    return $user;
}

function archiveAsDeclinedMember(User $member): void
{
    MemberApplication::create([
        'user_id' => $member->id,
        'status' => 'declined',
        'original_email' => $member->email,
        'first_name' => 'Archived',
        'last_name' => 'Member',
    ]);

    $member->update([
        'email' => 'archived_'.$member->id.'_'.$member->email,
        'user_number' => 'ARCH-'.$member->user_number,
    ]);

    $member->delete();
}

test('archived filter returns only soft-deleted members', function () {
    $member = archivedMemberFactory('archived.shown@example.com');
    $member->delete();

    $everything = $this->actingAs($this->admin)->getJson('/api/members?status=all')->assertOk();
    expect(collect($everything->json('data'))->contains('id', $member->id))->toBeFalse();

    $archived = $this->actingAs($this->admin)->getJson('/api/members?status=archived')->assertOk();
    $row = collect($archived->json('data'))->firstWhere('id', $member->id);

    expect($row)->not->toBeNull()
        ->and($row['archived_at'])->not->toBeNull();
});

test('archived member can be viewed', function () {
    $member = archivedMemberFactory('archived.view@example.com');
    $member->delete();

    $this->actingAs($this->admin)->getJson("/api/members/{$member->id}")
        ->assertOk()
        ->assertJsonPath('archived_at', $member->fresh()->deleted_at->toISOString());
});

test('restore is restricted to admin users', function () {
    $member = archivedMemberFactory('restore.denied@example.com');
    $member->delete();

    $this->actingAs($this->accountant)
        ->postJson("/api/members/{$member->id}/restore")
        ->assertForbidden();
});

test('chairman can restore an archived member and undo the decline scramble', function () {
    $member = archivedMemberFactory('restore.me@example.com');
    archiveAsDeclinedMember($member);

    $chairman = User::factory()->create(['email' => 'chairman@example.com']);
    $chairman->assignRole('chairman');
    $chairman->givePermissionTo('members.delete');

    $this->actingAs($chairman)
        ->postJson("/api/members/{$member->id}/restore")
        ->assertOk()
        ->assertJsonPath('message', 'Member restored.');

    $restored = $member->fresh();

    expect($restored->trashed())->toBeFalse()
        ->and($restored->email)->toBe('restore.me@example.com')
        ->and($restored->user_number)->not->toStartWith('ARCH-');
});

test('restore is rejected when the original email is already in use', function () {
    $member = archivedMemberFactory('restore.taken@example.com');
    archiveAsDeclinedMember($member);

    archivedMemberFactory('restore.taken@example.com');

    $chairman = User::factory()->create(['email' => 'chairman@example.com']);
    $chairman->assignRole('chairman');
    $chairman->givePermissionTo('members.delete');

    $this->actingAs($chairman)
        ->postJson("/api/members/{$member->id}/restore")
        ->assertStatus(422);
});

test('permanent delete is restricted to admin users', function () {
    $member = archivedMemberFactory('force.denied@example.com');
    $member->delete();

    $this->actingAs($this->accountant)
        ->deleteJson("/api/members/{$member->id}/force")
        ->assertForbidden();
});

test('chairman can permanently delete an archived member', function () {
    $member = archivedMemberFactory('force.target@example.com');
    $member->delete();

    $chairman = User::factory()->create(['email' => 'chairman@example.com']);
    $chairman->assignRole('chairman');
    $chairman->givePermissionTo('members.delete');

    $this->actingAs($chairman)
        ->deleteJson("/api/members/{$member->id}/force")
        ->assertOk()
        ->assertJsonPath('message', 'Member permanently deleted.');

    expect(User::withTrashed()->find($member->id))->toBeNull();
});
