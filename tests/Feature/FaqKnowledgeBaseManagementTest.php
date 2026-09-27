<?php

use App\Models\SiteContent;
use App\Models\User;
use App\Support\HelpContent;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->secretary = User::where('email', 'secretary@example.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@example.com')->firstOrFail();
});

function faqUpdatePayload(): array
{
    $defaults = HelpContent::defaults();

    return [
        'content' => $defaults['content'],
        'categories' => $defaults['categories'],
    ];
}

test('F031-6: unauthenticated requests to update the knowledge base return 401', function () {
    $this->putJson('/api/faqs', faqUpdatePayload())->assertUnauthorized();
});

test('F031-7: regular member without settings.update receives 403 when updating the knowledge base', function () {
    $this->actingAs($this->member);

    $this->putJson('/api/faqs', faqUpdatePayload())->assertForbidden();
});

test('F031-8: secretary with settings.view but not settings.update receives 403', function () {
    $this->actingAs($this->secretary);

    $this->putJson('/api/faqs', faqUpdatePayload())->assertForbidden();
});

test('F031-9: admin can update the knowledge base and changes are reflected on read', function () {
    $this->actingAs($this->admin);

    $payload = faqUpdatePayload();
    $payload['content']['header_description'] = 'Updated helpdesk description.';
    $payload['content']['callout_title'] = 'Need more help?';
    $payload['categories'][0]['label'] = 'Getting Started';
    $payload['categories'][0]['faqs'][0]['q'] = 'How do I create an account?';
    $payload['categories'][0]['faqs'][0]['a'] = 'Register through the public portal.';
    $payload['categories'][0]['faqs'][0]['tags'] = ['register', 'account'];

    $this->putJson('/api/faqs', $payload)
        ->assertOk()
        ->assertJson(['message' => 'Help & Support content updated successfully.']);

    $response = $this->getJson('/api/faqs')->assertOk()->json();

    expect($response['content']['header_description'])->toBe('Updated helpdesk description.')
        ->and($response['content']['callout_title'])->toBe('Need more help?')
        ->and($response['data'][0]['label'])->toBe('Getting Started')
        ->and($response['data'][0]['faqs'][0]['q'])->toBe('How do I create an account?')
        ->and($response['data'][0]['faqs'][0]['tags'])->toBe(['register', 'account']);
});

test('F031-10: admin update writes the knowledge base to site_contents', function () {
    $this->actingAs($this->admin);

    $this->putJson('/api/faqs', faqUpdatePayload())->assertOk();

    $stored = SiteContent::query()
        ->where('section', 'help')
        ->where('key', 'knowledge_base')
        ->value('value');

    $categories = json_decode((string) $stored, true);
    expect($categories)->toBeArray()->not->toBeEmpty();
});

test('F031-11: admin update records an audit log entry', function () {
    $this->actingAs($this->admin);

    $this->putJson('/api/faqs', faqUpdatePayload())->assertOk();

    expect(Activity::where('event', 'knowledge_base.updated')->exists())->toBeTrue();
});

test('F031-12: updating the knowledge base validates required question text', function () {
    $this->actingAs($this->admin);

    $payload = faqUpdatePayload();
    $payload['categories'][0]['faqs'][0]['q'] = '';

    $this->putJson('/api/faqs', $payload)->assertStatus(422);
});

test('F031-13: updating the knowledge base validates category ids', function () {
    $this->actingAs($this->admin);

    $payload = faqUpdatePayload();
    $payload['categories'][0]['id'] = 'INVALID ID!';

    $this->putJson('/api/faqs', $payload)->assertStatus(422);
});

test('F031-14: admin update strips html from answer and page copy', function () {
    $this->actingAs($this->admin);

    $payload = faqUpdatePayload();
    $payload['content']['header_description'] = '<p>Welcome</p><script>alert("xss")</script>';
    $payload['categories'][0]['faqs'][0]['a'] = 'Use <script>alert(1)</script><b>Settings</b> to update your profile.';

    $this->putJson('/api/faqs', $payload)->assertOk();

    $response = $this->getJson('/api/faqs')->assertOk()->json();

    expect($response['content']['header_description'])->toBe('Welcomealert("xss")')
        ->and($response['data'][0]['faqs'][0]['a'])->toBe('Use alert(1)Settings to update your profile.');
});
