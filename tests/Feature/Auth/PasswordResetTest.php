<?php

use App\Models\User;
use App\Models\LoginHandoff;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]));

        $response->assertRedirect('/reset-password?token='.$notification->token.'&email='.urlencode($user->email));

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->forceFill(['current_session_id' => 'old-active-session'])->save();
    $handoff = LoginHandoff::query()->create([
        'user_id' => $user->id,
        'current_session_id' => 'old-active-session',
        'pending_session_id' => 'waiting-session',
        'device_label' => 'Browser on Windows',
        'status' => 'pending',
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->postJson('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertOk();
        expect($user->fresh()->current_session_id)->toBeNull()
            ->and($handoff->fresh()->status)->toBe('expired');

        return true;
    });
});
