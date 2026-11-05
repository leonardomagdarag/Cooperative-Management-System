<?php

use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertStatus(200);
    $response->assertViewIs('react');
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('unverified user cannot access protected api endpoints', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->getJson('/api/dashboard')
        ->assertStatus(403)
        ->assertJsonPath('message', 'Your email address is not verified.');
});

test('unverified user cannot access protected web dashboard', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/verify-email');
});

test('unverified user can still fetch their own user record', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email_verified', false);
});

test('verified user reports email_verified true through the api', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email_verified', true);
});

test('unverified user can resend the verification email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->postJson('/api/auth/resend-verification')
        ->assertOk()
        ->assertJsonPath('message', 'A verification email has been sent to your email address.');

    expect($user->notifications()->where('type', 'email_verification')->exists())->toBeTrue();
});

test('verified user is told their email is already verified', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/auth/resend-verification')
        ->assertOk()
        ->assertJsonPath('message', 'Your email is already verified.');

    expect(Notification::where('type', 'email_verification')->count())->toBe(0);
});
