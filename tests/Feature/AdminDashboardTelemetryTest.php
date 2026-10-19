<?php

use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\QaChairmanSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(QaChairmanSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->chairman = User::where('email', 'leo.baluyot039+chairman@gmail.com')->firstOrFail();
});

test('admin dashboard returns complete system health and telemetry', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'system_status',
            'system_health' => [
                'database' => ['status', 'driver', 'name'],
                'environment' => ['app_env', 'app_debug', 'php_version', 'laravel_version', 'timezone', 'server_time'],
                'maintenance' => ['active', 'public_login', 'member_login', 'officer_login'],
                'services' => ['deposit', 'withdrawal', 'loan_application', 'loan_payment'],
                'integrations' => [
                    'sms' => ['name', 'enabled', 'configured'],
                    'paymongo' => ['name', 'enabled', 'mode', 'configured'],
                    'mail' => ['name', 'mailer', 'from_address'],
                ],
            ],
            'backup_status' => [
                'health',
                'last_run_at',
                'tiers',
            ],
            'security_summary' => [
                'officers_breakdown',
                'recent_logs',
                'pending_privileged_actions',
            ],
            'operations_summary' => [
                'open_tickets',
                'urgent_tickets',
                'recent_tickets',
            ],
        ]);

    expect($response->json('system_status'))->toBeIn(['operational', 'attention', 'critical']);
});

test('chairman dashboard does not include admin telemetry payload or backup status', function () {
    $response = $this->actingAs($this->chairman)->getJson('/api/dashboard');

    $response->assertOk()
        ->assertJsonStructure([
            'greeting',
            'period_label',
            'total_members',
            'month_net',
            'month_deposits',
            'month_withdrawals',
            'month_expenses',
            'loans_active',
            'loans_outstanding',
            'loans_overdue',
            'loans_due_soon',
            'total_savings',
            'total_share_capital',
            'total_member_funds',
            'total_loans_count',
            'total_loans_disbursed',
            'pending_transactions',
            'pending_loans',
            'pending_oversight_approvals',
            'total_pending_approvals',
            'pending_oversight_requests',
            'monthly_series',
            'loan_status_series',
            'pending_transactions_list',
            'recent_expenses',
            'recent_announcements',
        ]);

    expect($response->json('system_health'))->toBeNull();
    expect($response->json('security_summary'))->toBeNull();
    expect($response->json('backup_status'))->toBeNull();
});
