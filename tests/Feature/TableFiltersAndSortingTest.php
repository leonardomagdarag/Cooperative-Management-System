<?php

use App\Models\Expense;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->viewer = User::factory()->create(['role' => 'chairman']);
    $this->viewer->assignRole('chairman');
});

test('officer role priority sorts the full result before pagination and uses assigned roles', function () {
    $officer = User::factory()->create(['role' => 'accountant', 'is_officer' => true]);
    $officer->assignRole('manager');
    $response = $this->actingAs($this->viewer)->getJson('/api/members?is_officer=1&sort=role&role_first=manager&direction=asc&per_page=5');
    $response->assertOk();
    $roles = collect($response->json('data'))->pluck('primary_role');
    expect($roles->first())->toBe('manager');
    expect($response->json('sort_roles'))->toContain('manager');
    expect(collect($response->json('data'))->pluck('id'))->toContain($officer->id);

    $this->getJson('/api/members?is_officer=1&sort=role&role_first=accountant&direction=asc&per_page=5')
        ->assertOk()->assertJsonPath('data.0.primary_role', 'accountant');
});

test('member name sorting preserves user ids and respects branch filters', function () {
    $response = $this->actingAs($this->viewer)->getJson('/api/members?sort=name&direction=asc&per_page=50');
    $response->assertOk();
    $rows = collect($response->json('data'));
    foreach ($rows as $row) {
        expect(User::findOrFail($row['id'])->user_number)->toBe($row['user_number']);
    }
    $branch = User::whereNotNull('branch_id')->firstOrFail()->branch_id;
    $this->getJson('/api/members?sort=branch&direction=desc&branch_id='.$branch)
        ->assertOk()->assertJsonPath('data.0.branch_id', $branch);
});

test('all time reports include expenses outside the default month and custom dates still narrow results', function () {
    $this->travelTo(now()->startOfMonth()->addDays(10));
    Expense::create(['category' => 'Utilities', 'description' => 'Older table filter record', 'amount' => 123, 'expense_date' => now()->subYears(2), 'recorded_by' => $this->viewer->id]);
    $this->actingAs($this->viewer)->getJson('/api/reports/expenses?all_time=1')
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('total', 123);
    $this->getJson('/api/reports/expenses?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString())
        ->assertOk()->assertJsonPath('meta.total', 0);
});
