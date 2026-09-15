<?php

use App\Models\Account;
use App\Models\Announcement;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->manager = User::where('email', 'manager@example.com')->firstOrFail();
    $this->accountant = User::where('email', 'accountant@example.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@example.com')->firstOrFail();
});

test('member cannot view officer-only announcement via show endpoint', function () {
    $officerAnnouncement = Announcement::create([
        'title' => 'Confidential Officer Memo',
        'content' => 'Internal leadership briefing.',
        'type' => 'announcement',
        'visibility' => ['officers'],
        'created_by' => $this->admin->id,
    ]);

    // Member attempting to view officer announcement must be blocked with 403
    actingAs($this->member);
    $this->getJson("/api/announcements/{$officerAnnouncement->id}")
        ->assertForbidden();

    // Officer with announcements permission can view it
    actingAs($this->admin);
    $this->getJson("/api/announcements/{$officerAnnouncement->id}")
        ->assertOk()
        ->assertJsonPath('title', 'Confidential Officer Memo');
});

test('member can view public and member announcements via show endpoint', function () {
    $memberAnnouncement = Announcement::create([
        'title' => 'General Assembly Notice',
        'content' => 'Notice for all members.',
        'type' => 'announcement',
        'visibility' => ['members'],
        'created_by' => $this->admin->id,
    ]);

    actingAs($this->member);
    $this->getJson("/api/announcements/{$memberAnnouncement->id}")
        ->assertOk()
        ->assertJsonPath('title', 'General Assembly Notice');
});

test('deposits.approve permission cannot approve cash withdrawals', function () {
    $depositApprover = User::factory()->create(['account_status' => 'active', 'is_officer' => true]);
    $depositApprover->givePermissionTo('deposits.approve');

    $memberAccount = Account::firstOrCreate(
        ['user_id' => $this->member->id, 'account_type' => 'savings'],
        ['balance' => 5000]
    );
    $memberAccount->update(['balance' => 5000]);

    $withdrawal = Transaction::create([
        'account_id' => $memberAccount->id,
        'transaction_type' => 'withdrawal',
        'amount' => 500,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
    ]);

    // Attempting to approve withdrawal with only deposits.approve must fail with 403
    actingAs($depositApprover);
    $this->postJson("/api/services/approve/{$withdrawal->id}", [
        'action' => 'approve',
    ])->assertForbidden();

    expect($withdrawal->fresh()->status)->toBe('pending');
});

test('withdrawals.approve permission cannot approve deposits', function () {
    $withdrawalApprover = User::factory()->create(['account_status' => 'active', 'is_officer' => true]);
    $withdrawalApprover->givePermissionTo('withdrawals.approve');

    $memberAccount = Account::firstOrCreate(
        ['user_id' => $this->member->id, 'account_type' => 'savings'],
        ['balance' => 5000]
    );

    $deposit = Transaction::create([
        'account_id' => $memberAccount->id,
        'transaction_type' => 'deposit',
        'amount' => 500,
        'status' => 'pending',
        'transaction_mode' => 'online',
        'transaction_date' => now(),
    ]);

    // Attempting to approve deposit with only withdrawals.approve must fail with 403
    actingAs($withdrawalApprover);
    $this->postJson("/api/services/approve/{$deposit->id}", [
        'action' => 'approve',
    ])->assertForbidden();

    expect($deposit->fresh()->status)->toBe('pending');
});

test('pending transactions endpoint filters transactions by approval permissions', function () {
    $depositApprover = User::factory()->create(['account_status' => 'active', 'is_officer' => true]);
    $depositApprover->givePermissionTo('deposits.approve');

    $memberAccount = Account::firstOrCreate(
        ['user_id' => $this->member->id, 'account_type' => 'savings'],
        ['balance' => 5000]
    );

    $deposit = Transaction::create([
        'account_id' => $memberAccount->id,
        'transaction_type' => 'deposit',
        'amount' => 1234,
        'status' => 'pending',
        'transaction_mode' => 'online',
        'transaction_date' => now(),
    ]);

    $withdrawal = Transaction::create([
        'account_id' => $memberAccount->id,
        'transaction_type' => 'withdrawal',
        'amount' => 4321,
        'status' => 'pending',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
    ]);

    actingAs($depositApprover);
    $response = $this->getJson('/api/services/pending')
        ->assertOk();

    $data = collect($response->json('data'));
    expect($data->pluck('id')->all())->toContain($deposit->id)
        ->and($data->pluck('id')->all())->not->toContain($withdrawal->id);
});

test('members.delete cannot delete an officer account', function () {
    $memberDeleter = User::factory()->create(['account_status' => 'active', 'is_officer' => true]);
    $memberDeleter->givePermissionTo('members.delete');

    $officerTarget = User::factory()->create([
        'account_status' => 'active',
        'is_officer' => true,
        'role' => 'loan',
    ]);
    $officerTarget->assignRole('loan');

    // Attempting to delete officer with members.delete only must fail with 403
    actingAs($memberDeleter);
    $this->deleteJson("/api/members/{$officerTarget->id}")
        ->assertForbidden();

    expect(User::find($officerTarget->id))->not->toBeNull();

    // User with officers.delete can delete the officer
    $officerDeleter = User::factory()->create(['account_status' => 'active', 'is_officer' => true]);
    $officerDeleter->givePermissionTo('officers.delete');

    actingAs($officerDeleter);
    $this->deleteJson("/api/members/{$officerTarget->id}")
        ->assertOk();

    expect(User::find($officerTarget->id))->toBeNull();
});

test('officers.delete cannot delete an administrator account', function () {
    $officerDeleter = User::factory()->create(['account_status' => 'active', 'is_officer' => true]);
    $officerDeleter->givePermissionTo('officers.delete');

    actingAs($officerDeleter);
    $this->deleteJson("/api/members/{$this->admin->id}")
        ->assertForbidden();

    expect(User::find($this->admin->id))->not->toBeNull();
});
