<?php

use App\Models\Notification;
use App\Models\PersonalData;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    Storage::fake('public');
});

test('faqs endpoint returns categorized knowledge base', function () {
    $user = User::factory()->create(['role' => 'member', 'email_verified_at' => now()]);
    $user->assignRole('member');

    $this->actingAs($user)
        ->getJson('/api/faqs')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'label', 'icon', 'description', 'faqs'],
            ],
        ]);
});

test('member can create ticket with image attachment and initial message', function () {
    $personalData = PersonalData::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
    $member = User::factory()->create([
        'personal_data_id' => $personalData->id,
        'role' => 'member',
        'email_verified_at' => now(),
    ]);
    $member->assignRole('member');

    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $admin->assignRole('admin');

    $file = UploadedFile::fake()->image('error_screenshot.png', 600, 400);

    $response = $this->actingAs($member)
        ->postJson('/api/tickets', [
            'subject' => 'Withdrawal OTP not arriving on email',
            'category' => 'withdrawals',
            'priority' => 'high',
            'message' => 'I have tried multiple times to request a savings withdrawal but the 6-digit code does not arrive.',
            'attachments' => [$file],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.subject', 'Withdrawal OTP not arriving on email')
        ->assertJsonPath('data.category', 'withdrawals')
        ->assertJsonPath('data.priority', 'high')
        ->assertJsonPath('data.status', 'open');

    $ticket = Ticket::where('subject', 'Withdrawal OTP not arriving on email')->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->ticket_number)->toStartWith('TCK-');
    expect($ticket->messages)->toHaveCount(1);
    expect($ticket->attachments)->toHaveCount(1);

    // Verify support officer was notified
    $notification = Notification::where('user_id', $admin->id)
        ->where('type', 'ticket_created')
        ->first();
    expect($notification)->not->toBeNull();
});

test('member only sees their own tickets and scope all is restricted to support staff', function () {
    $member1 = User::factory()->create(['role' => 'member', 'email_verified_at' => now()]);
    $member1->assignRole('member');

    $member2 = User::factory()->create(['role' => 'member', 'email_verified_at' => now()]);
    $member2->assignRole('member');

    $ticket1 = Ticket::create([
        'user_id' => $member1->id,
        'subject' => 'Ticket from Member 1',
        'category' => 'loans',
        'priority' => 'medium',
        'status' => 'open',
    ]);

    $ticket2 = Ticket::create([
        'user_id' => $member2->id,
        'subject' => 'Ticket from Member 2',
        'category' => 'savings_deposits',
        'priority' => 'low',
        'status' => 'open',
    ]);

    // Member 1 requests with scope=all, but should be forced to mine
    $response = $this->actingAs($member1)
        ->getJson('/api/tickets?scope=all')
        ->assertOk();

    $ticketIds = collect($response->json('data'))->pluck('id')->all();
    expect($ticketIds)->toContain($ticket1->id);
    expect($ticketIds)->not->toContain($ticket2->id);
});

test('member cannot view other members ticket', function () {
    $member1 = User::factory()->create(['role' => 'member', 'email_verified_at' => now()]);
    $member1->assignRole('member');

    $member2 = User::factory()->create(['role' => 'member', 'email_verified_at' => now()]);
    $member2->assignRole('member');

    $ticket2 = Ticket::create([
        'user_id' => $member2->id,
        'subject' => 'Private Issue',
        'category' => 'account_security',
        'priority' => 'urgent',
        'status' => 'open',
    ]);

    $this->actingAs($member1)
        ->getJson('/api/tickets/'.$ticket2->id)
        ->assertForbidden();
});

test('member can reply to own ticket with attachments', function () {
    $member = User::factory()->create(['role' => 'member', 'email_verified_at' => now()]);
    $member->assignRole('member');

    $ticket = Ticket::create([
        'user_id' => $member->id,
        'subject' => 'Inquiry about share capital',
        'category' => 'membership',
        'priority' => 'low',
        'status' => 'open',
    ]);

    TicketMessage::create([
        'ticket_id' => $ticket->id,
        'user_id' => $member->id,
        'message' => 'First message',
    ]);

    $file = UploadedFile::fake()->image('receipt.jpg', 300, 300);

    $response = $this->actingAs($member)
        ->postJson('/api/tickets/'.$ticket->id.'/messages', [
            'message' => 'Here is the payment receipt for confirmation.',
            'attachments' => [$file],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.message', 'Here is the payment receipt for confirmation.');

    expect($ticket->fresh()->messages)->toHaveCount(2);
    expect($ticket->fresh()->attachments)->toHaveCount(1);
});

test('member cannot update status or assign officer', function () {
    $member = User::factory()->create(['role' => 'member', 'email_verified_at' => now()]);
    $member->assignRole('member');

    $ticket = Ticket::create([
        'user_id' => $member->id,
        'subject' => 'Testing unauthorized operations',
        'category' => 'other',
        'priority' => 'medium',
        'status' => 'open',
    ]);

    $this->actingAs($member)
        ->patchJson('/api/tickets/'.$ticket->id.'/status', ['status' => 'resolved'])
        ->assertForbidden();

    $this->actingAs($member)
        ->patchJson('/api/tickets/'.$ticket->id.'/assign', ['assigned_to' => $member->id])
        ->assertForbidden();
});

test('manager and accountant can view all tickets, update status, and assign officer', function () {
    $manager = User::factory()->create(['role' => 'manager', 'email_verified_at' => now()]);
    $manager->assignRole('manager');

    $accountant = User::factory()->create(['role' => 'accountant', 'email_verified_at' => now()]);
    $accountant->assignRole('accountant');

    $member = User::factory()->create(['role' => 'member', 'email_verified_at' => now()]);
    $member->assignRole('member');

    $ticket = Ticket::create([
        'user_id' => $member->id,
        'subject' => 'Loan product clarification',
        'category' => 'loans',
        'priority' => 'high',
        'status' => 'open',
    ]);

    // Manager can view all tickets
    $response = $this->actingAs($manager)
        ->getJson('/api/tickets?scope=all')
        ->assertOk();

    $ticketIds = collect($response->json('data'))->pluck('id')->all();
    expect($ticketIds)->toContain($ticket->id);

    // Manager assigns ticket to accountant
    $this->actingAs($manager)
        ->patchJson('/api/tickets/'.$ticket->id.'/assign', ['assigned_to' => $accountant->id])
        ->assertOk()
        ->assertJsonPath('data.assigned_to', $accountant->id);

    expect($ticket->fresh()->assigned_to)->toBe($accountant->id);

    // Accountant updates status to in_progress
    $this->actingAs($accountant)
        ->patchJson('/api/tickets/'.$ticket->id.'/status', ['status' => 'in_progress'])
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    expect($ticket->fresh()->status)->toBe('in_progress');

    // Accountant updates status to resolved
    $this->actingAs($accountant)
        ->patchJson('/api/tickets/'.$ticket->id.'/status', ['status' => 'resolved'])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');

    $freshTicket = $ticket->fresh();
    expect($freshTicket->status)->toBe('resolved');
    expect($freshTicket->resolved_at)->not->toBeNull();
});

test('support officers list endpoint returns managers, accountants, and admins', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $admin->assignRole('admin');

    $manager = User::factory()->create(['role' => 'manager', 'email_verified_at' => now()]);
    $manager->assignRole('manager');

    $this->actingAs($admin)
        ->getJson('/api/tickets/support-officers')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'role'],
            ],
        ]);
});
