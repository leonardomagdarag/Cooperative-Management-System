<?php

use App\Models\Account;
use App\Models\SiteContent;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CooperativeMetricsService;
use App\Support\SiteContentCatalog;
use Database\Seeders\SpatieRolePermissionSeeder;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    Cache::flush();
    $this->seed(SpatieRolePermissionSeeder::class);
});

function makeMetricsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function enableLiveMetrics(array $overrides = []): void
{
    $values = [
        'stats_visible' => '1',
        'stats_use_cooperative' => '1',
        'founding_year' => '2016',
        ...$overrides,
    ];

    foreach ($values as $key => $value) {
        SiteContent::query()->updateOrCreate(
            ['section' => 'home', 'key' => $key],
            [
                'value' => $value,
                'type' => $key === 'founding_year' ? 'text' : 'boolean',
                'description' => $key,
                'order' => 0,
                'is_active' => true,
            ],
        );
    }

    CooperativeMetricsService::forget();
}

test('public content exposes a null metrics payload while live metrics are disabled', function () {
    getJson('/api/public-content')
        ->assertOk()
        ->assertJsonPath('metrics', null)
        ->assertJsonPath('form.home.stats_use_cooperative', false)
        ->assertJsonPath('form.home.stats_visible', true);
});

test('admin can toggle metrics visibility and live mode through the api', function () {
    $admin = makeMetricsAdmin();

    $form = SiteContentCatalog::editorContent();
    $form['home']['stats_visible'] = false;
    $form['home']['stats_use_cooperative'] = true;

    actingAs($admin)
        ->putJson('/api/public-content', ['form' => $form])
        ->assertOk();

    actingAs($admin)
        ->getJson('/api/public-content')
        ->assertOk()
        ->assertJsonPath('form.home.stats_visible', false)
        ->assertJsonPath('form.home.stats_use_cooperative', true);
});

test('live metrics are computed cooperatively and formatted coarsely', function () {
    $active1 = User::factory()->create(['account_status' => 'active']);
    $active1->assignRole('member');

    $active2 = User::factory()->create(['account_status' => 'active']);
    $active2->assignRole('member');

    $inactive = User::factory()->create(['account_status' => 'inactive']);
    $inactive->assignRole('member');

    Account::factory()->savings()->create(['user_id' => $active1->id, 'balance' => 2_000_000]);
    Account::factory()->shareCapital()->create(['user_id' => $active2->id, 'balance' => 500_000]);

    Transaction::factory()->atCounter()->count(3)->create();

    enableLiveMetrics();

    getJson('/api/public-content')
        ->assertOk()
        ->assertJsonPath('metrics.active_members', '2')
        ->assertJsonPath('metrics.total_assets', '₱2M+')
        ->assertJsonPath('metrics.service_done', '3')
        ->assertJsonPath('metrics.years_service', (string) (now()->year - 2016));
});

test('live metrics fall back to null when there is no data to report', function () {
    enableLiveMetrics();

    getJson('/api/public-content')
        ->assertOk()
        ->assertJsonPath('metrics.active_members', null)
        ->assertJsonPath('metrics.total_assets', null)
        ->assertJsonPath('metrics.service_done', null);
});

test('turnstile and availability payloads are unaffected by metrics', function () {
    enableLiveMetrics();

    $response = getJson('/api/public-content')->assertOk();

    expect($response->json('availability'))->toHaveKeys(['registration', 'login'])
        ->and($response->json('form.home.active_members'))->not->toBeEmpty();
});

test('founding year is validated when saving public content', function () {
    $admin = makeMetricsAdmin();

    $form = SiteContentCatalog::editorContent();

    foreach (['abc', '1800', (string) (now()->year + 1)] as $invalid) {
        $form['home']['founding_year'] = $invalid;

        actingAs($admin)
            ->putJson('/api/public-content', ['form' => $form])
            ->assertStatus(422);
    }

    $form['home']['founding_year'] = '2016';

    actingAs($admin)
        ->putJson('/api/public-content', ['form' => $form])
        ->assertOk();
});

test('founding year validation returns a friendly message instead of the raw key', function () {
    $admin = makeMetricsAdmin();

    $form = SiteContentCatalog::editorContent();
    $form['home']['founding_year'] = (string) (now()->year + 1);

    $response = actingAs($admin)
        ->putJson('/api/public-content', ['form' => $form])
        ->assertStatus(422);

    $errors = $response->json('errors');

    expect($errors['form.home.founding_year'][0] ?? null)
        ->toBe('The founding year must not be greater than '.(int) date('Y').'.');
});

test('public content hides the editor schema from unauthenticated visitors', function () {
    $response = getJson('/api/public-content')->assertOk();

    expect($response->json('can_manage'))->toBeFalse()
        ->and($response->json('sections'))->toBeNull()
        ->and($response->json('form'))->not->toBeNull();
});

test('public content includes the editor schema for administrators', function () {
    $admin = makeMetricsAdmin();

    $response = actingAs($admin)
        ->getJson('/api/public-content')
        ->assertOk();

    expect($response->json('can_manage'))->toBeTrue()
        ->and($response->json('sections'))->toBeArray()
        ->and($response->json('sections'))->toHaveKey('home');
});
