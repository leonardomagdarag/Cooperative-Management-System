<?php

use App\Livewire\Dashboard;
use App\Models\User;
use Livewire\Livewire;
use function Pest\Laravel\actingAs;

test('dashboard loading overlay also targets notification inbox section switches', function () {
    $user = User::factory()->create(['role' => 'member']);

    actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSeeHtml('wire:target="updateSection, openNotificationsInbox"');
});