import { api } from './client';

export interface LogEntry {
    id: number;
    log_name: string;
    description: string;
    event: string;
    subject_type: string;
    subject_id: number;
    subject_name?: string | null;
    causer: { id: number; name: string; email: string } | null;
    properties: any;
    created_at: string;
}

export async function getLogs(params?: {
    search?: string;
    tag?: string;
    event?: string;
    user_id?: number;
    date_range?: string;
    sort?: string;
    direction?: string;
    page?: number;
    per_page?: number;
}) {
    return api.get('/api/logs', { params });
}
