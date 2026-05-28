<?php

use App\Livewire\Content\Notifications;
use App\Models\Notification;
use App\Models\User;
use Livewire\Livewire;
use function Pest\Laravel\actingAs;

test('notifications modal opens, marks the notification as read, and renders a dark backdrop without blur', function () {
    $user = User::factory()->create();

    $notification = Notification::query()->create([
        'user_id' => $user->id,
        'type' => 'general',
        'title' => 'Security reminder',
        'message' => 'Review your recent account activity.',
        'priority' => 'high',
        'is_read' => false,
    ]);

    actingAs($user);

    $component = Livewire::test(Notifications::class)
        ->call('openNotification', $notification->id)
        ->assertSet('showModal', true)
        ->assertSet('selectedNotificationId', $notification->id);

    $notification->refresh();

    expect($notification->is_read)->toBeTrue()
        ->and($notification->read_at)->not->toBeNull()
        ->and($component->html())->toContain('bg-black/60')
        ->and($component->html())->toContain("document.body.classList.add('overflow-hidden'); return () => document.body.classList.remove('overflow-hidden')")
        ->and($component->html())->not->toContain('backdrop-blur-sm');

    $component
        ->call('closeModal')
        ->assertSet('showModal', false)
        ->assertSet('selectedNotificationId', null);
});