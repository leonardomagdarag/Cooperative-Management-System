import { api } from './client';

export interface Announcement {
    id: number;
    title: string;
    content: string;
    type: string;
    type_label: string;
    visibility: string[];
    visibility_label: string;
    category: string | null;
    image_url: string | null;
    event_date: string | null;
    event_time: string | null;
    event_location: string | null;
    created_at: string;
    creator_name: string;
}

export async function getAnnouncements(params?: { page?: number; per_page?: number }) {
    return api.get('/api/announcements', { params });
}

export async function getAnnouncement(id: number) {
    return api.get(`/api/announcements/${id}`);
}

export async function createAnnouncement(data: FormData) {
    return api.post('/api/announcements', data, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
}

export async function updateAnnouncement(id: number, data: FormData) {
    return api.post(`/api/announcements/${id}`, data, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
}

export async function deleteAnnouncement(id: number) {
    return api.delete(`/api/announcements/${id}`);
}
