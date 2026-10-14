<?php

use App\Models\Branch;
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

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->accountant = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $this->member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();

    $this->mainBranch = Branch::where('code', 'BR000001')->firstOrFail();
    $this->officeBranch = Branch::where('code', 'BR000002')->firstOrFail();
});

test('F027-1: unauthenticated requests to all report endpoints return 401', function () {
    $this->getJson('/api/reports/financial')->assertUnauthorized();
    $this->getJson('/api/reports/expenses')->assertUnauthorized();
    $this->getJson('/api/reports/member-activity')->assertUnauthorized();
    $this->getJson('/api/reports/member-savings')->assertUnauthorized();
    $this->getJson('/api/reports/loans')->assertUnauthorized();
    $this->getJson('/api/reports/payments')->assertUnauthorized();
    $this->getJson('/api/reports/options')->assertUnauthorized();
    $this->getJson('/api/reports/balance-sheet')->assertUnauthorized();
    $this->getJson('/api/reports/balance-sheet/download')->assertUnauthorized();
});

test('F027-2: regular member receives 403 on all report endpoints', function () {
    $this->actingAs($this->member);

    $this->getJson('/api/reports/financial')->assertForbidden();
    $this->getJson('/api/reports/expenses')->assertForbidden();
    $this->getJson('/api/reports/member-activity')->assertForbidden();
    $this->getJson('/api/reports/member-savings')->assertForbidden();
    $this->getJson('/api/reports/loans')->assertForbidden();
    $this->getJson('/api/reports/payments')->assertForbidden();
    $this->getJson('/api/reports/options')->assertForbidden();
    $this->getJson('/api/reports/balance-sheet')->assertForbidden();
    $this->get('/api/reports/balance-sheet/download')->assertForbidden();
});

test('F027-3: accountant can retrieve balance sheet summary JSON', function () {
    $response = $this->actingAs($this->accountant)->getJson('/api/reports/balance-sheet');

    $response->assertOk()
        ->assertJsonStructure([
            'start',
            'end',
            'as_of',
            'opening_balance' => ['cash'],
            'period_movement' => ['inflows', 'outflows', 'expenses', 'net_cash_flow'],
            'assets' => [['label', 'amount']],
            'liabilities' => [['label', 'amount']],
            'equity' => [['label', 'amount']],
            'totals' => ['assets', 'liabilities', 'equity', 'liabilities_and_equity'],
        ]);
});

test('F027-4: accountant can download balance sheet PDF', function () {
    $response = $this->actingAs($this->accountant)->get('/api/reports/balance-sheet/download');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
});

test('F027-5: balance sheet mathematical balance holds (Assets = Liabilities + Equity)', function () {
    $response = $this->actingAs($this->accountant)->getJson('/api/reports/balance-sheet');
    $response->assertOk();

    $totals = $response->json('totals');
    $assets = (float) $totals['assets'];
    $liabilities = (float) $totals['liabilities'];
    $equity = (float) $totals['equity'];
    $liabAndEquity = (float) $totals['liabilities_and_equity'];

    expect(round($liabilities + $equity, 2))->toEqual(round($liabAndEquity, 2))
        ->and(round($assets, 2))->toEqual(round($liabAndEquity, 2));
});

test('F027-6: reports options endpoint returns members, branches, and expense categories', function () {
    $response = $this->actingAs($this->accountant)->getJson('/api/reports/options');

    $response->assertOk()
        ->assertJsonStructure([
            'members',
            'expense_categories',
        ]);
});

test('F027-7: reports honor branch scoping for branch-bound officers', function () {
    $this->accountant->update(['branch_id' => $this->officeBranch->id]);

    $response = $this->actingAs($this->accountant)->getJson('/api/reports/financial');
    $response->assertOk();
    expect($response->json('start'))->not->toBeNull();
});
