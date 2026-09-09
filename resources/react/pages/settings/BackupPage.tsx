import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useToast } from '../../contexts/ToastContext';
import { useAuth } from '../../contexts/AuthContext';
import SettingsTabHeader from './SettingsTabHeader';
import {
    getBackups,
    updateBackupSettings,
    runBackup,
    cleanupBackup,
    deleteBackup,
    restoreBackup,
    backupDownloadUrl,
    getCloudCredentials,
    saveCloudCredentials,
    testCloudCredentials,
    clearCloudCredentials,
    type BackupIndexData,
    type BackupItem,
    type BackupRun,
    type BackupTierSettings,
    type CloudCredentialsSummary,
} from '../../api/backups';

const SCOPE_LABELS: Record<string, string> = { db: 'Database', files: 'Files', both: 'Database + Files' };

function formatBytes(bytes: number | null): string {
    if (bytes === null || bytes === undefined) return '—';
    if (bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
    return `${(bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

function StatusBadge({ status }: { status: BackupRun['status'] }) {
    const map: Record<BackupRun['status'], string> = {
        running: 'bg-blue-100 text-blue-700',
        success: 'bg-emerald-100 text-emerald-700',
        failed: 'bg-red-100 text-red-700',
    };
    return <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${map[status]}`}>{status}</span>;
}

type CredFieldKey = 'client_id' | 'client_secret' | 'refresh_token' | 'folder' | 'team_drive_id';

function CredFieldBadge({ field, creds }: { field: CredFieldKey; creds?: CloudCredentialsSummary }) {
    if (creds?.stored?.[field]) return <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Saved in app</span>;
    if (creds?.configured) return <span className="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold text-sky-700">From .env</span>;
    return <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500">Not set</span>;
}

function ConfirmModal({ open, title, description, children, onCancel, onConfirm, confirmLabel, danger }: {
    open: boolean;
    title: string;
    description?: string;
    children?: React.ReactNode;
    onCancel: () => void;
    onConfirm: () => void;
    confirmLabel: string;
    danger?: boolean;
}) {
    if (!open) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4" onClick={onCancel}>
            <div className="w-full max-w-lg rounded-xl bg-white shadow-2xl" onClick={e => e.stopPropagation()}>
                <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div>
                        <h3 className="font-semibold text-gray-900">{title}</h3>
                        {description && <p className="mt-0.5 text-sm text-gray-500">{description}</p>}
                    </div>
                    <button type="button" onClick={onCancel} className="rounded-lg p-2 text-gray-400 hover:bg-gray-100" aria-label="Close"><i className="fas fa-times"></i></button>
                </div>
                <div className="max-h-[70vh] overflow-y-auto p-5">{children}</div>
                <div className="flex justify-end gap-2 border-t border-gray-100 px-5 py-4">
                    <button type="button" onClick={onCancel} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="button" onClick={onConfirm} className={`rounded-lg px-4 py-2 text-sm font-medium text-white ${danger ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'}`}>{confirmLabel}</button>
                </div>
            </div>
        </div>
    );
}

export default function BackupPage() {
    const { addToast } = useToast();
    const { user } = useAuth();
    const queryClient = useQueryClient();
    const canManage = user?.permissions?.includes('backups.manage');
    const canRestore = user?.permissions?.includes('backups.restore');

    const [draft, setDraft] = useState<BackupIndexData['settings'] | null>(null);
    const [runTarget, setRunTarget] = useState<{ tier: 'local' | 'cloud'; scope: 'db' | 'files' | 'both' } | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<{ tier: 'local' | 'cloud'; item: BackupItem } | null>(null);
    const [restoreTarget, setRestoreTarget] = useState<{ tier: 'local' | 'cloud'; item: BackupItem } | null>(null);
    const [restoreForm, setRestoreForm] = useState({ scope: 'both' as 'db' | 'files' | 'both', current_password: '', confirmation: '', archive_password: '' });
    const [credsForm, setCredsForm] = useState<Record<CredFieldKey, string>>({ client_id: '', client_secret: '', refresh_token: '', folder: '', team_drive_id: '' });
    const [confirmClearCreds, setConfirmClearCreds] = useState(false);

    const { data, isLoading, isError, error } = useQuery({ queryKey: ['backups'], queryFn: getBackups });
    const payload = data?.data?.data as BackupIndexData | undefined;

    const credsQuery = useQuery({ queryKey: ['backups-cloud-credentials'], queryFn: getCloudCredentials });
    const creds = credsQuery.data?.data?.data as CloudCredentialsSummary | undefined;

    useEffect(() => {
        if (!creds) return;
        setCredsForm(f => ({
            ...f,
            folder: creds.folder ?? '',
            team_drive_id: creds.team_drive_id ?? '',
        }));
    }, [creds]);

    const invalidate = () => queryClient.invalidateQueries({ queryKey: ['backups'] });
    const invalidateCreds = () => queryClient.invalidateQueries({ queryKey: ['backups-cloud-credentials'] });

    const saveSettingsMutation = useMutation({
        mutationFn: (d: BackupIndexData['settings']) => updateBackupSettings({
            encryption_enabled: d.encryption_enabled,
            local: { enabled: d.mesh.local.enabled, schedule: d.mesh.local.schedule, scope: d.mesh.local.scope, retention_days: d.mesh.local.retention_days },
            cloud: { enabled: d.mesh.cloud.enabled, schedule: d.mesh.cloud.schedule, scope: d.mesh.cloud.scope, retention_days: d.mesh.cloud.retention_days },
        }),
        onSuccess: () => { invalidate(); setDraft(null); addToast('Backup settings saved.', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to save backup settings.', 'error'),
    });

    const runMutation = useMutation({
        mutationFn: (t: { tier: 'local' | 'cloud'; scope: 'db' | 'files' | 'both' }) => runBackup(t.tier, t.scope),
        onSuccess: (res: any) => { invalidate(); setRunTarget(null); addToast(res?.data?.message || 'Backup started.', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Backup failed.', 'error'),
    });

    const cleanupMutation = useMutation({
        mutationFn: (tier: 'local' | 'cloud') => cleanupBackup(tier),
        onSuccess: (res: any) => { invalidate(); addToast(res?.data?.message || 'Cleanup completed.', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Cleanup failed.', 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: (t: { tier: 'local' | 'cloud'; path: string }) => deleteBackup(t.tier, t.path),
        onSuccess: () => { invalidate(); setDeleteTarget(null); addToast('Backup deleted.', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to delete backup.', 'error'),
    });

    const restoreMutation = useMutation({
        mutationFn: () => restoreBackup({
            tier: restoreTarget!.tier,
            path: restoreTarget!.item.path,
            scope: restoreForm.scope,
            current_password: restoreForm.current_password,
            confirmation: restoreForm.confirmation,
            archive_password: restoreForm.archive_password,
        }),
        onSuccess: (res: any) => { invalidate(); setRestoreTarget(null); setRestoreForm({ scope: 'both', current_password: '', confirmation: '', archive_password: '' }); addToast(res?.data?.message || 'Restore completed.', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Restore failed.', 'error'),
    });

    const saveCredsMutation = useMutation({
        mutationFn: () => saveCloudCredentials({
            client_id: credsForm.client_id || undefined,
            client_secret: credsForm.client_secret || undefined,
            refresh_token: credsForm.refresh_token || undefined,
            folder: credsForm.folder || undefined,
            team_drive_id: credsForm.team_drive_id || undefined,
        }),
        onSuccess: (res: any) => {
            const next = res?.data?.data as CloudCredentialsSummary | undefined;
            setCredsForm({ client_id: '', client_secret: '', refresh_token: '', folder: next?.folder ?? '', team_drive_id: next?.team_drive_id ?? '' });
            invalidate(); invalidateCreds();
            addToast(res?.data?.message || 'Google Drive credentials saved.', 'success');
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to save credentials.', 'error'),
    });

    const testCredsMutation = useMutation({
        mutationFn: () => testCloudCredentials(),
        onSuccess: (res: any) => { invalidateCreds(); addToast(res?.data?.message || 'Connected to Google Drive.', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Connection test failed.', 'error'),
    });

    const clearCredsMutation = useMutation({
        mutationFn: () => clearCloudCredentials(),
        onSuccess: (res: any) => {
            setConfirmClearCreds(false);
            setCredsForm({ client_id: '', client_secret: '', refresh_token: '', folder: '', team_drive_id: '' });
            invalidate(); invalidateCreds();
            addToast(res?.data?.message || 'Stored Google Drive credentials removed.', 'success');
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to remove credentials.', 'error'),
    });

    const dirty = useMemo(() => {
        if (!draft || !payload) return false;
        return JSON.stringify(draft.mesh) !== JSON.stringify(payload.settings.mesh) || draft.encryption_enabled !== payload.settings.encryption_enabled;
    }, [draft, payload]);

    const editTier = (tier: 'local' | 'cloud', patch: Partial<BackupTierSettings>) => {
        setDraft(d => d ? { ...d, mesh: { ...d.mesh, [tier]: { ...d.mesh[tier], ...patch } } } : d);
    };

    if (isLoading) return <div className="space-y-3">{[...Array(4)].map((_, i) => <div key={i} className="h-24 animate-pulse rounded-xl bg-gray-100" />)}</div>;
    if (isError) return <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">Failed to load backups: {(error as any)?.message || 'Unknown error'}</div>;

    const tierCards: Array<{ tier: 'local' | 'cloud'; title: string; description: string; icon: string }> = [
        { tier: 'local', title: 'On-Server Backups', description: 'Stored on this server. Quick recovery, limited to disk space.', icon: 'fas fa-server' },
        { tier: 'cloud', title: 'Google Drive Backups', description: 'Encrypted archives stored off-site for long-term retention.', icon: 'fas fa-cloud' },
    ];

    return (
        <div className="space-y-6">
            <SettingsTabHeader
                title="Backup & Restore"
                description="Schedule encrypted backups, download archives, and restore the cooperative database or files."
                icon="fas fa-hard-drive"
            />

            {/* Encryption notice */}
            <div className={`rounded-xl border p-4 text-sm ${payload!.settings.encryption_enabled ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-gray-200 bg-gray-50 text-gray-600'}`}>
                <p className="font-semibold flex items-center gap-2"><i className="fas fa-lock"></i> Encryption is {payload!.settings.encryption_enabled ? 'ENABLED' : 'disabled'}</p>
                <p className="mt-1">Archives are encrypted with the <code className="rounded bg-white/70 px-1">BACKUP_ARCHIVE_PASSWORD</code> environment value. Keep it safe — backups cannot be decrypted without it.</p>
            </div>

            {/* Settings form */}
            {canManage && (
                <section className="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <div>
                            <h3 className="font-semibold text-gray-900">Schedule & Retention</h3>
                            <p className="mt-0.5 text-xs text-gray-500">Each tier runs independently on its own cron schedule.</p>
                        </div>
                        <div className="flex items-center gap-3">
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    checked={draft ? draft.encryption_enabled : payload!.settings.encryption_enabled}
                                    onChange={e => setDraft(d => d ? { ...d, encryption_enabled: e.target.checked } : d === null ? { ...payload!.settings, encryption_enabled: e.target.checked } : null)}
                                    className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                Encrypt archives
                            </label>
                        </div>
                    </div>
                    <div className="grid grid-cols-1 gap-4 p-5 lg:grid-cols-2">
                        {tierCards.map(({ tier, title, icon }) => {
                            const s = (draft ?? payload!.settings).mesh[tier];
                            return (
                                <div key={tier} className="rounded-lg border border-gray-200 p-4 space-y-3">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i className={icon}></i></div>
                                        <div className="flex-1">
                                            <p className="text-sm font-semibold text-gray-900">{title}</p>
                                            <p className="text-xs text-gray-500">Retains {s.retention_days} days · {SCOPE_LABELS[s.scope]}</p>
                                        </div>
                                        <label className="flex cursor-pointer items-center gap-2 text-xs font-medium text-gray-600">
                                            <input type="checkbox" checked={s.enabled} onChange={e => editTier(tier, { enabled: e.target.checked })} className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                                            Enabled
                                        </label>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-gray-600">Schedule (cron)</label>
                                            <input value={s.schedule} onChange={e => editTier(tier, { schedule: e.target.value })} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="0 2 * * *" />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs font-medium text-gray-600">Retention (days)</label>
                                            <input type="number" min={1} value={s.retention_days} onChange={e => editTier(tier, { retention_days: Number(e.target.value) })} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                                        </div>
                                        <div className="col-span-2">
                                            <label className="mb-1 block text-xs font-medium text-gray-600">What to back up</label>
                                            <div className="flex gap-2">
                                                {(['db', 'files', 'both'] as const).map(scope => (
                                                    <button key={scope} type="button" onClick={() => editTier(tier, { scope })} className={`rounded-lg px-3 py-2 text-xs font-medium ${s.scope === scope ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>{SCOPE_LABELS[scope]}</button>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    {dirty && (
                        <div className="flex items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-5 py-3">
                            <p className="text-sm font-medium text-amber-800">Unsaved changes</p>
                            <div className="flex gap-2">
                                <button type="button" onClick={() => setDraft(null)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Discard</button>
                                <button type="button" onClick={() => saveSettingsMutation.mutate(draft!)} disabled={saveSettingsMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">{saveSettingsMutation.isPending ? 'Saving...' : 'Save Settings'}</button>
                            </div>
                        </div>
                    )}
                </section>
            )}

            {/* Google Drive connection */}
            <section className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                    <div>
                        <h3 className="font-semibold text-gray-900">Google Drive Connection</h3>
                        <p className="mt-0.5 text-xs text-gray-500">Credentials for the cloud backup tier. Stored encrypted in the database with GOOGLE_DRIVE_* .env values used as fallback.</p>
                    </div>
                    <span className={`flex shrink-0 items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ${creds?.configured ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'}`}>
                        <span className={`h-2 w-2 rounded-full ${creds?.configured ? 'bg-emerald-500' : 'bg-gray-400'}`}></span>
                        {creds?.configured ? 'Configured' : 'Not configured'}
                    </span>
                </div>
                {credsQuery.isError ? (
                    <div className="p-5 text-center text-sm text-red-600">Failed to load credential status.</div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 p-5 lg:grid-cols-2">
                        {([
                            { key: 'client_id', label: 'Client ID', placeholder: 'Google Cloud OAuth client ID' },
                            { key: 'client_secret', label: 'Client Secret', placeholder: 'OAuth client secret', secret: true },
                            { key: 'refresh_token', label: 'Refresh Token', placeholder: 'OAuth refresh token (offline access)', secret: true },
                            { key: 'folder', label: 'Folder ID / Name', placeholder: 'Blank = root of the drive' },
                            { key: 'team_drive_id', label: 'Team Drive ID', placeholder: 'Only for a shared Drive' },
                        ] as Array<{ key: CredFieldKey; label: string; placeholder?: string; secret?: boolean }>).map(field => (
                            <div key={field.key}>
                                <label className="mb-1 flex items-center justify-between gap-2 text-xs font-medium text-gray-600">
                                    <span>{field.label}</span>
                                    <CredFieldBadge field={field.key} creds={creds} />
                                </label>
                                <input
                                    type={field.secret ? 'password' : 'text'}
                                    value={credsForm[field.key]}
                                    onChange={e => setCredsForm(f => ({ ...f, [field.key]: e.target.value }))}
                                    placeholder={field.placeholder}
                                    autoComplete="off"
                                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                                />
                            </div>
                        ))}
                    </div>
                )}
                {canManage && (
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-5 py-3">
                        <p className="text-xs text-gray-500"><i className="fas fa-info-circle mr-1"></i>Leaving a field blank keeps its current value. Use the reset button to clear stored values and fall back to .env.</p>
                        <div className="flex flex-wrap gap-2">
                            <button type="button" onClick={() => setConfirmClearCreds(true)} disabled={clearCredsMutation.isPending || !creds?.stored.client_id && !creds?.stored.client_secret && !creds?.stored.refresh_token} className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-50">
                                <i className="fas fa-trash mr-1"></i>Remove stored
                            </button>
                            <button type="button" onClick={() => testCredsMutation.mutate()} disabled={testCredsMutation.isPending || !creds?.configured} className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50">
                                <i className="fas fa-plug mr-1"></i>{testCredsMutation.isPending ? 'Testing...' : 'Test connection'}
                            </button>
                            <button type="button" onClick={() => saveCredsMutation.mutate()} disabled={saveCredsMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                <i className="fas fa-save mr-1"></i>{saveCredsMutation.isPending ? 'Saving...' : 'Save Credentials'}
                            </button>
                        </div>
                    </div>
                )}
            </section>

            {/* Tier lists */}
            <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                {tierCards.map(({ tier, title, description, icon }) => {
                    const t = payload!.tiers[tier];
                    const s = payload!.settings.mesh[tier];
                    const cloudUnconfigured = tier === 'cloud' && !t.configured;
                    return (
                        <section key={tier} className="rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div className="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4">
                                <div className="flex min-w-0 items-center gap-3">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i className={icon}></i></div>
                                    <div className="min-w-0">
                                        <h3 className="font-semibold text-gray-900">{title}</h3>
                                        <p className="mt-0.5 text-xs text-gray-500">{description}</p>
                                    </div>
                                </div>
                                {canManage && (
                                    <div className="flex shrink-0 gap-2">
                                        <button type="button" onClick={() => cleanupMutation.mutate(tier)} disabled={cleanupMutation.isPending || cloudUnconfigured} className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50" title="Delete backups older than retention">
                                            <i className="fas fa-broom mr-1"></i>Clean
                                        </button>
                                        <button type="button" onClick={() => setRunTarget({ tier, scope: 'both' })} disabled={runMutation.isPending || cloudUnconfigured} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                            <i className="fas fa-play mr-1"></i>Back Up Now
                                        </button>
                                    </div>
                                )}
                            </div>
                            {cloudUnconfigured ? (
                                <div className="p-5 text-center text-sm text-gray-500">
                                    <i className="fas fa-cloud-upload-alt mb-2 text-3xl text-gray-300"></i>
                                    <p>Google Drive is not configured. Add the client credentials in the <strong>Google Drive Connection</strong> section above to enable this tier.</p>
                                </div>
                            ) : (
                                <div className="divide-y divide-gray-100">
                                    {t.items.length === 0 && <p className="px-5 py-6 text-center text-sm text-gray-400">No backups yet. {s.enabled ? 'The scheduler will create one automatically.' : 'Run one manually or enable the schedule.'}</p>}
                                    {t.items.map(item => (
                                        <div key={item.path} className="flex items-center justify-between gap-3 px-5 py-3">
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-gray-800" title={item.path}>{item.name}</p>
                                                <p className="mt-0.5 text-xs text-gray-500">{formatDate(item.created_at)} · {formatBytes(item.size)}</p>
                                            </div>
                                            <div className="flex shrink-0 gap-2">
                                                {canRestore && (
                                                    <button type="button" onClick={() => setRestoreTarget({ tier, item })} className="rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-100" title="Restore this backup">
                                                        <i className="fas fa-rotate-left mr-1"></i>Restore
                                                    </button>
                                                )}
                                                {canManage && (
                                                    <a href={backupDownloadUrl(tier, item.path)} download className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50" title="Download archive">
                                                        <i className="fas fa-download"></i>
                                                    </a>
                                                )}
                                                {canManage && (
                                                    <button type="button" onClick={() => setDeleteTarget({ tier, item })} className="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50" title="Delete archive">
                                                        <i className="fas fa-trash"></i>
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                    );
                })}
            </div>

            {/* Recent runs */}
            <section className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-100 px-5 py-4">
                    <h3 className="font-semibold text-gray-900">Recent Activity</h3>
                    <p className="mt-0.5 text-xs text-gray-500">The most recent backup runs across all tiers.</p>
                </div>
                <div className="divide-y divide-gray-100">
                    {payload!.recent_runs.length === 0 && <p className="px-5 py-6 text-center text-sm text-gray-400">No backup activity yet.</p>}
                    {payload!.recent_runs.map(run => (
                        <div key={run.id} className="flex items-center justify-between gap-3 px-5 py-3">
                            <div className="min-w-0">
                                <p className="text-sm font-medium text-gray-800 capitalize">{run.tier} · {SCOPE_LABELS[run.scope]}</p>
                                <p className="mt-0.5 truncate text-xs text-gray-500">{run.filename || (run.status === 'failed' ? (run.message || 'Failed') : 'Pending')} · {formatDate(run.started_at)}</p>
                            </div>
                            <StatusBadge status={run.status} />
                        </div>
                    ))}
                </div>
            </section>

            {/* Clear cloud credentials modal */}
            <ConfirmModal
                open={confirmClearCreds}
                title="Remove stored Google Drive credentials?"
                description="Saved client ID, secret and refresh token will be deleted."
                danger
                onCancel={() => setConfirmClearCreds(false)}
                onConfirm={() => clearCredsMutation.mutate()}
                confirmLabel="Remove"
            >
                <p className="text-sm text-gray-600">The cloud tier will fall back to any <code className="rounded bg-gray-100 px-1">GOOGLE_DRIVE_*</code> values in <code className="rounded bg-gray-100 px-1">.env</code>. If none exist, cloud backups will stop until new credentials are saved.</p>
            </ConfirmModal>

            {/* Run backup modal */}
            <ConfirmModal
                open={!!runTarget}
                title="Run a backup now"
                description="Choose what to include in this archive."
                onCancel={() => setRunTarget(null)}
                onConfirm={() => runTarget && runMutation.mutate(runTarget)}
                confirmLabel="Start Backup"
            >
                <div className="flex gap-2">
                    {(['db', 'files', 'both'] as const).map(scope => (
                        <button key={scope} type="button" onClick={() => setRunTarget(t => t ? { ...t, scope } : t)} className={`flex-1 rounded-lg px-3 py-2 text-sm font-medium ${runTarget?.scope === scope ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>{SCOPE_LABELS[scope]}</button>
                    ))}
                </div>
                <p className="mt-3 text-xs text-gray-500">This runs a single on-demand backup to the {runTarget?.tier} tier. Scheduled backups are unaffected.</p>
            </ConfirmModal>

            {/* Delete modal */}
            <ConfirmModal
                open={!!deleteTarget}
                title="Delete this backup?"
                description={deleteTarget?.item.name}
                danger
                onCancel={() => setDeleteTarget(null)}
                onConfirm={() => deleteTarget && deleteMutation.mutate({ tier: deleteTarget.tier, path: deleteTarget.item.path })}
                confirmLabel="Delete"
            >
                <p className="text-sm text-gray-600">The archive will be permanently removed from the {deleteTarget?.tier} tier. This cannot be undone.</p>
            </ConfirmModal>

            {/* Restore modal */}
            <ConfirmModal
                open={!!restoreTarget}
                title="Restore from backup"
                description={restoreTarget?.item.name}
                danger
                onCancel={() => { setRestoreTarget(null); setRestoreForm({ scope: 'both', current_password: '', confirmation: '', archive_password: '' }); }}
                onConfirm={() => restoreTarget && restoreMutation.mutate()}
                confirmLabel={restoreMutation.isPending ? 'Restoring...' : 'Restore Now'}
            >
                <div className="space-y-4">
                    <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        <p className="font-semibold flex items-center gap-2"><i className="fas fa-exclamation-triangle"></i> Dangerous operation</p>
                        <p className="mt-1 text-xs">This replaces the current data and puts the application into maintenance mode until it finishes. It is gated by your password and requires typing <strong>RESTORE</strong> to confirm.</p>
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-600">What to restore</label>
                        <div className="flex gap-2">
                            {(['db', 'files', 'both'] as const).map(scope => (
                                <button key={scope} type="button" onClick={() => setRestoreForm(f => ({ ...f, scope }))} className={`flex-1 rounded-lg px-3 py-2 text-sm font-medium ${restoreForm.scope === scope ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>{SCOPE_LABELS[scope]}</button>
                            ))}
                        </div>
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-600">Your password (verification)</label>
                        <input type="password" value={restoreForm.current_password} onChange={e => setRestoreForm(f => ({ ...f, current_password: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-600">Archive encryption password</label>
                        <input type="password" value={restoreForm.archive_password} onChange={e => setRestoreForm(f => ({ ...f, archive_password: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="BACKUP_ARCHIVE_PASSWORD" />
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-gray-600">Type <strong>RESTORE</strong> to confirm</label>
                        <input value={restoreForm.confirmation} onChange={e => setRestoreForm(f => ({ ...f, confirmation: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </div>
                </div>
            </ConfirmModal>
        </div>
    );
}
