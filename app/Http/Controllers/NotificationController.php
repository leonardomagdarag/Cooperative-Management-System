<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;
    protected AuditLogService $auditLogService;

    public function __construct(NotificationService $notificationService, AuditLogService $auditLogService)
    {
        $this->notificationService = $notificationService;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get all notifications for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $query = Auth::user()
            ->notifications()
            ->active()
            ->orderByPriority();

        $status = $request->string('status')->toString();
        if ($status === 'unread') {
            $query->unread();
        } elseif ($status === 'read') {
            $query->read();
        }

        $type = $request->string('type')->toString();
        if ($type !== '') {
            $query->where('type', $type);
        }

        $category = $request->string('category')->toString();
        if ($category === 'security') {
            $query->where('type', 'security_alert');
        } elseif ($category === 'billing') {
            $query->where('type', 'loan_due');
        } elseif ($category === 'activity') {
            $query->whereIn('type', ['general', 'loan_payment']);
        } elseif ($category === 'announcements') {
            $query->where('type', 'announcement');
        }

        $perPage = max(5, min((int) $request->integer('per_page', 20), 50));
        $paginate = $request->boolean('paginate') || $request->has('page');

        if ($paginate) {
            $paginator = $query->paginate($perPage);

            return response()->json([
                'notifications' => $paginator->getCollection()->map(fn($notification) => $this->serializeNotification($notification)),
                'unread_count' => $this->getUnreadCount(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        }

        $notifications = $query
            ->limit(20)
            ->get()
            ->map(fn($notification) => $this->serializeNotification($notification));

        $unreadCount = $this->getUnreadCount();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Get unread count
     */
    public function unreadCount(): JsonResponse
    {
        $count = $this->getUnreadCount();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        $user = Auth::user();

        // Ensure the notification belongs to the authenticated user
        if ($notification->user_id !== Auth::id()) {
            $this->auditLogService->log(
                'notification.read_denied',
                'User attempted to mark another user notification as read.',
                'notification',
                $notification,
                $user,
                [
                    'notification_id' => $notification->id,
                    'owner_id' => $notification->user_id,
                ]
            );

            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->markAsRead();

        $this->auditLogService->log(
            'notification.read',
            'User marked notification as read.',
            'notification',
            $notification,
            $user,
            [
                'notification_id' => $notification->id,
                'type' => $notification->type,
            ]
        );

        return response()->json(['success' => true]);
    }

    private function getUnreadCount(): int
    {
        return Auth::user()
            ->notifications()
            ->unread()
            ->active()
            ->count();
    }

    private function serializeNotification(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'icon' => $notification->icon,
            'color_class' => $notification->color_class,
            'priority' => $notification->priority,
            'is_read' => $notification->is_read,
            'action_url' => $notification->action_url,
            'time_ago' => $notification->time_ago,
            'created_at' => $notification->created_at->toISOString(),
        ];
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();

        $updatedCount = $user
            ->notifications()
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        $this->auditLogService->log(
            'notification.read_all',
            'User marked all notifications as read.',
            'notification',
            null,
            $user,
            [
                'updated_count' => (int) $updatedCount,
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Delete a notification
     */
    public function destroy(Notification $notification): JsonResponse
    {
        $user = Auth::user();

        // Ensure the notification belongs to the authenticated user
        if ($notification->user_id !== Auth::id()) {
            $this->auditLogService->log(
                'notification.delete_denied',
                'User attempted to delete another user notification.',
                'notification',
                $notification,
                $user,
                [
                    'notification_id' => $notification->id,
                    'owner_id' => $notification->user_id,
                ]
            );

            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->delete();

        $this->auditLogService->log(
            'notification.deleted',
            'User deleted a notification.',
            'notification',
            null,
            $user,
            [
                'notification_id' => $notification->id,
                'type' => $notification->type,
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * Delete all read notifications
     */
    public function deleteAllRead(): JsonResponse
    {
        $user = Auth::user();

        $deletedCount = $user
            ->notifications()
            ->read()
            ->delete();

        $this->auditLogService->log(
            'notification.deleted_read_all',
            'User deleted all read notifications.',
            'notification',
            null,
            $user,
            [
                'deleted_count' => (int) $deletedCount,
            ]
        );

        return response()->json(['success' => true]);
    }
}
