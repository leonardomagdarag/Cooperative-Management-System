<?php

use App\Models\Account;
use App\Models\Loan;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
});

test('admin search returns permission-scoped groups', function () {
    $member = User::factory()->create([
        'user_number' => 'USR777777',
        'email' => 'search.admin.member@example.com',
    ]);
    $member->assignRole('member');

    actingAs($this->admin);

    $response = $this->getJson('/api/search?q=USR777777');
    $response->assertOk()
        ->assertJsonStructure(['groups'])
        ->assertJsonPath('groups.0.label', 'Members')
        ->assertJsonPath('groups.0.items.0.subtitle', 'USR777777');
});

test('admin search excludes sections the role cannot access', function () {
    actingAs($this->admin);

    // 'terminated' is a reserved role and should never appear for a page-less query
    $this->getJson('/api/search?q=terminated')
        ->assertOk();

    $response = $this->getJson('/api/search?q=zzz-unknown-thing-999');
    $response->assertOk();
    $this->assertSame([], $response->json('groups'));
});

test('short queries return empty groups', function () {
    actingAs($this->admin);

    $this->getJson('/api/search?q=a')
        ->assertOk()
        ->assertJsonPath('groups', []);
});

test('regular member only sees pages and their own data', function () {
    $member = User::factory()->create([
        'user_number' => 'USR888888',
        'email' => 'search.member@example.com',
    ]);
    $member->assignRole('member');

    $account = Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
    ]);

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_status' => 'active',
        'loan_amount' => 5000,
    ]);

    actingAs($member);

    $this->getJson('/api/search?q=member')
        ->assertOk();

    $response = $this->getJson('/api/search?q='.$loan->loan_number);
    $response->assertOk();

    $groups = collect($response->json('groups'))->pluck('label')->all();

    expect($groups)->toContain('Loans');

    $loanGroup = collect($response->json('groups'))->firstWhere('label', 'Loans');
    expect($loanGroup['items'][0]['url'])->toBe('/dashboard/member-loans');

    // A member must never receive the members directory or full ledger.
    $groups = collect($this->getJson('/api/search?q=USR888888')->json('groups'))->pluck('label')->all();
    expect($groups)->not->toContain('Members')
        ->and($groups)->not->toContain('Ledger');
});

test('search respects the role permission for the members section', function () {
    $member = User::factory()->create([
        'user_number' => 'USR999999',
        'email' => 'search.loan-officer@example.com',
    ]);
    $member->assignRole('member');
    $member->assignRole('member');

    Role::findOrCreate('loan', 'web');

    $loanOfficer = User::factory()->create([
        'user_number' => 'OFF999999',
        'email' => 'search.officer@example.com',
    ]);
    $loanOfficer->assignRole('loan');

    actingAs($member);

    $groups = collect($this->getJson('/api/search?q=USR999999')->json('groups'))->pluck('label')->all();
    expect($groups)->not->toContain('Members');
});
