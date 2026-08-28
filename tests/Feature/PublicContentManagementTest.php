<?php

use App\Models\SiteContent;
use App\Models\User;
use App\Support\SiteContentCatalog;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

function makePublicContentEditor(): User
{
    (new SpatieRolePermissionSeeder)->run();
    (new UserSeeder)->run();

    return User::where('email', 'admin@example.com')->firstOrFail();
}

function makeUnauthorizedUser(): User
{
    $user = User::factory()->create();

    return $user;
}

function fullForm(): array
{
    return SiteContentCatalog::editorContent();
}

test('public content api returns sections, form, can_manage and turnstile key', function () {
    $response = getJson('/api/public-content')
        ->assertOk()
        ->assertJsonStructure([
            'sections',
            'form',
            'can_manage',
            'turnstile_site_key',
        ]);

    $data = $response->json();
    expect($data['sections'])->toBeArray()
        ->and($data['sections'])->toHaveKeys([
            'home', 'about', 'board', 'officers', 'member_statements',
            'news', 'events', 'contact', 'registration', 'policies',
        ])
        ->and($data['form'])->toBeArray()
        ->and($data['can_manage'])->toBeFalse();
});

test('admin sees can_manage true when reading public content', function () {
    $admin = makePublicContentEditor();

    actingAs($admin)
        ->getJson('/api/public-content')
        ->assertOk()
        ->assertJsonPath('can_manage', true);
});

test('non-admin users see can_manage false when reading public content', function () {
    $user = makeUnauthorizedUser();

    actingAs($user)
        ->getJson('/api/public-content')
        ->assertOk()
        ->assertJsonPath('can_manage', false);
});

test('unauthenticated public content read is allowed but cannot save', function () {
    putJson('/api/public-content', ['form' => fullForm()])
        ->assertStatus(401);
});

test('admin can save public content through the api', function () {
    $admin = makePublicContentEditor();

    $form = fullForm();
    $form['home']['hero_subtitle'] = 'Updated through React API';

    actingAs($admin)
        ->putJson('/api/public-content', ['form' => $form])
        ->assertOk()
        ->assertJson(['message' => 'Public content updated successfully.']);

    expect(
        SiteContent::query()
            ->where('section', 'home')
            ->where('key', 'hero_subtitle')
            ->value('value')
    )->toBe('Updated through React API');
});

test('admin can save the online member registration public setting', function () {
    $admin = makePublicContentEditor();

    $form = fullForm();
    $form['registration']['online_member_registration_enabled'] = false;

    actingAs($admin)
        ->putJson('/api/public-content', ['form' => $form])
        ->assertOk();

    expect(
        SiteContent::query()
            ->where('section', 'registration')
            ->where('key', 'online_member_registration_enabled')
            ->value('value')
    )->toBe('0');
});

test('admin can save dynamic board cards through the api', function () {
    $admin = makePublicContentEditor();

    $form = fullForm();
    $form['board']['cards'] = [
        [
            'name' => 'Maria Example',
            'position' => 'Chairperson',
            'image_url' => 'images/hero_banner.jpg',
        ],
    ];

    actingAs($admin)
        ->putJson('/api/public-content', ['form' => $form])
        ->assertOk();

    $cards = json_decode(
        SiteContent::query()
            ->where('section', 'board')
            ->where('key', 'cards')
            ->value('value'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($cards)->toHaveCount(1)
        ->and($cards[0]['name'])->toBe('Maria Example')
        ->and($cards[0]['position'])->toBe('Chairperson')
        ->and($cards[0]['image_url'])->toBe('images/hero_banner.jpg');
});

test('board visibility toggle is reflected back through the api', function () {
    $admin = makePublicContentEditor();

    $form = fullForm();
    $form['board']['is_visible'] = false;

    actingAs($admin)
        ->putJson('/api/public-content', ['form' => $form])
        ->assertOk();

    actingAs($admin)
        ->getJson('/api/public-content')
        ->assertOk()
        ->assertJsonPath('form.board.is_visible', false);
});

test('non-admin users cannot save public content', function () {
    $user = makeUnauthorizedUser();

    actingAs($user)
        ->putJson('/api/public-content', ['form' => fullForm()])
        ->assertForbidden();
});

test('public content save validates form fields', function () {
    $admin = makePublicContentEditor();

    $form = fullForm();
    $form['home']['hero_subtitle'] = ['not', 'a', 'string'];

    actingAs($admin)
        ->putJson('/api/public-content', ['form' => $form])
        ->assertStatus(422);
});

test('public content save validates card field lengths', function () {
    $admin = makePublicContentEditor();

    $form = fullForm();
    $form['board']['cards'] = [
        [
            'name' => str_repeat('x', 256),
            'position' => 'Chairperson',
            'image_url' => '',
        ],
    ];

    actingAs($admin)
        ->putJson('/api/public-content', ['form' => $form])
        ->assertStatus(422);
});

test('public pages still expose the seeded default content tree', function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $data = getJson('/api/public-content')->assertOk()->json();

    expect($data['form']['home']['hero_subtitle'])->not->toBeEmpty()
        ->and($data['form']['about']['mission'])->not->toBeEmpty()
        ->and($data['form']['board']['cards'])->toBeArray()
        ->and($data['form']['officers']['cards'])->toBeArray()
        ->and($data['form']['contact']['email'])->not->toBeEmpty();
});
