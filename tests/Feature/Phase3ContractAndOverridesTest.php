<?php

use App\Models\OfficerPermissionOverride;
use App\Models\Ticket;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->accountant = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $this->accountant = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $this->loanOfficer = User::where('email', 'leo.baluyot039+loan@gmail.com')->firstOrFail();
    $this->member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
});

test('auth user endpoint reflects officer permission overrides in permissions array', function () {
    actingAs($this->manager);

    // Initial check: manager has members.update by default
    $initialResponse = $this->getJson('/api/user')
        ->assertOk();
    expect($initialResponse->json('permissions'))->toContain('members.update');

    // Add a DISALLOW override for members.update
    OfficerPermissionOverride::create([
        'user_id' => $this->manager->id,
        'permission' => 'members.update',
        'effect' => OfficerPermissionOverride::EFFECT_DISALLOW,
    ]);

    $overrideResponse = $this->getJson('/api/user')
        ->assertOk();
    expect($overrideResponse->json('permissions'))->not->toContain('members.update');

    // Add an ALLOW override for backups.view (which manager does not normally have)
    OfficerPermissionOverride::create([
        'user_id' => $this->manager->id,
        'permission' => 'backups.view',
        'effect' => OfficerPermissionOverride::EFFECT_ALLOW,
    ]);

    $overrideResponse2 = $this->getJson('/api/user')
        ->assertOk();
    expect($overrideResponse2->json('permissions'))->toContain('backups.view');
});

test('accountant can view all tickets and manage status as support staff', function () {
    $ticket = Ticket::create([
        'user_id' => $this->member->id,
        'subject' => 'Question about dividend schedule',
        'category' => 'general',
        'priority' => 'medium',
        'status' => 'open',
    ]);

    actingAs($this->accountant);

    // accountant should be treated as support staff and view all tickets
    $response = $this->getJson('/api/tickets?scope=all')
        ->assertOk();

    $ticketIds = collect($response->json('data'))->pluck('id')->all();
    expect($ticketIds)->toContain($ticket->id);

    // accountant can update status
    $this->patchJson("/api/tickets/{$ticket->id}/status", [
        'status' => 'in_progress',
    ])->assertOk();

    expect($ticket->fresh()->status)->toBe('in_progress');

    // accountant can reply
    $this->postJson("/api/tickets/{$ticket->id}/messages", [
        'message' => 'Hello, dividends will be released next week.',
    ])->assertStatus(201);
});

test('permission catalog groups include tickets, dashboard, notifications, and profile', function () {
    $groups = PermissionCatalog::groups();

    expect($groups)->toHaveKey('Tickets')
        ->and($groups)->toHaveKey('Dashboard')
        ->and($groups)->toHaveKey('Notifications')
        ->and($groups)->toHaveKey('Profile');

    expect($groups['Tickets'])->toContain('tickets.view', 'tickets.reply', 'tickets.manage');
});
