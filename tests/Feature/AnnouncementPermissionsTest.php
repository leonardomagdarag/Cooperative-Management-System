<?php

use App\Livewire\Content\Announcements;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function makeAnnouncementUserWithPermissions(array $permissions): User
{
    foreach (['announcements.create', 'announcements.update', 'announcements.delete'] as $permissionName) {
        Permission::findOrCreate($permissionName, 'web');
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create(['role' => 'secretary']);
    $user->syncPermissions(Permission::query()->whereIn('name', $permissions)->where('guard_name', 'web')->get());

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function makeMemberAnnouncementUser(): User
{
    Role::findOrCreate('member', 'web');

    $user = User::factory()->create([
        'role' => 'member',
        'is_officer' => false,
    ]);

    $user->syncRoles(['member']);

    return $user->fresh();
}

test('create-only announcement permission does not allow deleting announcements', function () {
    $user = makeAnnouncementUserWithPermissions(['announcements.create']);

    $announcement = Announcement::query()->create([
        'title' => 'Members meeting',
        'content' => 'Quarterly meeting reminder.',
        'visibility' => ['members'],
        'created_by' => $user->id,
    ]);

    actingAs($user);

    Livewire::test(Announcements::class)
        ->call('openModal')
        ->assertSet('showModal', true)
        ->call('delete', $announcement->id);

    expect(Announcement::query()->whereKey($announcement->id)->exists())->toBeTrue();
});

test('delete permission can remove announcements without create permission', function () {
    $user = makeAnnouncementUserWithPermissions(['announcements.delete']);

    expect($user->can('announcements.create'))->toBeFalse()
        ->and($user->can('announcements.delete'))->toBeTrue();

    $announcement = Announcement::query()->create([
        'title' => 'Records reminder',
        'content' => 'Please submit the missing documents.',
        'visibility' => ['members'],
        'created_by' => $user->id,
    ]);

    actingAs($user);

    Livewire::test(Announcements::class)
        ->call('openModal')
        ->assertSet('showModal', false)
        ->call('delete', $announcement->id);

    expect(Announcement::query()->whereKey($announcement->id)->exists())->toBeFalse();
});

test('member can open a cooperative announcement in a read-only modal', function () {
    $member = makeMemberAnnouncementUser();

    $announcementId = DB::table('announcements')->insertGetId([
        'title' => 'Member advisory',
        'content' => 'This message should open in the member modal.',
        'visibility' => 'cooperative',
        'created_by' => $member->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $announcement = Announcement::query()->findOrFail($announcementId);

    actingAs($member);

    Livewire::test(Announcements::class)
        ->call('viewAnnouncement', $announcement->id)
        ->assertSet('showViewModal', true)
        ->assertSee('This message should open in the member modal.');
});

test('member can open a public announcement in a read-only modal', function () {
    $member = makeMemberAnnouncementUser();

    $announcementId = DB::table('announcements')->insertGetId([
        'title' => 'Public memo',
        'content' => 'This public announcement should also open in the modal.',
        'visibility' => 'public',
        'created_by' => $member->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $announcement = Announcement::query()->findOrFail($announcementId);

    actingAs($member);

    Livewire::test(Announcements::class)
        ->call('viewAnnouncement', $announcement->id)
        ->assertSet('showViewModal', true)
        ->assertSee('This public announcement should also open in the modal.');
});

test('announcement images stay out of the list and only appear in the read-only modal', function () {
    $member = makeMemberAnnouncementUser();

    $announcement = Announcement::query()->create([
        'title' => 'Image bulletin',
        'content' => 'The image should only render inside the modal.',
        'visibility' => ['members'],
        'image_path' => 'announcements/example-banner.jpg',
        'created_by' => $member->id,
    ]);

    actingAs($member);

    $imageUrl = asset('storage/' . $announcement->image_path);

    Livewire::test(Announcements::class)
        ->assertDontSee($imageUrl)
        ->call('viewAnnouncement', $announcement->id)
        ->assertSet('showViewModal', true)
        ->assertSee($imageUrl)
        ->assertSee('The image should only render inside the modal.');
});