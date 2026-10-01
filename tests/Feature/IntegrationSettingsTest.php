<?php

use App\Mail\EmailConfigurationTestMail;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\TextBeeService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->admin->assignRole('admin');
});

test('TextBee key is seeded from the environment without exposing it in settings responses', function () {
    config(['services.textbee.api_key' => 'test-textbee-secret']);

    $this->seed(SystemSettingSeeder::class);

    expect(SystemSetting::get('textbee_api_key'))->toBe('test-textbee-secret')
        ->and(SystemSetting::get('sms_service_enabled'))->toBeFalse();

    $this->actingAs($this->admin)
        ->getJson('/api/settings/sms')
        ->assertOk()
        ->assertJsonPath('data.textbee_api_key_configured', true)
        ->assertJsonPath('data.sms_service_enabled', false)
        ->assertJsonMissingPath('data.textbee_api_key');
});

test('SMS setting can be toggled and the settings test sends through TextBee', function () {
    config(['services.textbee.api_key' => 'test-textbee-secret']);
    Cache::forget('system_setting.sms_service_enabled');
    Cache::forget('system_setting.textbee_api_key');
    SystemSetting::set('sms_service_enabled', true, 'boolean', 'integrations');

    Http::fake([
        'https://api.textbee.dev/api/v1/gateway/send-sms' => Http::response(['success' => true], 200),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/settings/sms')
        ->assertOk()
        ->assertJsonPath('data.sms_service_enabled', true)
        ->assertJsonPath('data.textbee_api_key_configured', true);

    $this->actingAs($this->admin)
        ->postJson('/api/settings/sms/test', ['phone_number' => '09761729700'])
        ->assertOk()
        ->assertJsonPath('success', true);

    Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'test-textbee-secret'));

    $this->actingAs($this->admin)
        ->putJson('/api/settings/sms', ['sms_service_enabled' => false])
        ->assertOk();

    expect(app(TextBeeService::class)->isEnabled())->toBeFalse();
});

test('settings email tester sends immediately to the selected address', function () {
    Mail::fake();

    $this->actingAs($this->admin)
        ->postJson('/api/settings/email/test', ['email' => 'delivery-test@example.com'])
        ->assertOk()
        ->assertJsonPath('success', true);

    Mail::assertSent(EmailConfigurationTestMail::class, fn (EmailConfigurationTestMail $mail) => $mail->hasTo('delivery-test@example.com'));
});

test('members cannot use the email settings tester', function () {
    $member = User::factory()->create(['role' => 'member']);
    $member->assignRole('member');

    $this->actingAs($member)
        ->postJson('/api/settings/email/test', ['email' => 'delivery-test@example.com'])
        ->assertForbidden();
});
