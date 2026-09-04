<?php

use App\Jobs\SendPasswordResetEmailJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('reset password link can be requested', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Queue::assertPushed(SendPasswordResetEmailJob::class, fn ($job) => $job->userId === $user->id);
});

test('reset password screen can be rendered', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Queue::assertPushed(SendPasswordResetEmailJob::class, function ($job) {
        $response = $this->get('/reset-password/'.$job->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Queue::assertPushed(SendPasswordResetEmailJob::class, function ($job) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $job->token,
            'email' => $user->email,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});
