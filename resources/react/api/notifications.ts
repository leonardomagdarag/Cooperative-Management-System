import { api } from './client';

export interface Notification {
    id: number;
    type: string;
    title: string;
    message: string;
    icon?: string;
    color_class?: string;
    priority?: string;
    is_read: boolean;
    action_url?: string;
    time_ago?: string;
    created_at: string;
}

export async function getNotifications(params?: { status?: string; category?: string; page?: number; per_page?: number }) {
    return api.get('/api/notifications', { params });
}

export async function getUnreadCount() {
    return api.get('/api/notifications/unread-count');
}

export async function markAsRead(id: number) {
    return api.post(`/api/notifications/${id}/read`);
}

export async function markAllAsRead() {
    return api.post('/api/notifications/mark-all-read');
}

export async function deleteNotification(id: number) {
    return api.delete(`/api/notifications/${id}`);
}

export async function deleteAllReadNotifications() {
    return api.delete('/api/notifications/read/all');
}
