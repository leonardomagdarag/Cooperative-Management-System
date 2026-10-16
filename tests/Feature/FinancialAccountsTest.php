<?php

use App\Models\Account;
use App\Models\InterestDisbursement;
use App\Models\PrivilegedActionRequest;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CountersignService;
use App\Services\InterestService;
use App\Support\FinancialSettings;
use Database\Seeders\AccountSeeder;
use Database\Seeders\FinancialSettingSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(AccountSeeder::class);
    $this->seed(FinancialSettingSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();

    $this->chairman = User::factory()->create(['role' => 'chairman', 'is_officer' => true]);
    $this->chairman->assignRole('chairman');

    $this->savings = Account::where('user_id', $this->member->id)
        ->where('account_type', 'savings')
        ->firstOrFail();
});

function financialSettingsPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'savings' => ['rate' => 12, 'interval' => 'monthly', 'min_balance' => 0],
        'share_capital' => ['rate' => 6, 'interval' => 'yearly', 'min_balance' => 0],
        'interest' => ['min_payout' => 0, 'notify_members' => false],
        'withdrawal' => [
            'min_amount' => 100,
            'max_amount' => 0,
            'savings_enabled' => true,
            'share_capital_enabled' => true,
            'enabled' => true,
            'member' => true,
            'officer' => true,
            'counter' => true,
            'online' => true,
            'bank_transfer' => true,
        ],
    ], $overrides);
}

test('financial settings are seeded with safe defaults', function () {
    expect(FinancialSettings::rate('savings'))->toBe(0.0)
        ->and(FinancialSettings::interval('savings'))->toBe('monthly')
        ->and(FinancialSettings::withdrawalMin())->toBe(100.0)
        ->and(FinancialSettings::savingsWithdrawalEnabled())->toBeTrue();
});

test('admin can read financial settings', function () {
    actingAs($this->admin)
        ->getJson('/api/settings/financial')
        ->assertOk()
        ->assertJsonPath('data.savings.interval', 'monthly')
        ->assertJsonPath('data.withdrawal.min_amount', 100)
        ->assertJsonStructure([
            'data' => [
                'savings' => ['rate', 'interval', 'min_balance'],
                'share_capital' => ['rate', 'interval', 'min_balance'],
                'interest' => ['min_payout', 'notify_members'],
                'withdrawal',
                'last_credited' => ['savings', 'share_capital'],
            ],
        ]);
});

test('financial settings are not readable without the permission', function () {
    actingAs($this->member)->getJson('/api/settings/financial')->assertForbidden();
});

test('financial settings update is countersigned and only applies on approval', function () {
    $response = actingAs($this->admin)
        ->putJson('/api/settings/financial', financialSettingsPayload())
        ->assertAccepted()
        ->assertJsonPath('status', 'pending');

    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));
    expect($request->action_type)->toBe('financial.settings.update')
        ->and(FinancialSettings::rate('savings'))->toBe(0.0);

    app(CountersignService::class)->approve($request, $this->chairman);

    expect(FinancialSettings::rate('savings'))->toBe(12.0)
        ->and(FinancialSettings::interval('share-capital'))->toBe('yearly')
        ->and(FinancialSettings::withdrawalMin())->toBe(100.0);
});

test('financial settings update rejects a minimum above the maximum', function () {
    actingAs($this->admin)
        ->putJson('/api/settings/financial', financialSettingsPayload([
            'withdrawal' => ['min_amount' => 5000, 'max_amount' => 1000],
        ]))
        ->assertStatus(422);
});

test('interest run credits eligible members and posts interest transactions', function () {
    SystemSetting::set('financial_savings_interest_rate', 12);
    SystemSetting::set('financial_savings_interest_interval', 'monthly');
    $this->savings->update(['balance' => 12000]);

    $batch = app(InterestService::class)->disburse('savings', $this->admin, 'manual');

    expect($batch->status)->toBe('completed')
        ->and($batch->member_count)->toBeGreaterThanOrEqual(1)
        ->and((float) $batch->total_amount)->toBeGreaterThan(0);

    $item = $batch->items()->where('user_id', $this->member->id)->first();

    // 12000 * 12% / 12 = 120.00
    expect($item)->not->toBeNull()
        ->and((float) $item->amount)->toBe(120.0)
        ->and((float) $item->balance_after)->toBe(12120.0);

    expect(Transaction::where('id', $item->transaction_id)
        ->where('transaction_type', 'interest')
        ->where('status', 'completed')
        ->exists())->toBeTrue();

    expect((float) $this->savings->fresh()->balance)->toBe(12120.0);
});

test('interest eager-loads only columns that exist on the users and personal_data tables', function () {
    // Regression guard: SQLite silently substitutes the literal string
    // `"first_name"` when a quoted identifier is not a real column, so a bad
    // eager-load column would pass this SQLite suite and only fail on MySQL.
    $userColumns = Schema::getColumnListing('users');
    $personalDataColumns = Schema::getColumnListing('personal_data');

    foreach (explode(',', InterestService::USER_EAGER_COLUMNS) as $column) {
        expect($userColumns)->toContain($column);
    }

    $user = Account::query()
        ->where('account_type', 'savings')
        ->with([
            'user:'.InterestService::USER_EAGER_COLUMNS,
            'user.personalData:id,first_name,last_name',
        ])
        ->first()
        ?->user;

    expect($user)->not->toBeNull();

    foreach (array_keys($user->getAttributes()) as $column) {
        expect($userColumns)->toContain($column);
    }

    foreach (array_keys($user->personalData?->getAttributes() ?? []) as $column) {
        expect($personalDataColumns)->toContain($column);
    }
});

test('interest run is idempotent for the same period', function () {
    SystemSetting::set('financial_savings_interest_rate', 12);
    SystemSetting::set('financial_savings_interest_interval', 'monthly');

    $first = app(InterestService::class)->disburse('savings');
    $second = app(InterestService::class)->disburse('savings');

    expect($second->id)->toBe($first->id)
        ->and(InterestDisbursement::where('account_type', 'savings')->count())->toBe(1);
});

test('interest below the minimum payout is recorded as skipped', function () {
    SystemSetting::set('financial_savings_interest_rate', 12);
    SystemSetting::set('financial_savings_interest_interval', 'monthly');
    SystemSetting::set('financial_interest_min_payout', 1000000000);

    $batch = app(InterestService::class)->disburse('savings');

    expect($batch->member_count)->toBe(0)
        ->and($batch->skipped_count)->toBeGreaterThanOrEqual(1)
        ->and($batch->items()->where('skipped', true)->exists())->toBeTrue();
});

test('withdrawals are blocked when savings withdrawals are disabled', function () {
    SystemSetting::set('financial_withdrawal_savings_enabled', false);

    actingAs($this->member)
        ->postJson('/api/services/withdrawal', ['amount' => 100, 'transaction_mode' => 'counter'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Withdrawals to savings accounts are currently disabled.');
});

test('withdrawal below the configured minimum is rejected', function () {
    SystemSetting::set('financial_withdrawal_min_amount', 500);
    $this->savings->update(['balance' => 10000]);

    actingAs($this->member)
        ->postJson('/api/services/withdrawal', ['amount' => 100, 'transaction_mode' => 'counter'])
        ->assertStatus(422);
});

test('withdrawal above the configured maximum is rejected', function () {
    SystemSetting::set('financial_withdrawal_max_amount', 200);
    $this->savings->update(['balance' => 10000]);

    actingAs($this->member)
        ->postJson('/api/services/withdrawal', ['amount' => 1000, 'transaction_mode' => 'counter'])
        ->assertStatus(422);
});

test('service availability exposes the withdrawal limits', function () {
    SystemSetting::set('financial_withdrawal_min_amount', 250);
    SystemSetting::set('financial_withdrawal_max_amount', 7500);

    actingAs($this->admin)
        ->getJson('/api/services/availability')
        ->assertOk()
        ->assertJsonPath('data.withdrawal.min_amount', 250)
        ->assertJsonPath('data.withdrawal.max_amount', 7500)
        ->assertJsonPath('data.withdrawal.savings_enabled', true);
});

test('interest run requires countersign and credits on approval', function () {
    SystemSetting::set('financial_savings_interest_rate', 12);
    SystemSetting::set('financial_savings_interest_interval', 'monthly');
    $this->savings->update(['balance' => 12000]);

    $response = actingAs($this->admin)
        ->postJson('/api/settings/financial/interest/run', ['account_type' => 'savings'])
        ->assertAccepted()
        ->assertJsonPath('status', 'pending');

    $request = PrivilegedActionRequest::findOrFail($response->json('request_id'));
    expect($request->action_type)->toBe('financial.interest.run')
        ->and(InterestDisbursement::where('account_type', 'savings')->exists())->toBeFalse();

    app(CountersignService::class)->approve($request, $this->chairman);

    // QUEUE_CONNECTION=sync in tests, so the dispatched job runs inline.
    $batch = InterestDisbursement::where('account_type', 'savings')->first();
    expect($batch)->not->toBeNull()
        ->and($batch->trigger)->toBe('manual')
        ->and($batch->items()->where('user_id', $this->member->id)->where('skipped', false)->exists())->toBeTrue();
});

test('manual interest run is rejected when no rate is configured', function () {
    actingAs($this->admin)
        ->postJson('/api/settings/financial/interest/run', ['account_type' => 'savings'])
        ->assertStatus(422);
});

test('interest report requires the interest.view permission', function () {
    actingAs($this->member)->getJson('/api/reports/interest-disbursements')->assertForbidden();

    SystemSetting::set('financial_savings_interest_rate', 12);
    app(InterestService::class)->disburse('savings');
    $batch = InterestDisbursement::where('account_type', 'savings')->firstOrFail();

    actingAs($this->admin)
        ->getJson('/api/reports/interest-disbursements')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);

    actingAs($this->admin)
        ->getJson("/api/reports/interest-disbursements/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('batch.id', $batch->id)
        ->assertJsonStructure(['batch', 'data', 'meta']);
});

test('interest disbursements can be exported as a report', function () {
    SystemSetting::set('financial_savings_interest_rate', 12);
    app(InterestService::class)->disburse('savings');

    actingAs($this->admin)
        ->postJson('/api/reports/export', [
            'report_type' => 'interest_disbursements',
            'format' => 'excel',
        ])
        ->assertOk();

    actingAs($this->member)
        ->postJson('/api/reports/export', [
            'report_type' => 'interest_disbursements',
            'format' => 'excel',
        ])
        ->assertForbidden();
});
