<?php

namespace App\Livewire\Content;

use App\Models\Announcement;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Announcements extends Component
{
    use WithPagination, WithFileUploads;

    public $title          = '';
    public $content        = '';
    public $type           = 'announcement';
    public bool $visEveryone  = false;
    public bool $visMembers   = true;
    public bool $visOfficers  = false;
    public bool $visPublic    = false;
    public $category       = '';
    public $event_date     = '';
    public $event_time     = '';
    public $event_location = '';
    public $image          = null;  // uploaded file (temporary Livewire UploadedFile)
    public $existingImage  = null; // path currently stored in DB
    public $editingId      = null;
    public $showModal      = false;
    public $selectedAnnouncement = null;
    public bool $showViewModal = false;

    protected function rules(): array
    {
        $base = [
            'title'          => 'required|string|max:255',
            'content'        => 'required|string|max:5000',
            'type'           => 'required|in:news,announcement,event',
            'visEveryone'    => 'boolean',
            'visMembers'     => 'boolean',
            'visOfficers'    => 'boolean',
            'visPublic'      => 'boolean',
            'category'       => 'nullable|string|max:100',
            'event_date'     => 'nullable|date',
            'event_time'     => 'nullable|string|max:50',
            'event_location' => 'nullable|string|max:255',
            'image'          => 'nullable|image|max:2048', // 2 MB
        ];

        if ($this->type === 'event') {
            $base['event_date'] = 'required|date';
        }

        return $base;
    }

    /** Compose the visibility JSON array from the four boolean flags. */
    private function computeVisibility(): array
    {
        if ($this->visEveryone) {
            return ['everyone'];
        }

        $visibility = [];
        if ($this->visMembers)  $visibility[] = 'members';
        if ($this->visOfficers) $visibility[] = 'officers';
        if ($this->visPublic)   $visibility[] = 'public';

        return $visibility ?: ['members']; // fallback: never save an empty array
    }

    /** Populate the four boolean flags from a stored visibility array. */
    private function applyVisibility(array $visibility): void
    {
        $this->visEveryone = in_array('everyone', $visibility, true);
        $this->visMembers  = in_array('members',  $visibility, true);
        $this->visOfficers = in_array('officers', $visibility, true);
        $this->visPublic   = in_array('public',   $visibility, true);
    }

    public function render()
    {
        $announcements = $this->announcementQueryForCurrentUser()
            ->paginate(10);

        return view('livewire.content.announcements', [
            'announcements' => $announcements,
            'canCreateAnnouncements' => $this->canCreateAnnouncements(),
            'canUpdateAnnouncements' => $this->canUpdateAnnouncements(),
            'canDeleteAnnouncements' => $this->canDeleteAnnouncements(),
        ]);
    }

    private function announcementQueryForCurrentUser(): Builder
    {
        $user = Auth::user();

        $query = Announcement::query()
            ->with('creator.personalData')
            ->latest();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->isOfficerUser()) {
            return $this->hasAnyAnnouncementPermission()
                ? $query
                : $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) {
            $builder->whereIn('visibility', ['cooperative', 'public'])
                ->orWhere(function (Builder $jsonBuilder) {
                    $jsonBuilder->whereRaw('json_valid(visibility)')
                        ->where(function (Builder $visibilityBuilder) {
                            $visibilityBuilder->whereJsonContains('visibility', Announcement::VISIBILITY_MEMBERS)
                                ->orWhereJsonContains('visibility', Announcement::VISIBILITY_PUBLIC)
                                ->orWhereJsonContains('visibility', Announcement::VISIBILITY_EVERYONE);
                        });
                });
        });
    }

    private function isOfficerUser(): bool
    {
        $user = Auth::user();

        return (bool) ($user && (($user->is_officer ?? false) || $user->hasRole('admin')));
    }

    private function hasAnyAnnouncementPermission(): bool
    {
        $user = Auth::user();

        return (bool) ($user && collect([
            'announcements.view',
            'announcements.create',
            'announcements.update',
            'announcements.delete',
        ])->contains(fn (string $permission) => $user->can($permission)));
    }

    public function canCreateAnnouncements(): bool
    {
        $user = Auth::user();

        return (bool) ($user && $user->can('announcements.create'));
    }

    public function canUpdateAnnouncements(): bool
    {
        $user = Auth::user();

        return (bool) ($user && $user->can('announcements.update'));
    }

    public function canDeleteAnnouncements(): bool
    {
        $user = Auth::user();

        return (bool) ($user && $user->can('announcements.delete'));
    }

    public function openModal()
    {
        if (! $this->canCreateAnnouncements()) {
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

    public function viewAnnouncement(int $id): void
    {
        $announcement = $this->announcementQueryForCurrentUser()
            ->whereKey($id)
            ->first();

        if (! $announcement) {
            session()->flash('error', 'You do not have permission to view that announcement.');
            return;
        }

        $creatorName = 'Unknown';

        if ($announcement->creator) {
            $creatorName = trim(
                (($announcement->creator->personalData->first_name ?? '') . ' ' . ($announcement->creator->personalData->last_name ?? ''))
            ) ?: 'Unknown';
        }

        $this->selectedAnnouncement = [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'content' => $announcement->content,
            'type' => $announcement->type,
            'type_label' => $announcement->type_label,
            'visibility_label' => $announcement->visibility_label,
            'category' => $announcement->category,
            'image_url' => $announcement->image_path ? asset('storage/' . $announcement->image_path) : null,
            'created_at_label' => $announcement->created_at?->format('F d, Y h:i A'),
            'creator_name' => $creatorName,
            'event_date_label' => $announcement->event_date?->format('F d, Y'),
            'event_time' => $announcement->event_time,
            'event_location' => $announcement->event_location,
        ];
        $this->showViewModal = true;
    }

    public function closeViewModal(): void
    {
        $this->showViewModal = false;
        $this->selectedAnnouncement = null;
    }

    public function updatedType(string $value): void
    {
        // News and events default to everyone-visible; announcements are members-only.
        if (in_array($value, ['news', 'event'], true)) {
            $this->visEveryone = true;
            $this->visMembers  = false;
            $this->visOfficers = false;
            $this->visPublic   = false;
        } elseif ($value === 'announcement') {
            $this->visEveryone = false;
            $this->visMembers  = true;
            $this->visOfficers = false;
            $this->visPublic   = false;
        }
    }

    public function resetForm()
    {
        $this->title          = '';
        $this->content        = '';
        $this->type           = 'announcement';
        $this->visEveryone    = false;
        $this->visMembers     = true;
        $this->visOfficers    = false;
        $this->visPublic      = false;
        $this->category       = '';
        $this->event_date     = '';
        $this->event_time     = '';
        $this->event_location = '';
        $this->image          = null;
        $this->existingImage  = null;
        $this->editingId      = null;
        $this->resetValidation();
    }

    public function save()
    {
        $canProceed = $this->editingId
            ? $this->canUpdateAnnouncements()
            : $this->canCreateAnnouncements();

        if (! $canProceed) {
            session()->flash('error', $this->editingId
                ? 'You do not have permission to edit announcements.'
                : 'You do not have permission to create announcements.');
            return;
        }

        $this->validate();

        // Require at least one visibility option.
        if (!$this->visEveryone && !$this->visMembers && !$this->visOfficers && !$this->visPublic) {
            $this->addError('visibility', 'Please select at least one visibility option.');
            return;
        }

        $visibilityArray = $this->computeVisibility();

        // Handle image upload.
        $imagePath = $this->existingImage; // default: keep existing
        if ($this->image) {
            // Delete old image if replacing.
            if ($this->existingImage && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->existingImage)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($this->existingImage);
            }
            $imagePath = $this->image->store('announcements', 'public');
        }

        if ($this->editingId) {
            $announcement = Announcement::query()->find($this->editingId);
            if ($announcement) {
                $announcement->update([
                    'title'          => $this->title,
                    'content'        => $this->content,
                    'type'           => $this->type,
                    'visibility'     => $visibilityArray,
                    'category'       => $this->category ?: null,
                    'image_path'     => $imagePath,
                    'event_date'     => $this->type === 'event' ? ($this->event_date ?: null) : null,
                    'event_time'     => $this->type === 'event' ? ($this->event_time ?: null) : null,
                    'event_location' => $this->type === 'event' ? ($this->event_location ?: null) : null,
                ]);
                session()->flash('success', 'Announcement updated successfully.');
            }
        } else {
            $announcement = Announcement::create([
                'title'          => $this->title,
                'content'        => $this->content,
                'type'           => $this->type,
                'visibility'     => $visibilityArray,
                'category'       => $this->category ?: null,
                'image_path'     => $imagePath,
                'event_date'     => $this->type === 'event' ? ($this->event_date ?: null) : null,
                'event_time'     => $this->type === 'event' ? ($this->event_time ?: null) : null,
                'event_location' => $this->type === 'event' ? ($this->event_location ?: null) : null,
                'created_by'     => Auth::id(),
            ]);

            try {
                app(NotificationService::class)->notifyNewAnnouncement($announcement);
            } catch (\Throwable $e) {
                Log::error('Failed to send announcement notification', ['id' => $announcement->id, 'error' => $e->getMessage()]);
            }
            session()->flash('success', 'Announcement created successfully.');
        }

        $this->closeModal();
    }

    public function edit($id)
    {
        if (! $this->canUpdateAnnouncements()) {
            return;
        }

        $announcement = Announcement::query()->find($id);
        if ($announcement) {
            $this->editingId      = $announcement->id;
            $this->title          = $announcement->title;
            $this->content        = $announcement->content;
            $this->type           = $announcement->type;
            $this->applyVisibility((array) ($announcement->visibility ?? ['members']));
            $this->category       = $announcement->category ?? '';
            $this->event_date     = $announcement->event_date ? $announcement->event_date->format('Y-m-d') : '';
            $this->event_time     = $announcement->event_time ?? '';
            $this->event_location = $announcement->event_location ?? '';
            $this->image          = null;
            $this->existingImage  = $announcement->image_path;
            $this->showModal      = true;
        }
    }

    public function delete($id)
    {
        if (! $this->canDeleteAnnouncements()) {
            session()->flash('error', 'You do not have permission to delete announcements.');
            return;
        }

        $deleted = Announcement::query()
            ->whereKey($id)
            ->delete();

        if ($deleted) {
            session()->flash('success', 'Announcement deleted successfully.');
        }
    }
}
