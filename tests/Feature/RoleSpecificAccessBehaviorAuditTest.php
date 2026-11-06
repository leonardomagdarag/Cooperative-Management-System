<?php

use App\Models\Notification;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->accountant = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $this->member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();

    // Create loan officer
    $this->loanOfficer = User::factory()->create([
        'email' => 'loan.officer@example.com',
        'account_status' => 'active',
        'is_officer' => true,
        'role' => 'loan',
    ]);
    $this->loanOfficer->syncRoles(['loan']);
});

test('seeding preserves transactions.approve-own for manager but removes from other built-in roles', function () {
    $permission = Permission::findByName('transactions.approve-own', 'web');
    $roles = Role::query()->whereIn('name', ['admin', 'chairman', 'manager', 'accountant', 'loan', 'developer', 'member'])->get();

    foreach ($roles as $role) {
        $role->givePermissionTo($permission);
    }

    $this->seed(SpatieRolePermissionSeeder::class);

    $manager = Role::findByName('manager', 'web');
    expect($manager->hasPermissionTo($permission))->toBeTrue();

    $otherRoles = Role::query()->whereIn('name', ['admin', 'chairman', 'accountant', 'loan', 'developer', 'member'])->get();
    foreach ($otherRoles as $role) {
        expect($role->fresh()->hasPermissionTo($permission))->toBeFalse();
    }
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

test('notifications are newest first and include active high and normal priority counts', function () {
    $this->member->notifications()->delete();
    $olderHigh = Notification::create([
        'user_id' => $this->member->id,
        'type' => 'security_alert',
        'title' => 'Older security alert',
        'message' => 'Older alert',
        'priority' => 'urgent',
    ]);
    Notification::whereKey($olderHigh->id)->update(['created_at' => now()->subDay()]);
    $latestNormal = Notification::create([
        'user_id' => $this->member->id,
        'type' => 'general',
        'title' => 'Latest update',
        'message' => 'Latest alert',
        'priority' => 'normal',
    ]);
    Notification::whereKey($latestNormal->id)->update(['created_at' => now()->addMinute()]);
    Notification::create([
        'user_id' => $this->member->id,
        'type' => 'general',
        'title' => 'Low priority update',
        'message' => 'Low alert',
        'priority' => 'low',
    ]);
    Notification::create([
        'user_id' => $this->member->id,
        'type' => 'security_alert',
        'title' => 'Expired alert',
        'message' => 'Expired',
        'priority' => 'high',
        'expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($this->member)
        ->getJson('/api/notifications?page=1')
        ->assertOk()
        ->assertJsonPath('notifications.0.id', $latestNormal->id)
        ->assertJsonPath('notifications.2.id', $olderHigh->id)
        ->assertJsonPath('priority_counts.high', 1)
        ->assertJsonPath('priority_counts.normal', 2);
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
    $this->getJson('/api/logs')->assertOk(); // Manager can view logs
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

    // Loan officer has no global transaction visibility — ledger/expenses
    // require transactions.view, which is intentionally not granted.
    $this->getJson('/api/ledger')->assertStatus(403);
    $this->getJson('/api/expenses')->assertStatus(403);

    // Loan officer visibility is scoped to their own field-collection runs.
    $this->getJson('/api/collections')->assertOk();
    $this->getJson('/api/collections/route-list')->assertOk();

    // Loan officer is forbidden from roles, backups, logs
    $this->getJson('/api/roles')->assertStatus(403);
    $this->getJson('/api/backups')->assertStatus(403);
    $this->getJson('/api/logs')->assertStatus(403);
});
