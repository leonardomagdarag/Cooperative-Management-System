<?php

use App\Models\Branch;
use App\Models\Expense;
use App\Models\PrivilegedActionRequest;
use App\Models\User;
use App\Services\CountersignService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->accountant = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $this->member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();

    $this->chairman = User::factory()->create(['role' => 'chairman', 'is_officer' => true]);
    $this->chairman->assignRole('chairman');

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

test('F026-6: accountant edit of an existing expense is applied only after chairman countersign', function () {
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

    $response->assertStatus(202)
        ->assertJsonPath('status', 'pending')
        ->assertJsonStructure(['request_id', 'message']);

    // The edit is staged as a countersign request, not applied directly.
    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));
    expect($request->action_type)->toBe('expense.update')
        ->and($request->status)->toBe('pending')
        ->and($request->payload['changes']['amount'])->toEqual(550.00);

    $expense->refresh();
    expect($expense->category)->toBe('Travel')
        ->and((float) $expense->amount)->toBe(350.00);

    app(CountersignService::class)->approve($request, $this->chairman);

    $expense->refresh();
    expect($expense->category)->toBe('Travel & Transportation')
        ->and((float) $expense->amount)->toBe(550.00);
});

test('F026-6b: rejected expense edit leaves the expense unchanged', function () {
    $expense = Expense::create([
        'category' => 'Utilities',
        'amount' => 1200.00,
        'expense_date' => now()->toDateString(),
        'branch_id' => $this->mainBranch->id,
        'recorded_by' => $this->accountant->id,
    ]);

    $this->actingAs($this->accountant);

    $response = $this->putJson("/api/expenses/{$expense->id}", [
        'category' => 'Utilities',
        'amount' => 9000.00,
        'expense_date' => now()->toDateString(),
    ]);
    $response->assertStatus(202);

    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));
    app(CountersignService::class)->reject($request, $this->chairman, 'Amount not supported by receipts.');

    $expense->refresh();
    expect((float) $expense->amount)->toBe(1200.00)
        ->and($request->fresh()->status)->toBe('rejected');
});

test('F026-6c: expense edit cannot be countersigned by its own requester', function () {
    $expense = Expense::create([
        'category' => 'Supplies',
        'amount' => 250.00,
        'expense_date' => now()->toDateString(),
        'branch_id' => $this->mainBranch->id,
        'recorded_by' => $this->chairman->id,
    ]);

    $this->actingAs($this->chairman);

    $response = $this->putJson("/api/expenses/{$expense->id}", [
        'category' => 'Supplies',
        'amount' => 400.00,
        'expense_date' => now()->toDateString(),
    ]);
    $response->assertStatus(202);

    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));

    expect(fn () => app(CountersignService::class)->approve($request, $this->chairman))
        ->toThrow(HttpException::class, 'Self-approval is forbidden.');

    expect((float) $expense->fresh()->amount)->toBe(250.00);
});

test('F026-7: accountant delete of an existing expense is applied only after chairman countersign', function () {
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
    $response->assertStatus(202)
        ->assertJsonPath('status', 'pending')
        ->assertJsonStructure(['request_id', 'message']);

    // The deletion is staged as a countersign request, not deleted directly.
    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));
    expect($request->action_type)->toBe('expense.delete')
        ->and($request->status)->toBe('pending')
        ->and($request->payload['expense_id'])->toEqual($expense->id);

    expect(Expense::find($expense->id))->not->toBeNull();

    app(CountersignService::class)->approve($request, $this->chairman);

    expect(Expense::find($expense->id))->toBeNull();
});

test('F026-7b: rejected expense deletion leaves the expense intact', function () {
    $expense = Expense::create([
        'category' => 'Utilities',
        'amount' => 800.00,
        'expense_date' => now()->toDateString(),
        'branch_id' => $this->mainBranch->id,
        'recorded_by' => $this->accountant->id,
    ]);

    $this->actingAs($this->accountant);

    $response = $this->deleteJson("/api/expenses/{$expense->id}");
    $response->assertStatus(202);

    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));
    app(CountersignService::class)->reject($request, $this->chairman, 'Keep for audit records.');

    expect(Expense::find($expense->id))->not->toBeNull()
        ->and($request->fresh()->status)->toBe('rejected');
});

test('F026-7c: expense deletion cannot be countersigned by its own requester', function () {
    $expense = Expense::create([
        'category' => 'Supplies',
        'amount' => 150.00,
        'expense_date' => now()->toDateString(),
        'branch_id' => $this->mainBranch->id,
        'recorded_by' => $this->chairman->id,
    ]);

    $this->actingAs($this->chairman);

    $response = $this->deleteJson("/api/expenses/{$expense->id}");
    $response->assertStatus(202);

    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));

    expect(fn () => app(CountersignService::class)->approve($request, $this->chairman))
        ->toThrow(HttpException::class, 'Self-approval is forbidden.');

    expect(Expense::find($expense->id))->not->toBeNull();
});

test('F026-7d: duplicate pending countersign request on the same expense is rejected with 409', function () {
    $expense = Expense::create([
        'category' => 'Utilities',
        'amount' => 500.00,
        'expense_date' => now()->toDateString(),
        'branch_id' => $this->mainBranch->id,
        'recorded_by' => $this->accountant->id,
    ]);

    $this->actingAs($this->accountant);

    // Initial update request
    $first = $this->putJson("/api/expenses/{$expense->id}", [
        'category' => 'Utilities',
        'amount' => 600.00,
        'expense_date' => now()->toDateString(),
    ]);
    $first->assertStatus(202);

    // Attempting a second update while first is pending returns 409
    $secondUpdate = $this->putJson("/api/expenses/{$expense->id}", [
        'category' => 'Utilities',
        'amount' => 700.00,
        'expense_date' => now()->toDateString(),
    ]);
    $secondUpdate->assertStatus(409);

    // Attempting a delete while update is pending returns 409
    $deleteAttempt = $this->deleteJson("/api/expenses/{$expense->id}");
    $deleteAttempt->assertStatus(409);
});

test('F026-7e: expense listing returns pending_request metadata when countersign is active', function () {
    $expense = Expense::create([
        'category' => 'Legal',
        'amount' => 3000.00,
        'expense_date' => now()->toDateString(),
        'branch_id' => $this->mainBranch->id,
        'recorded_by' => $this->accountant->id,
    ]);

    $this->actingAs($this->accountant);

    $resBefore = $this->getJson('/api/expenses');
    $itemBefore = collect($resBefore->json('data'))->firstWhere('id', $expense->id);
    expect($itemBefore['pending_request'])->toBeNull();

    $this->deleteJson("/api/expenses/{$expense->id}")->assertStatus(202);

    $resAfter = $this->getJson('/api/expenses');
    $itemAfter = collect($resAfter->json('data'))->firstWhere('id', $expense->id);
    expect($itemAfter['pending_request'])->not->toBeNull()
        ->and($itemAfter['pending_request']['action_type'])->toBe('expense.delete');
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
