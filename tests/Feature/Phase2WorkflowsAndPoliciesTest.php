<?php

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->manager = User::where('email', 'manager@example.com')->firstOrFail();
    $this->loanOfficer = User::where('email', 'loan@example.com')->firstOrFail();
    $this->accountant = User::where('email', 'accountant@example.com')->firstOrFail();
    $this->accountant = User::where('email', 'accountant@example.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@example.com')->firstOrFail();

    Storage::fake('local');
    Storage::fake('public');
});

test('loan officer can encode loan and sees can_encode true', function () {
    actingAs($this->loanOfficer);

    $indexResponse = $this->getJson('/api/loans')
        ->assertOk();

    expect($indexResponse->json('can_encode'))->toBeTrue();

    $payload = [
        'user_id' => $this->member->id,
        'loan_product' => 'business',
        'loan_type' => 'business',
        'loan_status' => 'pending',
        'loan_amount' => 50000,
        'interest_rate' => 3.5,
        'term_months' => 12,
        'payment_frequency' => 'monthly',
        'applied_at' => now()->toDateString(),
    ];

    $response = $this->postJson('/api/loans', $payload)
        ->assertStatus(201);

    expect($response->json('data.loan_amount'))->toEqual(50000);
});

test('loan officer with loans.disburse can disburse approved loan', function () {
    $memberAccount = Account::firstOrCreate(
        ['user_id' => $this->member->id, 'account_type' => 'savings'],
        ['balance' => 1000]
    );

    $loan = Loan::create([
        'user_id' => $this->member->id,
        'loan_product' => 'business',
        'loan_type' => 'business',
        'loan_status' => 'approved',
        'loan_amount' => 20000,
        'interest_rate' => 3,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'applied_at' => now(),
        'approved_at' => now(),
        'approved_by' => $this->manager->id,
    ]);

    $this->loanOfficer->givePermissionTo('loans.disburse');
    actingAs($this->loanOfficer);
    $this->postJson("/api/loans/{$loan->id}/disburse")
        ->assertOk();

    expect($loan->fresh()->loan_status)->toBe('active')
        ->and((float) $memberAccount->fresh()->balance)->toBe(21000.0);
});

test('loan officer or borrower can upload loan document', function () {
    $loan = Loan::create([
        'user_id' => $this->member->id,
        'loan_product' => 'business',
        'loan_type' => 'business',
        'loan_status' => 'pending',
        'loan_amount' => 10000,
        'interest_rate' => 3,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'applied_at' => now(),
    ]);

    $file = UploadedFile::fake()->create('contract.pdf', 50, 'application/pdf');

    // Loan officer upload
    actingAs($this->loanOfficer);
    $this->postJson("/api/loans/{$loan->id}/documents", [
        'document' => $file,
    ])->assertStatus(201);

    // Borrower upload
    $file2 = UploadedFile::fake()->create('valid_id.pdf', 50, 'application/pdf');
    actingAs($this->member);
    $this->postJson("/api/loans/{$loan->id}/documents", [
        'document' => $file2,
    ])->assertStatus(201);
});

test('logs endpoint honors logs.view permission and rejects unauthorized users', function () {
    // Regular member has no logs.view
    actingAs($this->member);
    $this->getJson('/api/logs')
        ->assertForbidden();

    // User with logs.view can view logs
    $auditor = User::factory()->create(['account_status' => 'active', 'is_officer' => true]);
    $auditor->givePermissionTo('logs.view');

    actingAs($auditor);
    $this->getJson('/api/logs')
        ->assertOk()
        ->assertJsonStructure(['data', 'pagination', 'filters']);
});

test('ledger store, void, and export honor specific transaction permissions', function () {
    $memberAccount = Account::firstOrCreate(
        ['user_id' => $this->member->id, 'account_type' => 'savings'],
        ['balance' => 5000]
    );

    // transactions.create allows manual ledger entry
    $clerk = User::factory()->create(['account_status' => 'active', 'is_officer' => true]);
    $clerk->givePermissionTo('transactions.create');

    actingAs($clerk);
    $createResponse = $this->postJson('/api/ledger', [
        'transaction_type' => 'deposit',
        'amount' => 100,
        'account_id' => $memberAccount->id,
    ])->assertStatus(201);

    $transactionId = $createResponse->json('data.id');
    $transaction = Transaction::findOrFail($transactionId);

    // Clerk without transactions.update cannot delete
    $this->deleteJson("/api/ledger/{$transaction->id}")
        ->assertForbidden();

    // User with transactions.update can delete pending transaction
    $voider = User::factory()->create(['account_status' => 'active', 'is_officer' => true]);
    $voider->givePermissionTo('transactions.update');

    actingAs($voider);
    $this->deleteJson("/api/ledger/{$transaction->id}")
        ->assertOk();

    expect(Transaction::find($transactionId))->toBeNull();

    // User with transactions.export can export ledger
    $exporter = User::factory()->create(['account_status' => 'active', 'is_officer' => true]);
    $exporter->givePermissionTo('transactions.export');

    actingAs($exporter);
    $this->get('/api/ledger/export')
        ->assertOk();
});

test('report balance sheet download honors reports.export permission', function () {
    $exporter = User::factory()->create(['account_status' => 'active', 'is_officer' => true]);
    $exporter->givePermissionTo('reports.export');

    actingAs($exporter);
    $this->get('/api/reports/balance-sheet/download')
        ->assertOk();
});
