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

test('guest access to dashboard subpath is redirected to login', function () {
    $this->get('/dashboard/members')
        ->assertRedirect('/login');
});

test('member without permission accessing restricted dashboard subpath is redirected to dashboard', function () {
    $member = User::factory()->create(['role' => 'member']);

    actingAs($member)
        ->get('/dashboard/members')
        ->assertRedirect('/dashboard');
});

test('privileged user accessing permitted dashboard subpath can view react app', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get('/dashboard/members')
        ->assertOk()
        ->assertViewIs('react');
});
