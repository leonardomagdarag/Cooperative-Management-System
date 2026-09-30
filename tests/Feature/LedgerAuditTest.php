<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(AccountSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->accountant = User::where('email', 'accountant@example.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@example.com')->firstOrFail();

    $this->mainBranch = Branch::where('code', 'BR000001')->firstOrFail();
    $this->officeBranch = Branch::where('code', 'BR000002')->firstOrFail();

    $this->memberAccount = Account::where('user_id', $this->member->id)->where('account_type', 'savings')->firstOrFail();
});

test('F025-1: unauthenticated requests to ledger endpoints return 401', function () {
    $this->getJson('/api/ledger')->assertUnauthorized();
    $this->getJson('/api/ledger/summary')->assertUnauthorized();
    $this->getJson('/api/ledger/export')->assertUnauthorized();
    $this->postJson('/api/ledger', [])->assertUnauthorized();
    $this->putJson('/api/ledger/1', [])->assertUnauthorized();
    $this->deleteJson('/api/ledger/1')->assertUnauthorized();
});

test('F025-2: regular member receives 403 on ledger endpoints', function () {
    $t = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'deposit',
        'amount' => 100.00,
        'status' => 'completed',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->admin->id,
    ]);

    $this->actingAs($this->member);

    $this->getJson('/api/ledger')->assertForbidden();
    $this->getJson('/api/ledger/summary')->assertForbidden();
    $this->getJson('/api/ledger/export')->assertForbidden();
    $this->postJson('/api/ledger', [])->assertForbidden();
    $this->putJson("/api/ledger/{$t->id}", [])->assertForbidden();
    $this->deleteJson("/api/ledger/{$t->id}")->assertForbidden();
});

test('F025-3: accountant can list ledger transactions with pagination and sorting', function () {
    $response = $this->actingAs($this->accountant)->getJson('/api/ledger?per_page=10&sort=created_at&direction=desc');

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
            'can_edit',
        ]);

    expect($response->json('can_edit'))->toBeTrue();
});

test('F025-4: ledger filters by transaction_type category and search query', function () {
    $t1 = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'deposit',
        'amount' => 888.00,
        'note' => 'UniqueSearchableDepositNote',
        'status' => 'completed',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->accountant->id,
    ]);

    $t2 = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'withdrawal',
        'amount' => 200.00,
        'note' => 'AnotherNote',
        'status' => 'completed',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->accountant->id,
    ]);

    // Filter by category=deposit
    $depResponse = $this->actingAs($this->accountant)->getJson('/api/ledger?category=deposit');
    $depResponse->assertOk();
    $types = collect($depResponse->json('data'))->pluck('transaction_type')->unique()->all();
    expect($types)->toEqual(['deposit']);

    // Filter by search
    $searchResponse = $this->actingAs($this->accountant)->getJson('/api/ledger?search=UniqueSearchableDepositNote');
    $searchResponse->assertOk();
    $notes = collect($searchResponse->json('data'))->pluck('note')->all();
    expect($notes)->toContain('UniqueSearchableDepositNote')
        ->and($notes)->not->toContain('AnotherNote');
});

test('F025-5: ledger summary returns 200 OK with correct payload structure (Fixed F025-01)', function () {
    $response = $this->actingAs($this->accountant)->getJson('/api/ledger/summary?range=today');

    $response->assertOk()
        ->assertJsonStructure([
            'range',
            'deposits',
            'withdrawals',
            'loan_payments',
            'loan_disbursements',
            'loan_count',
        ])
        ->assertJsonPath('range', 'today');
});

test('F025-6: ledger export streams CSV file with headers and records', function () {
    $response = $this->actingAs($this->accountant)->get('/api/ledger/export');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();
    expect($content)->toContain('Transaction #')
        ->and($content)->toContain('Amount')
        ->and($content)->toContain('Status');
});

test('F025-7: ledger exposes entries across branches for officers holding branch bypass', function () {
    // Give accountant officeBranch while holding the default branch bypass
    $this->accountant->update(['branch_id' => $this->officeBranch->id]);

    $tMain = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->mainBranch->id,
        'transaction_type' => 'deposit',
        'amount' => 500.00,
        'status' => 'completed',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->admin->id,
        'note' => 'F025-7 branch bypass probe, main office',
    ]);

    $tOffice = Transaction::create([
        'account_id' => $this->memberAccount->id,
        'branch_id' => $this->officeBranch->id,
        'transaction_type' => 'deposit',
        'amount' => 600.00,
        'status' => 'completed',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
        'recorded_by' => $this->accountant->id,
        'note' => 'F025-7 branch bypass probe, branch office',
    ]);

    // The seeded ledger already holds a full history, so the probe is narrowed
    // down by its note: this still asks for both branches in one response.
    $response = $this->actingAs($this->accountant)->getJson('/api/ledger?per_page=50&search='.urlencode('F025-7 branch bypass probe'));
    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($tOffice->id)
        ->and($ids)->toContain($tMain->id);
});
