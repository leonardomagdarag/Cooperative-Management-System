import HelpTip from '../../components/ui/HelpTip';
import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useToast } from '../../contexts/ToastContext';
import { useAuth } from '../../contexts/AuthContext';
import SettingsTabHeader from './SettingsTabHeader';
import {
    getBackups,
    updateBackupSettings,
    runBackup,
    cleanupBackup,
    testOffsiteStorage,
    deleteBackup,
    restoreBackup,
    backupDownloadUrl,
    type BackupIndexData,
    type BackupItem,
    type BackupRun,
    type BackupScope,
    type BackupTier,
} from '../../api/backups';

const SCOPE_LABELS: Record<string, string> = { db: 'Database', files: 'Files', both: 'Database + Files' };

const TIER_DEFS: Record<BackupTier, { title: string; description: string; icon: string }> = {
    hourly: { title: 'Hourly Backups', description: 'Every hour on this server. Only the 3 most recent copies are kept.', icon: 'fas fa-clock' },
    daily: { title: 'Daily Backups', description: 'Every day at 3:00 AM on this server. Retained for 7 days.', icon: 'fas fa-server' },
    offsite: { title: 'Off-Site Backups', description: 'Daily at 3:00 AM, stored in Backblaze B2 for 7 days.', icon: 'fas fa-cloud' },
};

function scheduleLabel(schedule: string): string {
    const map: Record<string, string> = {
        '0 * * * *': 'Every hour, on the hour',
        '0 3 * * *': 'Every day at 3:00 AM',
    };
    return map[schedule] ?? schedule;
}

function retentionLabel(tier: BackupTier): string {
    switch (tier) {
        case 'hourly': return 'Keep the 3 newest copies';
        case 'daily': return 'Retain 7 days';
        case 'offsite': return 'Retain 7 days';
        default: return '';
    }
}

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
        queued: 'bg-amber-100 text-amber-700',
        running: 'bg-blue-100 text-blue-700',
        success: 'bg-emerald-100 text-emerald-700',
        failed: 'bg-red-100 text-red-700',
    };
    return <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${map[status]}`}>{status}</span>;
}

function ConfirmModal({ open, title, description, children, onCancel, onConfirm, confirmLabel, danger, confirmDisabled }: {
    open: boolean;
    title: string;
    description?: string;
    children?: React.ReactNode;
    onCancel: () => void;
    onConfirm: () => void;
    confirmLabel: string;
    danger?: boolean;
    confirmDisabled?: boolean;
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
                    <button type="button" onClick={onConfirm} disabled={confirmDisabled} className={`rounded-lg px-4 py-2 text-sm font-medium text-white ${danger ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'} disabled:cursor-not-allowed disabled:opacity-50`}>{confirmLabel}</button>
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

    const [draftEncryption, setDraftEncryption] = useState<boolean | null>(null);
    const [runTarget, setRunTarget] = useState<{ tier: BackupTier; scope: BackupScope } | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<{ tier: BackupTier; item: BackupItem } | null>(null);
    const [restoreTarget, setRestoreTarget] = useState<{ tier: BackupTier; item: BackupItem } | null>(null);
    const [restoreForm, setRestoreForm] = useState({ scope: 'both' as BackupScope, current_password: '', confirmation: '', archive_password: '', acknowledged: false });

    const { data, isLoading, isError, error } = useQuery({ queryKey: ['backups'], queryFn: getBackups, refetchInterval: query => {
        const runs = (query.state.data?.data?.data as BackupIndexData | undefined)?.recent_runs ?? [];
        return runs.some(run => run.status === 'queued' || run.status === 'running') ? 3000 : false;
    } });
    const payload = data?.data?.data as BackupIndexData | undefined;

    const invalidate = () => queryClient.invalidateQueries({ queryKey: ['backups'] });

    const saveSettingsMutation = useMutation({
        mutationFn: (enabled: boolean) => updateBackupSettings({ encryption_enabled: enabled }),
        onSuccess: () => { invalidate(); setDraftEncryption(null); addToast('Backup settings saved.', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to save backup settings.', 'error'),
    });

    const runMutation = useMutation({
        mutationFn: (t: { tier: BackupTier; scope: BackupScope }) => runBackup(t.tier, t.scope),
        onSuccess: (res: any) => { invalidate(); setRunTarget(null); addToast(res?.data?.message || 'Backup started.', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Backup failed.', 'error'),
    });

    const cleanupMutation = useMutation({
        mutationFn: (tier: BackupTier) => cleanupBackup(tier),
        onSuccess: (res: any) => { invalidate(); addToast(res?.data?.message || 'Cleanup completed.', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Cleanup failed.', 'error'),
    });

    const testOffsiteMutation = useMutation({
        mutationFn: () => testOffsiteStorage(),
        onSuccess: (res: any) => { invalidate(); addToast(res?.data?.message || 'Connected to Backblaze B2.', 'success'); },
        onError: (err: any) => { invalidate(); addToast(err.response?.data?.message || 'Off-site connection test failed.', 'error'); },
    });

    const deleteMutation = useMutation({
        mutationFn: (t: { tier: BackupTier; path: string }) => deleteBackup(t.tier, t.path),
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
        onSuccess: (res: any) => { invalidate(); setRestoreTarget(null); setRestoreForm({ scope: 'both', current_password: '', confirmation: '', archive_password: '', acknowledged: false }); addToast(res?.data?.message || 'Restore queued.', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Restore failed.', 'error'),
    });

    if (isLoading) return <div className="space-y-3">{[...Array(4)].map((_, i) => <div key={i} className="h-24 animate-pulse rounded-xl bg-gray-100" />)}</div>;
    if (isError) return <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">Failed to load backups: {(error as any)?.message || 'Unknown error'}</div>;

    const settings = payload!.settings;
    const offsiteStatus = settings.offsite;

    const shownEncryption = draftEncryption ?? settings.encryption_enabled;

    return (
        <div className="space-y-6">
            <SettingsTabHeader
                eyebrow="Data integrity"
                title="Backup & Restore"
                description="Automatic encrypted backups run on a fixed schedule. Download archives, test the off-site connection, or restore when needed."
                icon="fas fa-hard-drive"
                helpText="Keep backups in a separate location and confirm schedules are running. Restoring replaces current data with the selected archive, so verify the archive and scope before starting a restore."
            />

            {/* Off-site connection */}
            <section className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <div className="flex min-w-0 items-center gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i className="fas fa-cloud"></i></div>
                        <div className="min-w-0">
                            <h3 className="font-semibold text-gray-900">Off-Site Storage Connection</h3>
                            <p className="mt-0.5 text-xs text-gray-500">Backblaze B2, configured from .env (AWS_* / AWS_ENDPOINT values).</p>
                        </div>
                    </div>
                    {canManage && (
                        <button type="button" onClick={() => testOffsiteMutation.mutate()} disabled={testOffsiteMutation.isPending} className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50">
                            <i className="fas fa-plug mr-1"></i>{testOffsiteMutation.isPending ? 'Testing...' : 'Test connection'}
                        </button>
                    )}
                </div>
                <div className="px-5 pb-5">
                    {offsiteStatus.configured === false ? (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            <p className="font-semibold flex items-center gap-2"><i className="fas fa-exclamation-triangle"></i> Off-site storage is not configured</p>
                            <p className="mt-1 text-xs">Set <code className="rounded bg-white/70 px-1">AWS_ACCESS_KEY_ID</code>, <code className="rounded bg-white/70 px-1">AWS_SECRET_ACCESS_KEY</code>, <code className="rounded bg-white/70 px-1">AWS_DEFAULT_REGION</code>, <code className="rounded bg-white/70 px-1">AWS_BUCKET</code> and <code className="rounded bg-white/70 px-1">AWS_ENDPOINT</code> in <code className="rounded bg-white/70 px-1">.env</code> and run <code className="rounded bg-white/70 px-1">config:clear</code>.</p>
                        </div>
                    ) : offsiteStatus.reachable === true ? (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                            <p className="font-semibold flex items-center gap-2"><span className="h-2 w-2 rounded-full bg-emerald-500"></span> Connected to Backblaze B2</p>
                            <p className="mt-1 text-xs">The off-site storage is reachable.{' '}{offsiteStatus.checked_at ? <>Last checked {formatDate(offsiteStatus.checked_at)}.</> : null}</p>
                        </div>
                    ) : (
                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            <p className="font-semibold flex items-center gap-2"><span className="h-2 w-2 rounded-full bg-red-500"></span> Off-site storage is unreachable</p>
                            <p className="mt-1 text-xs">Could not reach Backblaze B2. Check the .env credentials, bucket and endpoint, then use <strong>Test connection</strong>.{offsiteStatus.checked_at ? <> Last checked {formatDate(offsiteStatus.checked_at)}.</> : null}</p>
                        </div>
                    )}
                </div>
            </section>
            {/* Encryption notice */}
            <div className={`rounded-xl border p-4 text-sm ${settings.encryption_enabled ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-gray-200 bg-gray-50 text-gray-600'}`}>
                <p className="font-semibold flex items-center gap-2"><i className="fas fa-lock"></i> Encryption is {settings.encryption_enabled ? 'ENABLED' : 'disabled'}</p>
                <p className="mt-1">Archives are encrypted with the <code className="rounded bg-white/70 px-1">BACKUP_ARCHIVE_PASSWORD</code> environment value. Keep it safe — backups cannot be decrypted without it.</p>
                {canManage && (
                    <label className="mt-3 flex w-fit cursor-pointer items-center gap-2 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            checked={shownEncryption}
                            onChange={e => setDraftEncryption(e.target.checked)}
                            className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        Encrypt archives
                    </label>
                )}
                {canManage && draftEncryption !== null && (
                    <div className="mt-3 flex items-center gap-2">
                        <button type="button" onClick={() => saveSettingsMutation.mutate(draftEncryption)} disabled={saveSettingsMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">{saveSettingsMutation.isPending ? 'Saving...' : 'Save'}</button>
                        <button type="button" onClick={() => setDraftEncryption(null)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                    </div>
                )}
            </div>
            {/* Tier lists */}
            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                {(Object.keys(TIER_DEFS) as BackupTier[]).map(tier => {
                    const t = payload!.tiers[tier];
                    const offsiteUnconfigured = tier === 'offsite' && !offsiteStatus.configured;
                    return (
                        <section key={tier} className="rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div className="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4">
                                <div className="flex min-w-0 items-center gap-3">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i className={TIER_DEFS[tier].icon}></i></div>
                                    <div className="min-w-0">
                                        <h3 className="font-semibold text-gray-900">{TIER_DEFS[tier].title}</h3>
                                        <p className="mt-0.5 text-xs text-gray-500">{TIER_DEFS[tier].description}</p>
                                    </div>
                                </div>
                            </div>
                            {offsiteUnconfigured ? (
                                <div className="p-5 text-center text-sm text-gray-500">
                                    <i className="fas fa-cloud-upload-alt mb-2 text-3xl text-gray-300"></i>
                                    <p>Backblaze B2 is not configured. Set the <strong>AWS_*</strong> values in <code className="rounded bg-gray-100 px-1">.env</code> to enable this tier.</p>
                                </div>
                            ) : (
                                <>
                                    <div className="divide-y divide-gray-100">
                                        {t.items.length === 0 && <p className="px-5 py-6 text-center text-sm text-gray-400">No backups yet. The scheduler will create one automatically, or run one manually.</p>}
                                        {t.items.map(item => (
                                            <div key={item.path} className="flex items-center justify-between gap-3 px-5 py-3">
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium text-gray-800" title={item.path}>{item.name}</p>
                                                    <p className="mt-0.5 text-xs text-gray-500">{formatDate(item.created_at)} · {formatBytes(item.size)}</p>
                                                </div>
                                                <div className="flex shrink-0 flex-wrap justify-end gap-2">
                                                    {canRestore && (
                                                        <button type="button" onClick={() => setRestoreTarget({ tier, item })} className="rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-100" title="Restore this backup">
                                                            <i className="fas fa-rotate-left mr-1"></i>Restore
                                                        </button>
                                                    )}
                                                    {canManage && (
                                                        <a href={backupDownloadUrl(tier, item.path)} download className="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50" title="Download archive">
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
                                    {canManage && (
                                        <div className="flex flex-wrap items-center gap-2 border-t border-gray-100 bg-gray-50 px-5 py-3">
                                            <button type="button" onClick={() => setRunTarget({ tier, scope: 'both' })} disabled={runMutation.isPending} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                                <i className="fas fa-play mr-1"></i>Back Up Now
                                            </button>
                                            <button type="button" onClick={() => cleanupMutation.mutate(tier)} disabled={cleanupMutation.isPending} className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50" title="Delete archives older than the retention policy">
                                                <i className="fas fa-broom mr-1"></i>Clean
                                            </button>
                                        </div>
                                    )}
                                </>
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
                                <p className="text-sm font-medium text-gray-800 capitalize">{TIER_DEFS[run.tier]?.title ?? run.tier} · {SCOPE_LABELS[run.scope]}</p>
                                <p className="mt-0.5 truncate text-xs text-gray-500">{run.filename || (run.status === 'failed' ? (run.message || 'Failed') : 'Pending')} · {formatDate(run.started_at)}</p>
                            </div>
                            <StatusBadge status={run.status} />
                        </div>
                    ))}
                </div>
            </section>

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
                <p className="mt-3 text-xs text-gray-500">This runs a single on-demand backup to the {runTarget?.tier ? TIER_DEFS[runTarget.tier].title : ''} tier. Scheduled backups are unaffected.</p>
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
                <p className="text-sm text-gray-600">The archive will be permanently removed from the {deleteTarget?.tier ? TIER_DEFS[deleteTarget.tier].title : ''} tier. This cannot be undone.</p>
            </ConfirmModal>

            {/* Restore modal */}
            <ConfirmModal
                open={!!restoreTarget}
                title="Restore from backup"
                description={restoreTarget?.item.name}
                danger
                onCancel={() => { setRestoreTarget(null); setRestoreForm({ scope: 'both', current_password: '', confirmation: '', archive_password: '', acknowledged: false }); }}
                onConfirm={() => restoreTarget && restoreMutation.mutate()}
                confirmLabel={restoreMutation.isPending ? 'Restoring...' : 'Restore Now'}
                confirmDisabled={restoreMutation.isPending || restoreForm.current_password.length === 0 || restoreForm.confirmation !== 'RESTORE' || !restoreForm.acknowledged}
            >
<div className="space-y-4">
                    <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        <p className="font-semibold flex items-center gap-2"><i className="fas fa-exclamation-triangle"></i> The whole system goes offline</p>
                        <p className="mt-1 text-xs">For the entire duration of the restore, the cooperative management system is completely unavailable. Every member, officer and administrator is signed out and blocked &mdash; including you, and including the officer who started the restore. Nobody can deposit, withdraw, borrow, repay, or use any other feature until it finishes. It is gated by your password and requires typing <strong>RESTORE</strong> to confirm.</p>
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
                        <label className="mb-1 block text-xs font-medium text-gray-600">Backup password <span className="font-normal text-gray-400">(leave blank only for unencrypted archives)</span></label>
                        <input type="password" value={restoreForm.archive_password} onChange={e => setRestoreForm(f => ({ ...f, archive_password: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="Enter the backup password" />
                    </div>
<div>
                        <label className="mb-1 block text-xs font-medium text-gray-600">Type <strong>RESTORE</strong> to confirm</label>
                        <input value={restoreForm.confirmation} onChange={e => setRestoreForm(f => ({ ...f, confirmation: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </div>
                    {/* Final confirmation. Typing RESTORE proves intent, but it does not
                        prove the operator understood that everyone is locked out, which is
                        the consequence that actually matters here. */}
                    <label className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm ${restoreForm.acknowledged ? 'border-red-300 bg-red-50 text-red-800' : 'border-gray-200 bg-gray-50 text-gray-700'}`}>
                        <input type="checkbox" checked={restoreForm.acknowledged} onChange={e => setRestoreForm(f => ({ ...f, acknowledged: e.target.checked }))} className="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-red-600 focus:ring-red-500" />
                        <span className="text-xs">
                            <span className="font-semibold">Final confirmation.</span> I understand the <strong>entire system is unusable</strong> for the full duration of this restore. All members and officers will see a maintenance notice and will not be able to sign in or transact. If the restore fails or is interrupted, the system may stay offline until it is resolved.
                        </span>
                    </label>
                </div>
            </ConfirmModal>
        </div>
    );
}
