import { api } from './client';

export interface Branch {
    id: number;
    code: string;
    name: string;
    is_head_office: boolean;
    address: string | null;
    contact_number: string | null;
    email: string | null;
    is_active: boolean;
    users_count: number;
    transactions_count: number;
    loans_count: number;
    members_count: number;
    created_at: string | null;
}

export interface BranchOption {
    id: number;
    name: string;
    is_head_office: boolean;
}

export async function getBranches(params?: { search?: string }) {
    return api.get('/api/branches', { params });
}

export async function getBranchOptions() {
    return api.get('/api/branches/options');
}

export async function createBranch(data: Record<string, unknown>) {
    return api.post('/api/branches', data);
}

export async function updateBranch(id: number, data: Record<string, unknown>) {
    return api.put(`/api/branches/${id}`, data);
}

export async function deleteBranch(id: number) {
    return api.delete(`/api/branches/${id}`);
}