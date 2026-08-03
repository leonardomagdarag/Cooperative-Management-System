import { api } from './client';

export interface SearchItem {
    label: string;
    subtitle?: string;
    url: string;
    icon?: string;
    meta?: string;
}

export interface SearchGroup {
    label: string;
    items: SearchItem[];
}

export async function globalSearch(q: string, signal?: AbortSignal): Promise<SearchGroup[]> {
    const { data } = await api.get('/api/search', { params: { q }, signal });
    return data.groups ?? [];
}
