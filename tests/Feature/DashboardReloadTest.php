<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

test('authenticated dashboard reload serves the react application', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertViewIs('react');
});

test('guest dashboard access is redirected to login', function () {
    $this->get('/dashboard')
        ->assertRedirect('/login');
});
