<?php

use App\Models\Account;
use App\Models\BackupRun;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\QaChairmanSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(AccountSeeder::class);

    // Officers (seeded by UserSeeder)
    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->accountant = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $this->loan = User::where('email', 'leo.baluyot039+loan@gmail.com')->firstOrFail();

    // Branches
    $this->mainBranch = Branch::where('code', 'BR000001')->firstOrFail();
    $this->officeBranch = Branch::where('code', 'BR000002')->firstOrFail();

    // Members in main branch
    $this->mainMember = User::where('user_number', 'USR000007')->firstOrFail(); // Arjay Ramos

    // Members in office branch
    $this->officeMember = User::where('user_number', 'USR000012')->firstOrFail(); // Jeral Jean Cabillon
});

// ---------------------------------------------------------------------------
// F015-1: GET /api/dashboard — unauthenticated
// ---------------------------------------------------------------------------

test('F015-1: GET /api/dashboard returns 401 for unauthenticated request', function () {
    $response = $this->getJson('/api/dashboard');
    $response->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// F015-2: Admin dashboard
// ---------------------------------------------------------------------------

test('F015-2: GET /api/dashboard returns admin overview for admin role', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'greeting',
            'total_members',
            'total_officers',
            'total_savings',
            'pending_transactions',
            'pending_loans',
            'loans_active',
            'month_deposits',
            'month_withdrawals',
            'backup_status' => [
                'health',
                'last_run_at',
                'tiers' => ['hourly', 'daily', 'offsite'],
            ],
        ]);
});

// ---------------------------------------------------------------------------
// F015-2b: Admin dashboard includes backup status derived from backup runs
// ---------------------------------------------------------------------------

test('F015-2b: admin dashboard backup status reflects per-tier backup runs', function () {
    BackupRun::create([
        'tier' => 'hourly',
        'scope' => 'both',
        'status' => 'success',
        'filename' => 'Settlers Credit Cooperative/backup-hourly-2026-09-22-01-00-00.zip',
        'size' => 2048,
        'started_at' => now()->subHours(2),
        'finished_at' => now()->subHours(2)->addMinutes(1),
    ]);

    BackupRun::create([
        'tier' => 'offsite',
        'scope' => 'both',
        'status' => 'failed',
        'filename' => 'Settlers Credit Cooperative/backup-offsight-2026-09-22-01-00-00.zip',
        'message' => 'Could not reach Backblaze B2.',
        'started_at' => now()->subHours(3),
        'finished_at' => now()->subHours(3)->addMinutes(1),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('backup_status.health', 'attention')
        ->assertJsonPath('backup_status.tiers.hourly.last_status', 'success')
        ->assertJsonPath('backup_status.tiers.hourly.last_scope', 'both')
        ->assertJsonPath('backup_status.tiers.hourly.last_size', 2048)
        ->assertJsonPath('backup_status.tiers.offsite.last_status', 'failed')
        ->assertJsonPath('backup_status.tiers.offsite.message', 'Could not reach Backblaze B2.')
        ->assertJsonPath('backup_status.tiers.daily.last_status', null);
});

test('chairman dashboard gives a cooperative-wide overview without administrator backup details', function () {
    $this->seed(QaChairmanSeeder::class);
    $this->chairman = User::where('email', 'leo.baluyot039+chairman@gmail.com')->firstOrFail();
    $memberCount = User::query()->memberUsers()->count();
    $this->actingAs($this->chairman)
        ->getJson('/api/dashboard?branch_id='.$this->officeBranch->id)
        ->assertOk()
        ->assertJsonStructure(['total_members', 'total_savings', 'total_share_capital', 'pending_transactions', 'pending_loans', 'loans_active', 'month_net', 'loan_status_series'])
        ->assertJsonPath('total_members', $memberCount)
        ->assertJsonMissingPath('backup_status');
});

test('chairman share capital total matches all share-capital account balances', function () {
    $this->seed(QaChairmanSeeder::class);
    $chairman = User::where('email', 'leo.baluyot039+chairman@gmail.com')->firstOrFail();
    $expected = (float) Account::query()->where('account_type', 'share-capital')->sum('balance');

    $response = $this->actingAs($chairman)
        ->getJson('/api/dashboard?branch_id='.$this->officeBranch->id)
        ->assertOk();

    expect((float) $response->json('total_share_capital'))->toBe($expected)
        ->and((float) $response->json('share_capital_total'))->toBe($expected);
});

test('dashboard and services pending-loan totals exclude rejected review requests', function () {
    foreach (range(1, 3) as $index) {
        Loan::factory()->create([
            'user_id' => $this->mainMember->id,
            'loan_status' => 'pending',
            'review_status' => 'review_rejected',
        ]);
    }

    Loan::factory()->create([
        'user_id' => $this->mainMember->id,
        'loan_status' => 'pending',
        'review_status' => 'under_review',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('pending_loans', 1);

    $this->getJson('/api/services')
        ->assertOk()
        ->assertJsonPath('pending_loans', 1);

    $this->actingAs($this->loan)
        ->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('pending_loans', 1)
        ->assertJsonCount(1, 'pending_loans_list');
});

// ---------------------------------------------------------------------------
// F015-3: Manager dashboard
// ---------------------------------------------------------------------------

test('F015-3: GET /api/dashboard returns manager overview for manager role', function () {
    $response = $this->actingAs($this->manager)->getJson('/api/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'greeting',
            'total_members',
            'loans_active',
            'month_deposits',
            'month_withdrawals',
        ]);
});

// ---------------------------------------------------------------------------
// F015-4: Accountant/treasurer dashboard
// ---------------------------------------------------------------------------

test('F015-4: GET /api/dashboard returns treasurer overview for accountant role', function () {
    $response = $this->actingAs($this->accountant)->getJson('/api/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'greeting',
            'total_savings',
            'total_share_capital',
            'month_deposits',
            'month_withdrawals',
        ]);
});

// ---------------------------------------------------------------------------
// F015-6: Loan officer dashboard
// ---------------------------------------------------------------------------

test('F015-6: GET /api/dashboard returns loan officer overview for loan role', function () {
    $response = $this->actingAs($this->loan)->getJson('/api/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'greeting',
            'loans_pending',
            'loans_active',
            'loans_overdue',
            'loans_outstanding',
        ]);
});

// ---------------------------------------------------------------------------
// F015-7: Member dashboard (non-officer)
// ---------------------------------------------------------------------------

test('F015-7: GET /api/dashboard returns member overview for member role', function () {
    $response = $this->actingAs($this->mainMember)->getJson('/api/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'greeting',
            'user_number',
            'total_balance',
            'total_savings',
            'announcements',
        ]);
});

// ---------------------------------------------------------------------------
// F015-8: Dashboard dispatches correctly — admin gets admin shape, not member
// ---------------------------------------------------------------------------

test('F015-8: Dashboard for admin does NOT return member-only fields', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/dashboard');

    $response->assertOk();
    // Admin dashboard should NOT contain member-specific field 'user_number'
    $data = $response->json();
    expect(array_key_exists('user_number', $data))->toBeFalse();
});

// ---------------------------------------------------------------------------
// F015-9: GET /api/members/search — member without permission gets 403
// ---------------------------------------------------------------------------

test('F015-9: GET /api/members/search returns 403 for regular member without members.view', function () {
    $response = $this->actingAs($this->mainMember)->getJson('/api/members/search?q=Arjay');
    $response->assertForbidden();
});

// ---------------------------------------------------------------------------
// F015-10: GET /api/members/search — admin can search members
// ---------------------------------------------------------------------------

test('F015-10: GET /api/members/search returns results for admin (has members.view)', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/members/search?q=Arjay');

    $response->assertOk()
        ->assertJsonStructure(['data']);

    $data = $response->json('data');
    // Should find Arjay Ramos
    expect(count($data))->toBeGreaterThanOrEqual(1);
    expect($data[0])->toHaveKeys(['id', 'name', 'user_number']);
});

// ---------------------------------------------------------------------------
// F015-11: GET /api/members/search — manager can search members
// ---------------------------------------------------------------------------

test('F015-11: GET /api/members/search returns results for manager (has members.view)', function () {
    $response = $this->actingAs($this->manager)->getJson('/api/members/search?q=Arjay');

    $response->assertOk()
        ->assertJsonStructure(['data']);
});

// ---------------------------------------------------------------------------
// F015-12: GET /api/members/search — blank query returns empty
// ---------------------------------------------------------------------------

test('F015-12: GET /api/members/search returns empty array for blank query', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/members/search?q=');

    $response->assertOk()
        ->assertJson(['data' => []]);
});

// ---------------------------------------------------------------------------
// F015-13: POST /api/services/deposit — member cannot deposit to another member
// ---------------------------------------------------------------------------

test('F015-13: Regular member cannot deposit to another member account (403)', function () {
    $response = $this->actingAs($this->mainMember)->postJson('/api/services/deposit', [
        'member_id' => $this->officeMember->id,
        'amount' => 500,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
    ]);

    // 403 either from permission check or branch enforcement
    $response->assertForbidden();
});

// ---------------------------------------------------------------------------
// F015-14: POST /api/services/deposit — admin can deposit to member in any branch
// ---------------------------------------------------------------------------

test('F015-14: Manager can deposit to member account in any branch (act-on-behalf)', function () {
    // Ensure member has savings account
    $savingsAccount = Account::where('user_id', $this->officeMember->id)
        ->where('account_type', 'savings')
        ->first();

    if (! $savingsAccount) {
        Account::create([
            'user_id' => $this->officeMember->id,
            'account_type' => 'savings',
            'balance' => 5000,
        ]);
    }

    $this->manager->givePermissionTo('branches.bypass');
    $response = $this->actingAs($this->manager)->postJson('/api/services/deposit', [
        'member_id' => $this->officeMember->id,
        'amount' => 500,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
    ]);

    // Should succeed (201 Created) — manager has bypass
    expect($response->status())->not->toBe(403);
    $response->assertStatus(201);
});

// ---------------------------------------------------------------------------
// F015-15: POST /api/services/deposit — officer with branch bypass can act on member in different branch
// ---------------------------------------------------------------------------

test('F015-15: Loan officer cannot deposit even with branch bypass (403)', function () {
    // loan officer is in office branch; mainMember is in main branch
    $savingsAccount = Account::where('user_id', $this->mainMember->id)
        ->where('account_type', 'savings')
        ->first();

    if (! $savingsAccount) {
        Account::create([
            'user_id' => $this->mainMember->id,
            'account_type' => 'savings',
            'balance' => 5000,
        ]);
    }

    $response = $this->actingAs($this->loan)->postJson('/api/services/deposit', [
        'member_id' => $this->mainMember->id,
        'amount' => 500,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
    ]);

    $response->assertStatus(403);
});

// ---------------------------------------------------------------------------
// F015-16: POST /api/services/deposit — officer same-branch can act on behalf
// ---------------------------------------------------------------------------

test('F015-16: Manager (main branch) can deposit to member in same branch (act-on-behalf)', function () {
    // manager is in main branch; mainMember is in main branch
    $savingsAccount = Account::where('user_id', $this->mainMember->id)
        ->where('account_type', 'savings')
        ->first();

    if (! $savingsAccount) {
        Account::create([
            'user_id' => $this->mainMember->id,
            'account_type' => 'savings',
            'balance' => 5000,
        ]);
    }

    $response = $this->actingAs($this->manager)->postJson('/api/services/deposit', [
        'member_id' => $this->mainMember->id,
        'amount' => 500,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
    ]);

    expect($response->status())->not->toBe(403);
    $response->assertStatus(201);
});

// ---------------------------------------------------------------------------
// F015-17: POST /api/services/withdrawal — member cannot withdraw from another member
// ---------------------------------------------------------------------------

test('F015-17: Regular member cannot withdraw from another member account (403)', function () {
    $response = $this->actingAs($this->mainMember)->postJson('/api/services/withdrawal', [
        'member_id' => $this->officeMember->id,
        'amount' => 200,
        'transaction_mode' => 'counter',
    ]);

    $response->assertForbidden();
});

// ---------------------------------------------------------------------------
// F015-18: POST /api/services/withdrawal — manager can withdraw from member in same branch
// ---------------------------------------------------------------------------

test('F015-18: Manager can withdraw from member account in same branch (act-on-behalf)', function () {
    $savingsAccount = Account::where('user_id', $this->mainMember->id)
        ->where('account_type', 'savings')
        ->first();

    if (! $savingsAccount) {
        Account::create([
            'user_id' => $this->mainMember->id,
            'account_type' => 'savings',
            'balance' => 5000,
        ]);
    }

    $response = $this->actingAs($this->manager)->postJson('/api/services/withdrawal', [
        'member_id' => $this->mainMember->id,
        'amount' => 200,
        'transaction_mode' => 'counter',
    ]);

    // NOT 403 (branch and officer permission OK)
    expect($response->status())->not->toBe(403);
});

// ---------------------------------------------------------------------------
// F015-19: POST /api/services/withdrawal — officer with branch bypass can withdraw cross-branch
// ---------------------------------------------------------------------------

test('F015-19: Loan officer cannot withdraw even with branch bypass (403)', function () {
    // loan officer is in office branch; mainMember is in main branch
    $savingsAccount = Account::where('user_id', $this->mainMember->id)
        ->where('account_type', 'savings')
        ->first();

    if (! $savingsAccount) {
        Account::create([
            'user_id' => $this->mainMember->id,
            'account_type' => 'savings',
            'balance' => 5000,
        ]);
    }

    $response = $this->actingAs($this->loan)->postJson('/api/services/withdrawal', [
        'member_id' => $this->mainMember->id,
        'amount' => 200,
        'transaction_mode' => 'counter',
    ]);

    $response->assertStatus(403);
});

// ---------------------------------------------------------------------------
// F015-20: GET /api/services — requires services.view permission
// ---------------------------------------------------------------------------

test('F015-20: GET /api/services returns 200 for member (services.view is in member role) but can_manage_member_services is false', function () {
    $response = $this->actingAs($this->mainMember)->getJson('/api/services');

    // Members have services.view permission (seeded) — they can access their own services
    $response->assertOk()
        ->assertJsonStructure([
            'can_manage_member_services',
            'pending_approvals',
            'pending_loans',
        ]);

    // But they cannot manage OTHER members' services
    $response->assertJsonPath('can_manage_member_services', false);
});

// ---------------------------------------------------------------------------
// F015-21: GET /api/services — returns dashboard for officer with services.view
// ---------------------------------------------------------------------------

test('F015-21: GET /api/services returns officer service dashboard for admin', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/services');

    $response->assertOk()
        ->assertJsonStructure([
            'is_admin',
            'can_manage_member_services',
            'pending_approvals',
            'pending_loans',
        ]);
});

// ---------------------------------------------------------------------------
// F015-22: POST /api/services/deposit — unauthenticated returns 401
// ---------------------------------------------------------------------------

test('F015-22: POST /api/services/deposit returns 401 for unauthenticated request', function () {
    $response = $this->postJson('/api/services/deposit', [
        'amount' => 500,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
    ]);

    $response->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// F015-23: GET /api/members — accessible by admin (members.view)
// ---------------------------------------------------------------------------

test('F015-23: GET /api/members returns member list for admin', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/members');

    $response->assertOk()
        ->assertJsonStructure(['data']);

    $data = $response->json('data');
    expect(count($data))->toBeGreaterThanOrEqual(1);
});

// ---------------------------------------------------------------------------
// F015-24: GET /api/members — 403 for member without permission
// ---------------------------------------------------------------------------

test('F015-24: GET /api/members returns 403 for regular member', function () {
    $response = $this->actingAs($this->mainMember)->getJson('/api/members');
    $response->assertForbidden();
});
