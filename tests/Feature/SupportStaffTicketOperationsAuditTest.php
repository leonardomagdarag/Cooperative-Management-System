<?php

use App\Models\PersonalData;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->manager = User::where('email', 'manager@example.com')->firstOrFail();
    $this->accountant = User::where('email', 'accountant@example.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@example.com')->firstOrFail();
});

test('F030-1: unauthenticated requests to tickets endpoints return 401', function () {
    $this->getJson('/api/tickets')->assertUnauthorized();
    $this->getJson('/api/tickets/support-officers')->assertUnauthorized();
    $this->getJson('/api/tickets/1')->assertUnauthorized();
    $this->postJson('/api/tickets/1/messages', [])->assertUnauthorized();
    $this->patchJson('/api/tickets/1/status', [])->assertUnauthorized();
    $this->patchJson('/api/tickets/1/assign', [])->assertUnauthorized();
});

test('F030-2: support staff can post internal notes; members cannot view them (Defect F030-01 fixed)', function () {
    $ticket = Ticket::create([
        'user_id' => $this->member->id,
        'subject' => 'Account Discrepancy',
        'category' => 'general',
        'priority' => 'high',
        'status' => 'open',
    ]);

    // Support officer posts internal note
    $response = $this->actingAs($this->accountant)->postJson("/api/tickets/{$ticket->id}/messages", [
        'message' => 'Internal investigation: Verified with banking partner.',
        'is_internal_note' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.is_internal_note', true);

    // Member views ticket detail: internal note must NOT leak to the requester
    $memberResponse = $this->actingAs($this->member)->getJson("/api/tickets/{$ticket->id}");
    $memberResponse->assertOk();

    $messages = $memberResponse->json('data.messages');
    $internalNotes = collect($messages)->filter(fn ($m) => $m['is_internal_note'] === true);
    expect($internalNotes)->toBeEmpty();

    // Member list view must not expose an internal note snippet
    $listResponse = $this->actingAs($this->member)->getJson('/api/tickets');
    $listResponse->assertOk();
    $snippet = collect($listResponse->json('data'))
        ->firstWhere('id', $ticket->id)['latest_message_snippet'] ?? null;
    expect($snippet)->toBeNull();

    // Support staff still see the internal note
    $staffResponse = $this->actingAs($this->accountant)->getJson("/api/tickets/{$ticket->id}");
    $staffResponse->assertOk();
    $staffMessages = $staffResponse->json('data.messages');
    $staffInternalNotes = collect($staffMessages)->filter(fn ($m) => $m['is_internal_note'] === true);
    expect($staffInternalNotes)->not->toBeEmpty();
});

test('F030-3: ticket status transition lifecycle records timestamps correctly', function () {
    $ticket = Ticket::create([
        'user_id' => $this->member->id,
        'subject' => 'ATM Card Issue',
        'category' => 'cards',
        'priority' => 'medium',
        'status' => 'open',
    ]);

    $this->actingAs($this->manager);

    // open -> in_progress
    $res1 = $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'in_progress']);
    $res1->assertOk()->assertJsonPath('data.status', 'in_progress');
    expect($ticket->fresh()->status)->toBe('in_progress');

    // in_progress -> resolved
    $res2 = $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'resolved']);
    $res2->assertOk()->assertJsonPath('data.status', 'resolved');
    $ticket->refresh();
    expect($ticket->status)->toBe('resolved')
        ->and($ticket->resolved_at)->not->toBeNull();

    // resolved -> closed
    $res3 = $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'closed']);
    $res3->assertOk()->assertJsonPath('data.status', 'closed');
    $ticket->refresh();
    expect($ticket->status)->toBe('closed')
        ->and($ticket->closed_at)->not->toBeNull();
});

test('F030-4: replying to an open ticket as support staff automatically transitions status to in_progress', function () {
    $ticket = Ticket::create([
        'user_id' => $this->member->id,
        'subject' => 'Help with portal',
        'category' => 'general',
        'priority' => 'low',
        'status' => 'open',
    ]);

    $response = $this->actingAs($this->accountant)->postJson("/api/tickets/{$ticket->id}/messages", [
        'message' => 'Hello, I am reviewing your request right now.',
    ]);

    $response->assertStatus(201);
    expect($ticket->fresh()->status)->toBe('in_progress');
});

test('F030-5: replying to a resolved ticket as member re-opens it to in_progress', function () {
    $ticket = Ticket::create([
        'user_id' => $this->member->id,
        'subject' => 'Old Inquiry',
        'category' => 'general',
        'priority' => 'low',
        'status' => 'resolved',
        'resolved_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->member)->postJson("/api/tickets/{$ticket->id}/messages", [
        'message' => 'The issue is actually still recurring.',
    ]);

    $response->assertStatus(201);
    $ticket->refresh();
    expect($ticket->status)->toBe('in_progress')
        ->and($ticket->resolved_at)->toBeNull();
});

test('F030-6: support staff can assign and unassign ticket officer', function () {
    $ticket = Ticket::create([
        'user_id' => $this->member->id,
        'subject' => 'Unassigned inquiry',
        'category' => 'general',
        'priority' => 'medium',
        'status' => 'open',
    ]);

    $this->actingAs($this->admin);

    // Assign to manager
    $res1 = $this->patchJson("/api/tickets/{$ticket->id}/assign", ['assigned_to' => $this->manager->id]);
    $res1->assertOk();
    expect($ticket->fresh()->assigned_to)->toBe($this->manager->id);

    // Unassign
    $res2 = $this->patchJson("/api/tickets/{$ticket->id}/assign", ['assigned_to' => null]);
    $res2->assertOk();
    expect($ticket->fresh()->assigned_to)->toBeNull();
});

test('F030-7: ticket filters by priority, category, and status_group', function () {
    Ticket::create([
        'user_id' => $this->member->id,
        'subject' => 'Urgent Deposit Missing',
        'category' => 'deposits',
        'priority' => 'urgent',
        'status' => 'open',
    ]);

    Ticket::create([
        'user_id' => $this->member->id,
        'subject' => 'Low General Question',
        'category' => 'general',
        'priority' => 'low',
        'status' => 'closed',
    ]);

    $this->actingAs($this->accountant);

    // Filter active status_group
    $resActive = $this->getJson('/api/tickets?status_group=active');
    $resActive->assertOk();
    $statuses = collect($resActive->json('data'))->pluck('status')->all();
    expect($statuses)->toContain('open')
        ->and($statuses)->not->toContain('closed');

    // Filter priority=urgent
    $resUrgent = $this->getJson('/api/tickets?priority=urgent');
    $resUrgent->assertOk();
    $priorities = collect($resUrgent->json('data'))->pluck('priority')->all();
    expect($priorities)->toEqual(['urgent']);
});
