<?php

use App\Livewire\Content\PublicContent;
use App\Models\SiteContent;
use App\Models\User;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

function makePublicContentAdmin(): User
{
    Role::findOrCreate('admin', 'web');

    $user = User::factory()->create([
        'role' => 'admin',
        'is_officer' => true,
    ]);
    $user->assignRole('admin');

    return $user;
}

test('public pages render content from the site contents table', function () {
    SiteContent::query()->create([
        'section' => 'home',
        'key' => 'hero_subtitle',
        'value' => 'Database driven hero subtitle',
        'type' => 'text',
        'description' => 'Hero subtitle',
        'order' => 1,
        'is_active' => true,
    ]);

    get(route('home_page'))
        ->assertOk()
        ->assertSee('Database driven hero subtitle', false);
});

test('footer and registration page render policy content from site contents', function () {
    SiteContent::query()->create([
        'section' => 'policies',
        'key' => 'terms_and_conditions',
        'value' => '<h3>Custom Terms</h3><p>Members must review the custom agreement.</p>',
        'type' => 'html',
        'description' => 'Terms and Conditions',
        'order' => 1,
        'is_active' => true,
    ]);

    SiteContent::query()->create([
        'section' => 'policies',
        'key' => 'privacy_policy',
        'value' => '<h3>Custom Privacy</h3><p>Members must review the custom privacy policy.</p>',
        'type' => 'html',
        'description' => 'Privacy Policy',
        'order' => 2,
        'is_active' => true,
    ]);

    get(route('home_page'))
        ->assertOk()
        ->assertSee('Terms and Agreement')
        ->assertSee('Custom Terms', false)
        ->assertSee('Custom Privacy', false);

    get(route('register_page'))
        ->assertOk()
        ->assertSee('Terms and Agreement')
        ->assertSee('Privacy Policy')
        ->assertSee('Custom Terms', false)
        ->assertSee('Custom Privacy', false);
});

test('admin can save public content from the dedicated content page', function () {
    actingAs(makePublicContentAdmin());

    Livewire::test(PublicContent::class)
        ->set('form.home.hero_subtitle', 'Updated from Livewire')
        ->call('save')
        ->assertHasNoErrors();

    expect(
        SiteContent::query()
            ->where('section', 'home')
            ->where('key', 'hero_subtitle')
            ->value('value')
    )->toBe('Updated from Livewire');
});

test('admin can save the online member registration public setting', function () {
    actingAs(makePublicContentAdmin());

    Livewire::test(PublicContent::class)
        ->set('activeSection', 'registration')
        ->set('form.registration.online_member_registration_enabled', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(
        SiteContent::query()
            ->where('section', 'registration')
            ->where('key', 'online_member_registration_enabled')
            ->value('value')
    )->toBe('0');
});

test('about page respects the board visibility toggle and renders dynamic board cards', function () {
    SiteContent::query()->create([
        'section' => 'board',
        'key' => 'is_visible',
        'value' => '0',
        'type' => 'boolean',
        'description' => 'Board section visibility',
        'order' => 1,
        'is_active' => true,
    ]);

    SiteContent::query()->create([
        'section' => 'board',
        'key' => 'cards',
        'value' => json_encode([
            [
                'name' => 'Maria Example',
                'position' => 'Chairperson',
                'image_url' => 'images/hero_banner.jpg',
            ],
        ], JSON_THROW_ON_ERROR),
        'type' => 'json',
        'description' => 'Board member cards',
        'order' => 2,
        'is_active' => true,
    ]);

    get(route('about_page'))
        ->assertOk()
        ->assertDontSee('Board of Directors')
        ->assertDontSee('Maria Example');

    SiteContent::query()
        ->where('section', 'board')
        ->where('key', 'is_visible')
        ->firstOrFail()
        ->update(['value' => '1']);

    get(route('about_page'))
        ->assertOk()
        ->assertSee('Board of Directors')
        ->assertSee('Maria Example')
        ->assertSee('Chairperson');
});

test('admin can add remove and save dynamic board cards', function () {
    actingAs(makePublicContentAdmin());

    Livewire::test(PublicContent::class)
        ->call('addCard', 'board')
        ->call('addCard', 'board')
        ->set('form.board.cards.0.name', 'Maria Example')
        ->set('form.board.cards.0.position', 'Chairperson')
        ->set('form.board.cards.0.image_url', 'images/hero_banner.jpg')
        ->call('removeCard', 'board', 1)
        ->call('save')
        ->assertHasNoErrors();

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

test('site content seeder preserves legacy board members when backfilling dynamic cards', function () {
    SiteContent::query()->create([
        'section' => 'board',
        'key' => 'chairperson.name',
        'value' => 'Legacy Chair',
        'type' => 'text',
        'description' => 'Legacy chairperson name',
        'order' => 1,
        'is_active' => true,
    ]);

    SiteContent::query()->create([
        'section' => 'board',
        'key' => 'chairperson.title',
        'value' => 'Legacy Position',
        'type' => 'text',
        'description' => 'Legacy chairperson title',
        'order' => 2,
        'is_active' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => SiteContentSeeder::class,
        '--no-interaction' => true,
    ]);

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
        ->and($cards[0]['name'])->toBe('Legacy Chair')
        ->and($cards[0]['position'])->toBe('Legacy Position')
        ->and(
            SiteContent::query()
                ->where('section', 'board')
                ->where('key', 'is_visible')
                ->value('value')
        )->toBe('1');
});

test('public registration affordances and routes are disabled when online member registration is off', function () {
    SiteContent::query()->create([
        'section' => 'registration',
        'key' => 'online_member_registration_enabled',
        'value' => '0',
        'type' => 'boolean',
        'description' => 'Online member registration availability',
        'order' => 1,
        'is_active' => true,
    ]);

    get(route('home_page'))
        ->assertOk()
        ->assertSee('href="#Contact"', false)
        ->assertDontSee(route('register_page'), false);

    get(route('login_page'))
        ->assertOk()
        ->assertDontSee("Don't have an account?")
        ->assertDontSee('Create an Account');

    get(route('register_page'))
        ->assertRedirect(route('home_page'));

    post(route('register'))
        ->assertRedirect(route('home_page'));
});
