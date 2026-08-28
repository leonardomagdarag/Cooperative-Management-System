<?php

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