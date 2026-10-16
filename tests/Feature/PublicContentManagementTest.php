<?php

use App\Models\PrivilegedActionRequest;
use App\Models\SiteContent;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CountersignService;
use App\Support\SiteContentCatalog;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

function makePublicContentEditor(): User
{
    (new SpatieRolePermissionSeeder)->run();
    (new UserSeeder)->run();

    return User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
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

test('public content api returns form, can_manage and turnstile key but hides the editor schema from guests', function () {
    $response = getJson('/api/public-content')
        ->assertOk()
        ->assertJsonStructure([
            'form',
            'availability',
            'can_manage',
            'turnstile_site_key',
        ]);

    $data = $response->json();

    expect($data)->not->toHaveKey('sections')
        ->and($data['form'])->toBeArray()
        ->and($data['availability']['registration'])->toEqual(['global' => true, 'member' => true])
        ->and($data['availability']['login'])->toEqual(['global' => true, 'member' => true, 'officer' => true])
        ->and($data['can_manage'])->toBeFalse();
});

test('admin sees can_manage true and the editor schema when reading public content', function () {
    $admin = makePublicContentEditor();

    $response = actingAs($admin)
        ->getJson('/api/public-content')
        ->assertOk()
        ->assertJsonPath('can_manage', true);

    expect($response->json('sections'))->toBeArray()
        ->and($response->json('sections'))->toHaveKeys([
            'home', 'about', 'board', 'officers', 'member_statements',
            'news', 'events', 'contact', 'policies',
        ]);
});

test('non-admin users see can_manage false when reading public content', function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $user = makeUnauthorizedUser();
    $user->givePermissionTo('settings.update');

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

    $response = actingAs($admin)
        ->putJson('/api/settings/service-availability', [
            'services' => [
                'registration' => ['global' => true, 'member' => false],
                'login' => ['global' => true, 'member' => true, 'officer' => true],
                'deposit' => ['global' => true, 'member' => true, 'officer' => true, 'counter' => true, 'online' => true],
                'loan_application' => ['global' => true, 'member' => true, 'officer' => true],
                'loan_payment' => ['global' => true, 'member' => true, 'officer' => true, 'counter' => true, 'online' => true],
            ],
            'notify_affected' => false,
        ]);

    $response->assertStatus(202);
    $requestId = $response->json('request_id');
    $request = PrivilegedActionRequest::findOrFail($requestId);

    $chairman = User::factory()->create(['email' => 'chairman3@example.com']);
    $chairman->assignRole('chairman');
    $chairman->givePermissionTo('oversight.approve');
    app(CountersignService::class)->approve($request, $chairman);

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
    $this->seed(SpatieRolePermissionSeeder::class);
    $user = makeUnauthorizedUser();
    $user->givePermissionTo('settings.update');

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

test('admin saving public content strips HTML tags from plain text fields', function () {
    $admin = makePublicContentEditor();

    $form = fullForm();
    $form['about']['about1'] = 'Welcome <script>alert(1)</script><b>Settlers</b>';

    actingAs($admin)
        ->putJson('/api/public-content', ['form' => $form])
        ->assertOk();

    $stored = SiteContent::query()
        ->where('section', 'about')
        ->where('key', 'about1')
        ->value('value');

    expect($stored)->toBe('Welcome alert(1)Settlers');
});
