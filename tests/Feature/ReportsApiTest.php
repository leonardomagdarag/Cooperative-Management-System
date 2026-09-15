<?php

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

test('report endpoints return paginated report data for admins', function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $admin = User::where('email', 'admin@example.com')->firstOrFail();

    $this->actingAs($admin)->getJson('/api/reports/options')
        ->assertOk()
        ->assertJsonStructure([
            'members' => ['*' => ['id', 'name']],
            'expense_categories',
        ]);

    $this->actingAs($admin)->getJson('/api/reports/financial?from=2024-01-01&to=2024-01-31')
        ->assertOk()
        ->assertJsonStructure([
            'start', 'end',
            'total_deposits', 'total_withdrawals', 'total_loan_releases', 'total_loan_repayments',
            'total_interest_earned', 'total_fees', 'total_expenses', 'net',
        ]);

    foreach (['/api/reports/member-savings', '/api/reports/loans', '/api/reports/payments', '/api/reports/expenses', '/api/reports/member-activity'] as $endpoint) {
        $this->actingAs($admin)->getJson($endpoint)
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }
});

test('report endpoints require the reports.view permission', function () {
    $this->seed(SpatieRolePermissionSeeder::class);

    $member = User::factory()->create([
        'email' => 'report-member@example.com',
        'password' => 'password',
        'role' => 'member',
    ]);
    $member->assignRole('member');

    $this->actingAs($member)
        ->getJson('/api/reports/financial')
        ->assertForbidden();
});

test('financial report aggregates interest cash receipts and excludes rejected or cancelled loans', function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $admin = User::where('email', 'admin@example.com')->firstOrFail();
    $member = User::factory()->create();
    $member->assignRole('member');

    // Rejected loan in range: should contribute 0 to interest
    Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 50000,
        'interest_rate' => 5.0,
        'term_months' => 12,
        'loan_status' => 'rejected',
        'applied_at' => '2024-05-01',
    ]);

    // Pending loan in range: should contribute 0 to interest
    Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 40000,
        'interest_rate' => 5.0,
        'term_months' => 12,
        'loan_status' => 'pending',
        'applied_at' => '2024-05-02',
    ]);

    // Active loan: 10,000 at 2% monthly for 10 months -> total interest = 2,000, total payable = 12,000. Interest ratio = 2000/12000 = 1/6
    $activeLoan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 10000,
        'interest_rate' => 2.0,
        'term_months' => 10,
        'loan_status' => 'active',
        'applied_at' => '2024-05-03',
        'approved_at' => '2024-05-03',
    ]);

    $account = Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
    ]);

    // Completed payment in range: 1,200. Interest part = 1200 * (2000 / 12000) = 200.00
    Transaction::factory()->create([
        'account_id' => $account->id,
        'loan_id' => $activeLoan->id,
        'transaction_type' => 'loan_payment',
        'amount' => 1200,
        'status' => 'completed',
        'transaction_date' => '2024-05-15',
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/reports/financial?from=2024-05-01&to=2024-05-31')
        ->assertOk();

    // total_interest_earned should be exactly 200.00 (from the 1200 repayment), NOT inflated by rejected/pending loans
    expect((float) $response->json('total_interest_earned'))->toBe(200.0)
        ->and((float) $response->json('projected_interest'))->toBe(2000.0);
});
