<?php

use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->secretary = User::where('email', 'secretary@example.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@example.com')->firstOrFail();
});

test('F028-1: unauthenticated requests to announcement CRUD endpoints return 401', function () {
    $this->getJson('/api/announcements')->assertUnauthorized();
    $this->postJson('/api/announcements', [])->assertUnauthorized();
    $this->putJson('/api/announcements/1', [])->assertUnauthorized();
    $this->deleteJson('/api/announcements/1')->assertUnauthorized();
});

test('F028-2: regular member receives 403 on store, update, and destroy', function () {
    $announcement = Announcement::create([
        'title' => 'Sample Announcement',
        'content' => 'Sample Content for regular members.',
        'type' => 'announcement',
        'visibility' => ['members'],
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->member);

    $this->postJson('/api/announcements', [])->assertForbidden();
    $this->putJson("/api/announcements/{$announcement->id}", [])->assertForbidden();
    $this->deleteJson("/api/announcements/{$announcement->id}")->assertForbidden();
});

test('F028-3: secretary creates announcement with valid payload', function () {
    $this->actingAs($this->secretary);

    $response = $this->postJson('/api/announcements', [
        'title' => 'Annual General Assembly 2026',
        'content' => 'Notice is hereby given for the upcoming general assembly.',
        'type' => 'announcement',
        'visibility' => ['members', 'public'],
        'category' => 'Governance',
    ]);

    $response->assertStatus(201)
        ->assertJson(['message' => 'Announcement created successfully.'])
        ->assertJsonPath('data.title', 'Annual General Assembly 2026');

    $this->assertDatabaseHas('announcements', [
        'title' => 'Annual General Assembly 2026',
        'created_by' => $this->secretary->id,
    ]);
});

test('F028-4: event type announcement requires event_date, returning 422 if omitted', function () {
    $this->actingAs($this->secretary);

    $response = $this->postJson('/api/announcements', [
        'title' => 'Cooperative Workshop',
        'content' => 'Financial literacy seminar for members.',
        'type' => 'event',
        'visibility' => ['everyone'],
        // event_date omitted
    ]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'Event date is required for events.']);
});

test('F028-5: secretary updates an existing announcement', function () {
    $announcement = Announcement::create([
        'title' => 'Initial Title',
        'content' => 'Initial Content text.',
        'type' => 'announcement',
        'visibility' => ['members'],
        'created_by' => $this->secretary->id,
    ]);

    $this->actingAs($this->secretary);

    $response = $this->putJson("/api/announcements/{$announcement->id}", [
        'title' => 'Updated Title Assembly',
        'content' => 'Updated Content details with revised schedule.',
        'type' => 'announcement',
        'visibility' => ['everyone'],
        'category' => 'Important',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Announcement updated successfully.'])
        ->assertJsonPath('data.title', 'Updated Title Assembly');

    $announcement->refresh();
    expect($announcement->title)->toBe('Updated Title Assembly');
});

test('F028-6: officer without announcements.delete receives 403 on destroy', function () {
    $announcement = Announcement::create([
        'title' => 'Protected Announcement',
        'content' => 'Important notice.',
        'type' => 'announcement',
        'visibility' => ['everyone'],
        'created_by' => $this->admin->id,
    ]);

    // Member has no delete
    $this->actingAs($this->member);
    $this->deleteJson("/api/announcements/{$announcement->id}")->assertForbidden();
});

test('F028-7: admin with announcements.delete can delete announcement', function () {
    $announcement = Announcement::create([
        'title' => 'Obsolete Notice',
        'content' => 'Old content to be purged.',
        'type' => 'announcement',
        'visibility' => ['everyone'],
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    $response = $this->deleteJson("/api/announcements/{$announcement->id}");
    $response->assertOk()
        ->assertJson(['message' => 'Announcement deleted successfully.']);

    expect(Announcement::find($announcement->id))->toBeNull();
});

test('F028-8: member can read member-visible announcement, but cannot read officer-only announcement', function () {
    $publicNotice = Announcement::create([
        'title' => 'Member Notice',
        'content' => 'General updates for all active members.',
        'type' => 'announcement',
        'visibility' => ['members'],
        'created_by' => $this->admin->id,
    ]);

    $officerOnlyNotice = Announcement::create([
        'title' => 'Internal Audit Board Memo',
        'content' => 'Confidential officer deliberations.',
        'type' => 'announcement',
        'visibility' => ['officers'],
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->member);

    // Member can view publicNotice
    $this->getJson("/api/announcements/{$publicNotice->id}")->assertOk();

    // Member is forbidden from viewing officerOnlyNotice
    $this->getJson("/api/announcements/{$officerOnlyNotice->id}")
        ->assertForbidden();
});
