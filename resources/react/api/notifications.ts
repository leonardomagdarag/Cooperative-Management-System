import { api } from './client';

export interface Notification {
    id: number;
    type: string;
    data: {
        title: string;
        message: string;
        category?: string;
        url?: string;
    };
    read_at: string | null;
    created_at: string;
}

export async function getNotifications(params?: { status?: string; category?: string; page?: number }) {
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
