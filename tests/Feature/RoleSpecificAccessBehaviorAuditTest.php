<?php

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
    $this->secretary = User::where('email', 'secretary@example.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@example.com')->firstOrFail();

    // Create loan officer
    $this->loanOfficer = User::factory()->create([
        'email' => 'loan.officer@example.com',
        'account_status' => 'active',
        'is_officer' => true,
        'role' => 'loan',
    ]);
    $this->loanOfficer->syncRoles(['loan']);
});

test('guest access behavior', function () {
    // Public endpoints are accessible
    $this->getJson('/api/public-content')->assertOk();
    $this->getJson('/api/announcements/public')->assertOk();

    // Protected endpoints return 401 Unauthorized
    $this->getJson('/api/user')->assertStatus(401);
    $this->getJson('/api/dashboard')->assertStatus(401);
    $this->getJson('/api/members')->assertStatus(401);
    $this->getJson('/api/ledger')->assertStatus(401);
    $this->getJson('/api/expenses')->assertStatus(401);
    $this->getJson('/api/roles')->assertStatus(401);
    $this->getJson('/api/backups')->assertStatus(401);
});

test('member role access boundaries', function () {
    $this->actingAs($this->member);

    // Allowed self-service endpoints
    $this->getJson('/api/user')->assertOk();
    $this->getJson('/api/profile')->assertOk();
    $this->getJson('/api/dashboard')->assertOk();
    $this->getJson('/api/history')->assertOk();
    $this->getJson('/api/member-loans')->assertOk();
    $this->getJson('/api/notifications')->assertOk();

    // Forbidden officer and admin endpoints
    $this->getJson('/api/members')->assertStatus(403);
    $this->getJson('/api/ledger')->assertStatus(403);
    $this->getJson('/api/expenses')->assertStatus(403);
    $this->getJson('/api/roles')->assertStatus(403);
    $this->getJson('/api/backups')->assertStatus(403);
    $this->getJson('/api/logs')->assertStatus(403);
    $this->postJson('/api/branches', ['name' => 'Unauthorized Branch'])->assertStatus(403);
});

test('admin role access capabilities', function () {
    $this->actingAs($this->admin);

    // Admin has full operational and administrative access
    $this->getJson('/api/members')->assertOk();
    $this->getJson('/api/roles')->assertOk();
    $this->getJson('/api/backups')->assertOk();
    $this->getJson('/api/ledger')->assertOk();
    $this->getJson('/api/expenses')->assertOk();
    $this->getJson('/api/logs')->assertOk();
    $this->getJson('/api/reports/financial')->assertOk();
});

test('manager role access boundaries', function () {
    $this->actingAs($this->manager);

    // Manager has operational and member management permissions
    $this->getJson('/api/members')->assertOk();
    $this->getJson('/api/services/pending')->assertOk();
    $this->getJson('/api/reports/financial')->assertOk();

    // Manager is forbidden from system administration: roles, backups, logs
    $this->getJson('/api/roles')->assertStatus(403);
    $this->getJson('/api/backups')->assertStatus(403);
    $this->getJson('/api/logs')->assertStatus(403);
});

test('accountant role access boundaries', function () {
    $this->actingAs($this->accountant);

    // Accountant has financial management permissions
    $this->getJson('/api/ledger')->assertOk();
    $this->getJson('/api/expenses')->assertOk();
    $this->getJson('/api/reports/financial')->assertOk();
    $this->getJson('/api/reports/expenses')->assertOk();

    // Accountant is forbidden from administrative management
    $this->getJson('/api/roles')->assertStatus(403);
    $this->getJson('/api/backups')->assertStatus(403);
    $this->getJson('/api/logs')->assertStatus(403);
});

test('loan officer role access boundaries', function () {
    $this->actingAs($this->loanOfficer);

    // Loan officer has loan operations permissions
    $this->getJson('/api/loans')->assertOk();
    $this->getJson('/api/loans/loan-products')->assertOk();
    $this->getJson('/api/loans/search-members')->assertOk();

    // Loan officer is forbidden from accounting, system administration and logs
    $this->getJson('/api/ledger')->assertStatus(403);
    $this->getJson('/api/expenses')->assertStatus(403);
    $this->getJson('/api/roles')->assertStatus(403);
    $this->getJson('/api/backups')->assertStatus(403);
    $this->getJson('/api/logs')->assertStatus(403);
});

test('secretary role access boundaries', function () {
    $this->actingAs($this->secretary);

    // Secretary has records, announcements and member management access
    $this->getJson('/api/members')->assertOk();
    $this->getJson('/api/announcements')->assertOk();

    // Secretary preset does not include branches.view by default, so /api/branches is 403
    $this->getJson('/api/branches')->assertStatus(403);

    // When granted branches.view override, access is permitted
    $this->secretary->givePermissionTo('branches.view');
    $this->getJson('/api/branches')->assertOk();

    // Secretary has transactions.view, so reading expenses is allowed
    $this->getJson('/api/expenses')->assertOk();

    // But Secretary lacks transactions.update, so recording expenses is forbidden
    $this->postJson('/api/expenses', [
        'category' => 'Office Supplies',
        'amount' => 100,
        'expense_date' => now()->toDateString(),
    ])->assertStatus(403);

    // Secretary is forbidden from roles, backups, and logs
    $this->getJson('/api/roles')->assertStatus(403);
    $this->getJson('/api/backups')->assertStatus(403);
    $this->getJson('/api/logs')->assertStatus(403);
});
