import { api } from './client';

export type BackupTier = 'daily' | 'offsite';
export type BackupScope = 'db' | 'files' | 'both';

export interface BackupItem {
    path: string;
    name: string;
    size: number | null;
    created_at: string | null;
}

export interface BackupTierInfo {
    disk: string;
    schedule: string;
    scope: BackupScope;
    retention: { keep?: number; days?: number };
    last_auto_run: string | null;
}

export interface OffsiteStatus {
    configured: boolean;
    reachable: boolean | null;
    checked_at: string | null;
}

export interface BackupSettings {
    tiers: Record<BackupTier, BackupTierInfo>;
    daily_time: string;
    offsite: OffsiteStatus;
}

export interface BackupTierList {
    daily: { disk: string; items: BackupItem[] };
    offsite: { disk: string; items: BackupItem[] };
}

export interface BackupIndexData {
    settings: BackupSettings;
    tiers: BackupTierList;
    recent_runs: BackupRun[];
}

export interface BackupRun {
    id: number;
    tier: BackupTier;
    scope: BackupScope;
    status: 'queued' | 'running' | 'success' | 'failed';
    filename: string | null;
    size: number | null;
    message: string | null;
    started_at: string | null;
    finished_at: string | null;
    triggered_by?: string | null;
}

export async function getBackups() {
    return api.get('/api/backups');
}

export async function updateBackupSettings(data: { daily_time?: string; archive_password?: string; archive_password_confirmation?: string }) {
    return api.put('/api/backups/settings', data);
}

export async function runBackup(tier: BackupTier, scope: BackupScope) {
    return api.post(`/api/backups/${tier}/run`, { scope });
}

export async function cleanupBackup(tier: BackupTier) {
    return api.post(`/api/backups/${tier}/cleanup`);
}

export async function testOffsiteStorage() {
    return api.post('/api/backups/offsite/test');
}

export function backupDownloadUrl(tier: string, path: string) {
    return `/api/backups/${tier}/download?file=${encodeURIComponent(path)}`;
}

export async function deleteBackup(tier: BackupTier, path: string) {
    return api.delete(`/api/backups/${tier}/backup`, { params: { file: path } });
}

export async function restoreBackup(data: {
    tier?: BackupTier;
    path?: string;
    archive?: File;
    scope: BackupScope;
    current_password: string;
    confirmation: string;
}) {
    if (data.archive) {
        const form = new FormData();
        form.append('archive', data.archive);
        form.append('scope', data.scope);
        form.append('current_password', data.current_password);
        form.append('confirmation', data.confirmation);
        return api.post('/api/backups/restore', form);
    }

    return api.post('/api/backups/restore', data);
}
