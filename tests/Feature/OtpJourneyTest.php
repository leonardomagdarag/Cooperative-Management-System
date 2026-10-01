<?php

use App\Mail\EmailVerificationMail;
use App\Mail\OtpCodeMail;
use App\Jobs\SendSmsJob;
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

test('admin approval of a public registration sends the email verification link', function () {
    Mail::fake();

    $this->seed(SpatieRolePermissionSeeder::class);

    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    $member = User::factory()->unverified()->create([
        'email' => 'pending-approve@example.com',
        'account_status' => 'inactive',
    ]);

    $this->actingAs($admin)
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

test('OTP SMS sends inline and queues only a failed delivery for retry', function () {
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

test('OTP SMS queues a retry after immediate delivery fails', function () {
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

    app(SecurityOtpService::class)->sendOtpSms($user, 'new_device_login', '123456');

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job) => $job->phone === '09761729700');
});
