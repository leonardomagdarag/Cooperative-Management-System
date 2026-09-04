import { api } from './client';

export interface BackupItem {
    path: string;
    name: string;
    size: number | null;
    created_at: string | null;
}

export interface BackupTierSettings {
    enabled: boolean;
    schedule: string;
    scope: 'db' | 'files' | 'both';
    retention_days: number;
    last_auto_run: string | null;
    configured?: boolean;
}

export interface BackupSettings {
    encryption_enabled: boolean;
    mesh: {
        local: BackupTierSettings;
        cloud: BackupTierSettings;
    };
}

export interface BackupIndexData {
    settings: BackupSettings;
    tiers: {
        local: { disk: string; items: BackupItem[] };
        cloud: { disk: string; items: BackupItem[]; configured: boolean };
    };
    recent_runs: BackupRun[];
}

export interface BackupRun {
    id: number;
    tier: 'local' | 'cloud';
    scope: 'db' | 'files' | 'both';
    status: 'running' | 'success' | 'failed';
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

export async function updateBackupSettings(data: {
    encryption_enabled: boolean;
    local: Omit<BackupTierSettings, 'last_auto_run' | 'configured'>;
    cloud: Omit<BackupTierSettings, 'last_auto_run' | 'configured'>;
}) {
    return api.put('/api/backups/settings', data);
}

export async function runBackup(tier: 'local' | 'cloud', scope: 'db' | 'files' | 'both') {
    return api.post(`/api/backups/${tier}/run`, { scope });
}

export async function cleanupBackup(tier: 'local' | 'cloud') {
    return api.post(`/api/backups/${tier}/cleanup`);
}

export function backupDownloadUrl(tier: string, path: string) {
    return `/api/backups/${tier}/download?file=${encodeURIComponent(path)}`;
}

export async function deleteBackup(tier: 'local' | 'cloud', path: string) {
    return api.delete(`/api/backups/${tier}/backup`, { params: { file: path } });
}

export async function restoreBackup(data: {
    tier: 'local' | 'cloud';
    path: string;
    scope: 'db' | 'files' | 'both';
    current_password: string;
    confirmation: string;
    archive_password: string;
}) {
    return api.post('/api/backups/restore', data);
}

export interface CloudCredentialsSummary {
    configured: boolean;
    folder: string | null;
    team_drive_id: string | null;
    stored: {
        client_id: boolean;
        client_secret: boolean;
        refresh_token: boolean;
        folder: boolean;
        team_drive_id: boolean;
    };
}

export interface CloudCredentialsInput {
    client_id?: string;
    client_secret?: string;
    refresh_token?: string;
    folder?: string;
    team_drive_id?: string;
}

export async function getCloudCredentials() {
    return api.get('/api/backups/cloud-credentials');
}

export async function saveCloudCredentials(data: CloudCredentialsInput) {
    return api.put('/api/backups/cloud-credentials', data);
}

export async function testCloudCredentials() {
    return api.post('/api/backups/cloud-credentials/test');
}

export async function clearCloudCredentials() {
    return api.delete('/api/backups/cloud-credentials');
}
