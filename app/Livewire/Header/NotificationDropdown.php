<?php

namespace App\Livewire\Header;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationDropdown extends Component
{
    public function markAsRead(int $notificationId): void
    {
        $notification = Auth::user()->notifications()->find($notificationId);

        if ($notification && ! $notification->is_read) {
            $notification->markAsRead();
        }
    }

    public function markAllAsRead(): void
    {
        Auth::user()
            ->notifications()
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    public function openNotification(int $notificationId): void
    {
        $notification = Auth::user()->notifications()->find($notificationId);

        if ($notification && ! $notification->is_read) {
            $notification->markAsRead();
        }

        $this->dispatch('open-notifications-inbox', notificationId: $notificationId);
    }

    public function openInbox(): void
    {
        $this->dispatch('open-notifications-inbox');
    }

    public function render()
    {
        $notifications = Auth::user()
            ->notifications()
            ->active()
            ->orderByPriority()
            ->take(6)
            ->get();

        $unreadCount = Auth::user()
            ->notifications()
            ->unread()
            ->active()
            ->count();

        return view('livewire.header.notification-dropdown', [
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
        ]);
    }
}
