<?php

namespace App\Livewire\Content;

use App\Models\Announcement;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Announcements extends Component
{
    use WithPagination;

    public $title = '';
    public $content = '';
    public $editingId = null;
    public $showModal = false;

    protected $rules = [
        'title' => 'required|string|max:255',
        'content' => 'required|string|max:5000',
    ];

    public function render()
    {
        $announcements = Announcement::with('creator')
            ->latest()
            ->paginate(10);

        return view('livewire.content.announcements', [
            'announcements' => $announcements,
            'canManage' => $this->canManageAnnouncements(),
        ]);
    }

    public function canManageAnnouncements()
    {
        $user = Auth::user();

        return $user && (
            $user->can('announcements.create') ||
            $user->can('announcements.update') ||
            $user->can('announcements.delete')
        );
    }

    public function openModal()
    {
        if (!$this->canManageAnnouncements()) {
            return;
        }

        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->title = '';
        $this->content = '';
        $this->editingId = null;
        $this->resetValidation();
    }

    public function save()
    {
        if (!$this->canManageAnnouncements()) {
            session()->flash('error', 'You do not have permission to create announcements.');
            return;
        }

        $this->validate();

        if ($this->editingId) {
            $announcement = Announcement::find($this->editingId);
            if ($announcement) {
                $announcement->update([
                    'title' => $this->title,
                    'content' => $this->content,
                ]);
                session()->flash('success', 'Announcement updated successfully.');
            }
        } else {
            $announcement = Announcement::create([
                'title' => $this->title,
                'content' => $this->content,
                'created_by' => Auth::id(),
            ]);

            app(NotificationService::class)->notifyNewAnnouncement($announcement);
            session()->flash('success', 'Announcement created successfully.');
        }

        $this->closeModal();
    }

    public function edit($id)
    {
        if (!$this->canManageAnnouncements()) {
            return;
        }

        $announcement = Announcement::find($id);
        if ($announcement) {
            $this->editingId = $announcement->id;
            $this->title = $announcement->title;
            $this->content = $announcement->content;
            $this->showModal = true;
        }
    }

    public function delete($id)
    {
        if (!$this->canManageAnnouncements()) {
            session()->flash('error', 'You do not have permission to delete announcements.');
            return;
        }

        $announcement = Announcement::find($id);
        if ($announcement) {
            $announcement->delete();
            session()->flash('success', 'Announcement deleted successfully.');
        }
    }
}
