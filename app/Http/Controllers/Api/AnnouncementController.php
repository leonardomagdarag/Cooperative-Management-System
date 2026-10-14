<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = Announcement::query()
            ->with('creator.personalData')
            ->latest();

        if ($this->isOfficerUser($user)) {
            if (! $this->hasAnyAnnouncementPermission($user)) {
                return response()->json(['data' => [], 'pagination' => $this->emptyPagination()]);
            }
        } else {
            $query->where(function ($builder) {
                $builder->whereIn('visibility', ['cooperative', 'public'])
                    ->orWhere(function ($jsonBuilder) {
                        $jsonBuilder->whereRaw('json_valid(visibility)')
                            ->where(function ($visibilityBuilder) {
                                $visibilityBuilder->whereJsonContains('visibility', Announcement::VISIBILITY_MEMBERS)
                                    ->orWhereJsonContains('visibility', Announcement::VISIBILITY_PUBLIC)
                                    ->orWhereJsonContains('visibility', Announcement::VISIBILITY_EVERYONE);
                            });
                    });
            });
        }

        $perPage = max(5, min((int) $request->integer('per_page', 10), 50));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($a) => $this->serialize($a)),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'permissions' => [
                'can_create' => $user->can('announcements.create'),
                'can_update' => $user->can('announcements.update'),
                'can_delete' => $user->can('announcements.delete'),
            ],
        ]);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $user = Auth::user();
        abort_unless($this->canViewAnnouncement($user, $announcement), 403, 'Unauthorized to view this announcement.');

        $creatorName = 'Unknown';
        if ($announcement->creator) {
            $creatorName = trim(
                (($announcement->creator->personalData->first_name ?? '').' '.($announcement->creator->personalData->last_name ?? ''))
            ) ?: 'Unknown';
        }

        return response()->json([
            'id' => $announcement->id,
            'title' => $announcement->title,
            'content' => $announcement->content,
            'type' => $announcement->type,
            'type_label' => $announcement->type_label,
            'visibility_label' => $announcement->visibility_label,
            'category' => $announcement->category,
            'image_url' => $announcement->image_path ? asset('storage/'.$announcement->image_path) : null,
            'created_at' => $announcement->created_at?->format('F d, Y h:i A'),
            'creator_name' => $creatorName,
            'event_date' => $announcement->event_date?->format('Y-m-d'),
            'event_time' => $announcement->event_time,
            'event_location' => $announcement->event_location,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('announcements.create'), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:5000',
            'type' => 'required|in:news,announcement,event',
            'visibility' => 'required|array|min:1',
            'visibility.*' => 'string|in:everyone,members,officers,public',
            'category' => 'nullable|string|max:100',
            'event_date' => 'nullable|date',
            'event_time' => 'nullable|string|max:50',
            'event_location' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($validated['type'] === 'event' && empty($validated['event_date'])) {
            return response()->json(['message' => 'Event date is required for events.'], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('announcements', 'public');
        }

        $announcement = Announcement::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'type' => $validated['type'],
            'visibility' => $validated['visibility'],
            'category' => $validated['category'] ?? null,
            'image_path' => $imagePath,
            'event_date' => $validated['type'] === 'event' ? ($validated['event_date'] ?? null) : null,
            'event_time' => $validated['type'] === 'event' ? ($validated['event_time'] ?? null) : null,
            'event_location' => $validated['type'] === 'event' ? ($validated['event_location'] ?? null) : null,
            'created_by' => Auth::id(),
        ]);

        try {
            app(NotificationService::class)->notifyNewAnnouncement($announcement);
        } catch (\Throwable $e) {
            Log::error('Failed to send announcement notification', ['id' => $announcement->id, 'error' => $e->getMessage()]);
        }

        app(AuditLogService::class)->log('announcements.created', 'Announcement published.', 'announcement', $announcement, $user, ['title' => $announcement->title, 'type' => $announcement->type]);

        return response()->json([
            'message' => 'Announcement created successfully.',
            'data' => $this->serialize($announcement),
        ], 201);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('announcements.update'), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:5000',
            'type' => 'required|in:news,announcement,event',
            'visibility' => 'required|array|min:1',
            'visibility.*' => 'string|in:everyone,members,officers,public',
            'category' => 'nullable|string|max:100',
            'event_date' => 'nullable|date',
            'event_time' => 'nullable|string|max:50',
            'event_location' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:2048',
        ]);

        $imagePath = $announcement->image_path;
        if ($request->hasFile('image')) {
            if ($announcement->image_path) {
                Storage::disk('public')->delete($announcement->image_path);
            }
            $imagePath = $request->file('image')->store('announcements', 'public');
        }

        $announcement->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'type' => $validated['type'],
            'visibility' => $validated['visibility'],
            'category' => $validated['category'] ?? null,
            'image_path' => $imagePath,
            'event_date' => $validated['type'] === 'event' ? ($validated['event_date'] ?? null) : null,
            'event_time' => $validated['type'] === 'event' ? ($validated['event_time'] ?? null) : null,
            'event_location' => $validated['type'] === 'event' ? ($validated['event_location'] ?? null) : null,
        ]);

        app(AuditLogService::class)->log('announcements.updated', 'Announcement updated.', 'announcement', $announcement, $user, ['title' => $announcement->title, 'type' => $announcement->type]);

        return response()->json([
            'message' => 'Announcement updated successfully.',
            'data' => $this->serialize($announcement),
        ]);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->can('announcements.delete'), 403);

        $announcement->delete();

        app(AuditLogService::class)->log('announcements.deleted', 'Announcement deleted.', 'announcement', $announcement, $user, ['title' => $announcement->title, 'type' => $announcement->type]);

        return response()->json(['message' => 'Announcement deleted successfully.']);
    }

    private function isOfficerUser($user): bool
    {
        return (bool) ($user && (($user->is_officer ?? false) || $user->hasRole('admin')));
    }

    private function hasAnyAnnouncementPermission($user): bool
    {
        return (bool) ($user && collect([
            'announcements.view',
            'announcements.create',
            'announcements.update',
            'announcements.delete',
        ])->contains(fn (string $permission) => $user->can($permission)));
    }

    private function canViewAnnouncement($user, Announcement $announcement): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isOfficerUser($user) && $this->hasAnyAnnouncementPermission($user)) {
            return true;
        }

        $visibility = $announcement->visibility;
        if (is_string($visibility)) {
            return in_array($visibility, ['cooperative', 'public', 'members', 'everyone'], true);
        }

        if (is_array($visibility)) {
            return ! empty(array_intersect($visibility, [
                Announcement::VISIBILITY_MEMBERS,
                Announcement::VISIBILITY_PUBLIC,
                Announcement::VISIBILITY_EVERYONE,
                'cooperative',
            ]));
        }

        return false;
    }

    private function serialize(Announcement $a): array
    {
        $creatorName = 'Unknown';
        if ($a->creator) {
            $creatorName = trim(
                (($a->creator->personalData->first_name ?? '').' '.($a->creator->personalData->last_name ?? ''))
            ) ?: 'Unknown';
        }

        return [
            'id' => $a->id,
            'title' => $a->title,
            'content' => $a->content,
            'type' => $a->type,
            'type_label' => $a->type_label,
            'visibility' => $a->visibility,
            'visibility_label' => $a->visibility_label,
            'category' => $a->category,
            'image_url' => $a->image_path ? asset('storage/'.$a->image_path) : null,
            'event_date' => $a->event_date?->format('Y-m-d'),
            'event_time' => $a->event_time,
            'event_location' => $a->event_location,
            'created_at' => $a->created_at->toISOString(),
            'creator_name' => $creatorName,
        ];
    }

    private function emptyPagination(): array
    {
        return [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 10,
            'total' => 0,
        ];
    }
}
