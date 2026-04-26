<?php

namespace App\Livewire\Content;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Notifications extends Component
{
    use WithPagination;

    public string $status = 'all';
    public string $category = 'all';

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function markAsRead(int $notificationId): void
    {
        $notification = Auth::user()->notifications()->find($notificationId);

        if (! $notification) {
            return;
        }

        if (! $notification->is_read) {
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

    public function deleteRead(): void
    {
        Auth::user()
            ->notifications()
            ->read()
            ->delete();
    }

    public function render()
    {
        $query = Auth::user()
            ->notifications()
            ->active()
            ->orderByPriority();

        if ($this->status === 'unread') {
            $query->unread();
        } elseif ($this->status === 'read') {
            $query->read();
        }

        if ($this->category === 'security') {
            $query->where('type', 'security_alert');
        } elseif ($this->category === 'billing') {
            $query->where('type', 'loan_due');
        } elseif ($this->category === 'activity') {
            $query->whereIn('type', ['general', 'loan_payment']);
        } elseif ($this->category === 'announcements') {
            $query->where('type', 'announcement');
        }

        return view('livewire.content.notifications', [
            'notifications' => $query->paginate(12),
            'unreadCount' => Auth::user()->notifications()->unread()->active()->count(),
        ]);
    }
}
