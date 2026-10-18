<?php

use App\Mail\EmailVerificationMail;
use App\Mail\OtpCodeMail;
use App\Mail\SecurityAlert;
use App\Models\User;
use App\Services\SecurityOtpService;
use App\Services\TextBeeService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function otpJourneyCode(): string
{
    $code = '000000';
    Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use (&$code) {
        $code = $mail->otpCode;

        return true;
    });

    return $code;
}

test('new device member login completes OTP exactly once and is not redirected again', function () {
    Mail::fake();

    $this->seed(SpatieRolePermissionSeeder::class);

    $user = User::factory()->create([
        'email' => 'journey@example.com',
        'password' => 'password',
        'role' => 'member',
    ]);
    $user->assignRole('member');

    // 1. SPA login triggers OTP for the untrusted device.
    $this->get('/sanctum/csrf-cookie')->assertNoContent();
    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('requires_otp', true);

    expect(session('security.pending_otp'))->toBeTrue();

    // 2. Protected API stays gated while OTP is pending.
    $this->getJson('/api/user')->assertStatus(423);

    // 3. OTP challenge page renders.
    $this->get('/security/verify-otp')->assertOk()->assertViewIs('public.verify-otp');

    // 4. Submitting the emailed code completes verification and clears the flag.
    $this->post('/security/verify-otp', ['otp' => otpJourneyCode()])->assertRedirect();
    expect(session('security.pending_otp'))->toBeNull();
    Mail::assertSent(SecurityAlert::class, fn (SecurityAlert $mail) => $mail->alertType === 'new_login'
        && $mail->data['otp_status'] === 'verified'
        && $mail->data['is_new_device'] === true
    );

    // 5. Protected API, dashboard, and homepage reloads are no longer redirected.
    $this->getJson('/api/user')->assertOk()->assertJsonPath('email', $user->email);
    $this->get('/dashboard')->assertOk()->assertViewIs('react');
    $this->get('/')->assertOk()->assertViewIs('react');
});

test('unverified member after OTP lands on the SPA verify page, not a plain-text dead end', function () {
    Mail::fake();

    $this->seed(SpatieRolePermissionSeeder::class);

    $user = User::factory()->unverified()->create([
        'email' => 'journey-unverified@example.com',
        'password' => 'password',
        'role' => 'member',
    ]);
    $user->assignRole('member');

    $this->get('/sanctum/csrf-cookie')->assertNoContent();
    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('requires_otp', true);

    $this->post('/security/verify-otp', ['otp' => otpJourneyCode()])->assertRedirect();
    expect(session('security.pending_otp'))->toBeNull();

    // Until the email is verified the dashboard bounces to the verify notice,
    // which must serve the SPA so the member can resend or sign out.
    $this->get('/dashboard')->assertRedirect('/verify-email');
    $this->get('/verify-email')->assertOk()->assertViewIs('react');
});

test('OTP code is only written to the application log in local or log mailer environments', function () {
    Log::spy();
    Mail::fake();

    $this->seed(SpatieRolePermissionSeeder::class);

    $user = User::factory()->create([
        'email' => 'otp-log@example.com',
        'password' => 'password',
        'role' => 'member',
    ]);
    $user->assignRole('member');

    // 1. Non-local environment with non-log mailer (array mailer in testing) must NOT log the OTP
    config(['mail.default' => 'array']);

    $this->get('/sanctum/csrf-cookie')->assertNoContent();
    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('requires_otp', true);

    Log::shouldNotHaveReceived('info', fn (string $message) => str_contains($message, 'OTP email') && str_contains($message, 'otp-log@example.com'));

    // 2. Explicit log mailer configuration DOES log the OTP as fallback
    config(['mail.default' => 'log']);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('requires_otp', true);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => str_contains($message, 'OTP email') && str_contains($message, 'otp-log@example.com'));
});

test('OTP challenge page offers a sign-out escape route that clears the pending flag', function () {
    Mail::fake();

    $this->seed(SpatieRolePermissionSeeder::class);

    $user = User::factory()->create([
        'email' => 'otp-escape@example.com',
        'password' => 'password',
        'role' => 'member',
    ]);
    $user->assignRole('member');

    $this->get('/sanctum/csrf-cookie')->assertNoContent();
    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('requires_otp', true);

    $this->get('/security/verify-otp')
        ->assertOk()
        ->assertViewIs('public.verify-otp')
        ->assertSee('Sign out')
        ->assertSee('/logout');

    $this->post('/logout')->assertRedirect('/');

    expect(session('security.pending_otp'))->toBeNull();

    $this->getJson('/api/user')->assertStatus(401);
});

test('manager approval of a public registration sends the email verification link', function () {
    Mail::fake();

    $this->seed(SpatieRolePermissionSeeder::class);

    $manager = User::factory()->create(['role' => 'manager']);
    $manager->assignRole('manager');

    $member = User::factory()->unverified()->create([
        'email' => 'pending-approve@example.com',
        'account_status' => 'inactive',
    ]);

    $this->actingAs($manager)
        ->postJson("/api/members/{$member->id}/approve")
        ->assertOk();

    Mail::assertSent(EmailVerificationMail::class, function (EmailVerificationMail $mail) {
        return ($mail->to[0]['address'] ?? null) === 'pending-approve@example.com';
    });
});

test('active database OTP challenge gates user even if session pending flag was cleared', function () {
    Mail::fake();

    $this->seed(SpatieRolePermissionSeeder::class);

    $user = User::factory()->create([
        'email' => 'tamper-session@example.com',
        'password' => 'password',
        'role' => 'member',
    ]);
    $user->assignRole('member');

    $securityOtp = app(SecurityOtpService::class);
    $securityOtp->issueChallenge($user, $securityOtp->newDevicePurpose());

    $this->actingAs($user)
        ->withSession(['security.pending_otp' => false])
        ->get('/dashboard')
        ->assertRedirect(route('otp.challenge.show'));
});

test('OTP SMS sends successful deliveries inline without queueing them', function () {
    $user = User::factory()->create();
    $user->personalData->contact->forceFill([
        'phone_no' => '09761729700',
        'phone_verified_at' => now(),
    ])->save();

    $sms = mock(TextBeeService::class);
    $sms->shouldReceive('isEnabled')->once()->andReturn(true);
    $sms->shouldReceive('send')
        ->once()
        ->with('09761729700', Mockery::type('string'))
        ->andReturn(true);
    app()->instance(TextBeeService::class, $sms);
    Queue::fake();

    app(SecurityOtpService::class)->sendOtpSms($user, 'new_device_login', '123456');

    Queue::assertNothingPushed();
});

test('OTP SMS falls back to email after immediate delivery failure without queueing a stale code', function () {
    $user = User::factory()->create();
    $user->personalData->contact->forceFill([
        'phone_no' => '09761729700',
        'phone_verified_at' => now(),
    ])->save();

    $sms = mock(TextBeeService::class);
    $sms->shouldReceive('isEnabled')->once()->andReturn(true);
    $sms->shouldReceive('send')
        ->once()
        ->with('09761729700', Mockery::type('string'))
        ->andReturn(false);
    app()->instance(TextBeeService::class, $sms);
    Queue::fake();

    expect(fn () => app(SecurityOtpService::class)->sendOtpSms($user, 'new_device_login', '123456'))
        ->not->toThrow(Throwable::class);

    Queue::assertNothingPushed();
});

test('OTP SMS falls back to email when the SMS service is disabled', function () {
    $user = User::factory()->create();
    $user->personalData->contact->forceFill([
        'phone_no' => '09761729700',
        'phone_verified_at' => now(),
    ])->save();

    $sms = mock(TextBeeService::class);
    $sms->shouldReceive('isEnabled')->once()->andReturn(false);
    $sms->shouldNotReceive('send');
    app()->instance(TextBeeService::class, $sms);

    expect(app(SecurityOtpService::class)->sendOtpSms($user, 'new_device_login', '654321'))->toBeFalse();
});

test('verification SMS to an unverified phone number sends inline', function () {
    $user = User::factory()->create();
    $sms = mock(TextBeeService::class);
    $sms->shouldReceive('isEnabled')->once()->andReturn(true);
    $sms->shouldReceive('send')
        ->once()
        ->with('09171234567', 'Settlers CC: Your phone verification OTP is 123456. Valid for 10 mins. Never share this.')
        ->andReturn(true);
    app()->instance(TextBeeService::class, $sms);
    Queue::fake();

    app(SecurityOtpService::class)->sendOtpSmsToPhone(
        $user,
        '09171234567',
        'phone_verification',
        '123456'
    );

    Queue::assertNothingPushed();
});

test('failed phone ownership SMS does not fall back to the account email', function () {
    $user = User::factory()->create();
    $sms = mock(TextBeeService::class);
    $sms->shouldReceive('isEnabled')->once()->andReturn(true);
    $sms->shouldReceive('send')->once()->andReturn(false);
    app()->instance(TextBeeService::class, $sms);
    Mail::fake();

    expect(app(SecurityOtpService::class)->sendOtpSmsToPhone(
        $user,
        '09171234567',
        'phone_verification',
        '123456'
    ))->toBeFalse();

    Mail::assertNothingOutgoing();
});

test('resend via SMS route falls back to email when SMS service is disabled', function () {
    $user = User::factory()->create();
    $user->personalData->contact->forceFill([
        'phone_no' => '09761729700',
        'phone_verified_at' => now(),
    ])->save();

    $sms = mock(TextBeeService::class);
    $sms->shouldReceive('isEnabled')->andReturn(false);
    $sms->shouldNotReceive('send');
    app()->instance(TextBeeService::class, $sms);

    $this->actingAs($user)
        ->withSession(['security.pending_otp' => true])
        ->post(route('otp.challenge.resend_sms'))
        ->assertRedirect()
        ->assertSessionHas('success', 'SMS is unavailable. A new verification code was sent to your email.');
});

test('resend via SMS route rejects when user has no verified phone number', function () {
    $user = User::factory()->create();
    $user->personalData->contact->forceFill([
        'phone_no' => null,
        'phone_verified_at' => null,
    ])->save();

    $this->actingAs($user)
        ->withSession(['security.pending_otp' => true])
        ->post(route('otp.challenge.resend_sms'))
        ->assertSessionHasErrors(['otp' => 'You do not have a verified mobile number registered.']);
});

test('phone verification returns 503 when SMS service is disabled', function () {
    $user = User::factory()->create();
    $user->assignRole('member');

    $sms = mock(TextBeeService::class);
    $sms->shouldReceive('isEnabled')->andReturn(false);
    app()->instance(TextBeeService::class, $sms);

    $this->actingAs($user)
        ->postJson('/api/profile/phone/send-otp', ['phone_no' => '09171234567'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'SMS service is currently unavailable. Phone number cannot be verified at this time.');
});

test('officer phone verification returns 503 when SMS service is disabled', function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->givePermissionTo('officers.create');

    $sms = mock(TextBeeService::class);
    $sms->shouldReceive('isEnabled')->andReturn(false);
    app()->instance(TextBeeService::class, $sms);

    $this->actingAs($admin)
        ->postJson('/api/officers/verify-phone/send', ['phone_no' => '09171234567'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'SMS service is currently unavailable. Please verify the email address instead.');
});
