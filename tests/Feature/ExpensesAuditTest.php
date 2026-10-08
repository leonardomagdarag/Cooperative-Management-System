<?php

use App\Models\Branch;
use App\Models\Expense;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->accountant = User::where('email', 'accountant@example.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@example.com')->firstOrFail();

    $this->mainBranch = Branch::where('code', 'BR000001')->firstOrFail();
    $this->officeBranch = Branch::where('code', 'BR000002')->firstOrFail();
});

test('F026-1: unauthenticated requests to expenses endpoints return 401', function () {
    $this->getJson('/api/expenses')->assertUnauthorized();
    $this->postJson('/api/expenses', [])->assertUnauthorized();
    $this->putJson('/api/expenses/1', [])->assertUnauthorized();
    $this->deleteJson('/api/expenses/1')->assertUnauthorized();
});

test('F026-2: regular members receive 403 on expenses endpoints', function () {
    $expense = Expense::create([
        'category' => 'Utilities',
        'description' => 'Electricity bill',
        'amount' => 1500.00,
        'expense_date' => now()->toDateString(),
        'branch_id' => $this->mainBranch->id,
        'recorded_by' => $this->admin->id,
    ]);

    $this->actingAs($this->member);

    $this->getJson('/api/expenses')->assertForbidden();
    $this->postJson('/api/expenses', [])->assertForbidden();
    $this->putJson("/api/expenses/{$expense->id}", [])->assertForbidden();
    $this->deleteJson("/api/expenses/{$expense->id}")->assertForbidden();
});

test('F026-3: accountant lists expenses with pagination, search, category, and date range filters', function () {
    Expense::create([
        'category' => 'Office Supplies',
        'description' => 'Printing paper reams',
        'amount' => 450.00,
        'expense_date' => '2026-09-01',
        'branch_id' => $this->mainBranch->id,
        'recorded_by' => $this->accountant->id,
    ]);

    Expense::create([
        'category' => 'Utilities',
        'description' => 'Water bill September',
        'amount' => 320.00,
        'expense_date' => '2026-09-10',
        'branch_id' => $this->mainBranch->id,
        'recorded_by' => $this->accountant->id,
    ]);

    $this->actingAs($this->accountant);

    // List all
    $response = $this->getJson('/api/expenses');
    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
            'total_amount',
            'categories',
            'can_manage',
        ]);
    expect($response->json('can_manage'))->toBeTrue();

    // Category filter
    $catRes = $this->getJson('/api/expenses?category=Utilities');
    $catRes->assertOk();
    $categories = collect($catRes->json('data'))->pluck('category')->unique()->all();
    expect($categories)->toEqual(['Utilities']);

    // Search filter
    $searchRes = $this->getJson('/api/expenses?search=reams');
    $searchRes->assertOk();
    $descriptions = collect($searchRes->json('data'))->pluck('description')->all();
    expect($descriptions)->toContain('Printing paper reams')
        ->and($descriptions)->not->toContain('Water bill September');

    // Date range filter
    $dateRes = $this->getJson('/api/expenses?from=2026-09-05&to=2026-09-15');
    $dateRes->assertOk();
    $dates = collect($dateRes->json('data'))->pluck('expense_date')->all();
    expect($dates)->toContain('2026-09-10')
        ->and($dates)->not->toContain('2026-09-01');
});

test('F026-4: accountant records new expense with full validation and audit logging', function () {
    $this->actingAs($this->accountant);

    $payload = [
        'category' => 'Maintenance',
        'description' => 'Air conditioning cleaning service',
        'amount' => 2500.00,
        'expense_date' => now()->toDateString(),
        'branch_id' => $this->mainBranch->id,
    ];

    $response = $this->postJson('/api/expenses', $payload);
    $response->assertStatus(201)
        ->assertJson(['message' => 'Expense recorded.'])
        ->assertJsonPath('data.category', 'Maintenance')
        ->assertJsonPath('data.amount', '2500.00');

    $expenseId = $response->json('data.id');
    $expense = Expense::findOrFail($expenseId);
    expect((float) $expense->amount)->toBe(2500.00)
        ->and($expense->recorded_by)->toBe($this->accountant->id);
});

test('F026-5: expense creation rejects invalid or missing fields with 422', function () {
    $this->actingAs($this->accountant);

    $response = $this->postJson('/api/expenses', [
        'category' => '',
        'amount' => -50,
        'expense_date' => 'invalid-date',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['category', 'amount', 'expense_date']);
});

test('F026-6: accountant updates an existing expense', function () {
    $expense = Expense::create([
        'category' => 'Travel',
        'description' => 'Bus fare to regional summit',
        'amount' => 350.00,
        'expense_date' => now()->toDateString(),
        'branch_id' => $this->mainBranch->id,
        'recorded_by' => $this->accountant->id,
    ]);

    $this->actingAs($this->accountant);

    $response = $this->putJson("/api/expenses/{$expense->id}", [
        'category' => 'Travel & Transportation',
        'description' => 'Bus and taxi fare to regional summit',
        'amount' => 550.00,
        'expense_date' => now()->toDateString(),
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Expense updated.']);

    $expense->refresh();
    expect($expense->category)->toBe('Travel & Transportation')
        ->and((float) $expense->amount)->toBe(550.00);
});

test('F026-7: accountant deletes an existing expense', function () {
    $expense = Expense::create([
        'category' => 'Miscellaneous',
        'description' => 'One-time courier delivery',
        'amount' => 120.00,
        'expense_date' => now()->toDateString(),
        'branch_id' => $this->mainBranch->id,
        'recorded_by' => $this->accountant->id,
    ]);

    $this->actingAs($this->accountant);

    $response = $this->deleteJson("/api/expenses/{$expense->id}");
    $response->assertOk()
        ->assertJson(['message' => 'Expense deleted.']);

    expect(Expense::find($expense->id))->toBeNull();
});

test('F026-8: branch accountant sees expenses from their branch only', function () {
    $this->accountant->update(['branch_id' => $this->officeBranch->id]);

    $expMain = Expense::create([
        'category' => 'Branch Main',
        'description' => 'Main branch repair',
        'amount' => 1000.00,
        'expense_date' => now()->toDateString(),
        'branch_id' => $this->mainBranch->id,
        'recorded_by' => $this->admin->id,
    ]);

    $expOffice = Expense::create([
        'category' => 'Branch Office',
        'description' => 'Office branch repair',
        'amount' => 1200.00,
        'expense_date' => now()->toDateString(),
        'branch_id' => $this->officeBranch->id,
        'recorded_by' => $this->accountant->id,
    ]);

    $response = $this->actingAs($this->accountant)->getJson('/api/expenses');
    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($expOffice->id)
        ->not->toContain($expMain->id);
});
