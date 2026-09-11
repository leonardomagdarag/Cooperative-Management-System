<?php

use App\Models\SiteContent;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\SiteContentCatalog;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Spatie\Permission\Models\Role;

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
            'availability',
            'can_manage',
            'turnstile_site_key',
        ]);

    $data = $response->json();
    expect($data['sections'])->toBeArray()
        ->and($data['sections'])->toHaveKeys([
            'home', 'about', 'board', 'officers', 'member_statements',
            'news', 'events', 'contact', 'policies',
        ])
        ->and($data['form'])->toBeArray()
        ->and($data['availability']['registration'])->toEqual(['global' => true, 'member' => true])
        ->and($data['availability']['login'])->toEqual(['global' => true, 'member' => true, 'officer' => true])
        ->and($data['can_manage'])->toBeFalse();
});

test('admin sees can_manage true when reading public content', function () {
    $admin = makePublicContentEditor();

    actingAs($admin)
        ->getJson('/api/public-content')
        ->assertOk()
        ->assertJsonPath('can_manage', true);
});

test('admin user payload includes the public content management permission', function () {
    $admin = makePublicContentEditor();
    Role::findByName('admin', 'web')->revokePermissionTo('settings.update');

    actingAs($admin)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('primary_role', 'admin')
        ->assertJsonFragment(['settings.update']);
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

test('admin can save the online member registration service availability setting', function () {
    $admin = makePublicContentEditor();
    $this->seed(SystemSettingSeeder::class);

    actingAs($admin)
        ->getJson('/api/settings/service-availability')
        ->assertOk()
        ->assertJsonPath('data.registration.global', true)
        ->assertJsonPath('data.registration.member', true)
        ->assertJsonPath('data.login.member', true)
        ->assertJsonPath('data.login.officer', true);

    actingAs($admin)
        ->putJson('/api/settings/service-availability', [
            'services' => [
                'registration' => ['global' => true, 'member' => false],
                'login' => ['global' => true, 'member' => true, 'officer' => true],
                'deposit' => ['global' => true, 'member' => true, 'officer' => true, 'counter' => true, 'online' => true],
                'withdrawal' => ['global' => true, 'member' => true, 'officer' => true, 'counter' => true, 'online' => true, 'bank_transfer' => true],
                'loan_application' => ['global' => true, 'member' => true, 'officer' => true],
                'loan_payment' => ['global' => true, 'member' => true, 'officer' => true, 'counter' => true, 'online' => true],
            ],
            'notify_affected' => false,
        ])
        ->assertOk();

    expect(
        (bool) SystemSetting::get('service_registration_member_enabled', true)
    )->toBeFalse();
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

test('admin saving public content sanitizes active html fields', function () {
    $admin = makePublicContentEditor();

    $form = fullForm();
    $form['home']['hero_subtitle'] = '<p>Welcome</p><script>alert("xss")</script>';

    actingAs($admin)
        ->putJson('/api/public-content', ['form' => $form])
        ->assertOk();

    $stored = SiteContent::query()
        ->where('section', 'home')
        ->where('key', 'hero_subtitle')
        ->value('value');

    expect($stored)->not->toContain('<script>')
        ->and($stored)->toContain('<p>Welcome</p>');
});

test('admin saving public content strips event handler attributes', function () {
    $admin = makePublicContentEditor();

    $form = fullForm();
    $form['home']['hero_subtitle'] = '<a href="https://example.com" onclick="alert(1)">Link</a>';

    actingAs($admin)
        ->putJson('/api/public-content', ['form' => $form])
        ->assertOk();

    $stored = SiteContent::query()
        ->where('section', 'home')
        ->where('key', 'hero_subtitle')
        ->value('value');

    expect($stored)->not->toContain('onclick')
        ->and($stored)->toContain('href="https://example.com"')
        ->and($stored)->toContain('Link');
});
