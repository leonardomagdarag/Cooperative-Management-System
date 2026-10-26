import HelpTip from '../../components/ui/HelpTip';
import { useRef, useState } from 'react';
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
import { formatBytes, formatDate } from '../../lib/format';

const SCOPE_LABELS: Record<string, string> = { db: 'Database', files: 'Files', both: 'Database + Files' };

const TIER_DEFS: Record<BackupTier, { title: string; description: string; icon: string }> = {
    daily: { title: 'Daily Backups', description: 'Daily on this server. Retained for 30 days.', icon: 'fas fa-server' },
    offsite: { title: 'Off-Site Backups', description: 'Daily in Backblaze B2 for 7 days.', icon: 'fas fa-cloud' },
};

function scheduleLabel(time: string): string {
    const [hour, minute] = time.split(':').map(Number);
    const suffix = hour >= 12 ? 'PM' : 'AM';
    return `Every day at ${hour % 12 || 12}:${String(minute).padStart(2, '0')} ${suffix}`;
}

function retentionLabel(tier: BackupTier): string {
    switch (tier) {
        case 'daily': return 'Retain 30 days';
        case 'offsite': return 'Retain 7 days';
        default: return '';
    }
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

    const [runTarget, setRunTarget] = useState<{ tier: BackupTier; scope: BackupScope } | null>(null);
    const [dailyTime, setDailyTime] = useState('');
    const [deleteTarget, setDeleteTarget] = useState<{ tier: BackupTier; item: BackupItem } | null>(null);
    const [restoreTarget, setRestoreTarget] = useState<{ tier?: BackupTier; item?: BackupItem; archive?: File } | null>(null);
    const [restoreForm, setRestoreForm] = useState({ scope: 'both' as BackupScope, current_password: '', confirmation: '', acknowledged: false });
    const [archivePassword, setArchivePassword] = useState('');
    const [archivePasswordConfirmation, setArchivePasswordConfirmation] = useState('');
    const archiveInput = useRef<HTMLInputElement>(null);

    const { data, isLoading, isError, error, refetch, isFetching } = useQuery({ queryKey: ['backups'], queryFn: getBackups, refetchInterval: query => {
        const runs = (query.state.data?.data?.data as BackupIndexData | undefined)?.recent_runs ?? [];
        return runs.some(run => run.status === 'queued' || run.status === 'running') ? 3000 : false;
    } });
    const payload = data?.data?.data as BackupIndexData | undefined;

    const invalidate = () => queryClient.invalidateQueries({ queryKey: ['backups'] });

    const dailyScheduleMutation = useMutation({
        mutationFn: (time: string) => updateBackupSettings({ daily_time: time }),
        onSuccess: (res: any) => {
            invalidate();
            addToast(res?.data?.message || 'Daily backup schedule change submitted for chairman approval.', 'info', 8000);
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to request the daily backup schedule change.', 'error'),
    });
    const archivePasswordMutation = useMutation({
        mutationFn: () => updateBackupSettings({
            archive_password: archivePassword,
            archive_password_confirmation: archivePasswordConfirmation,
        }),
        onSuccess: (res: any) => {
            setArchivePassword('');
            setArchivePasswordConfirmation('');
            addToast(res?.data?.message || 'Backup password change submitted for chairman approval.', 'info', 8000);
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to request the backup password change.', 'error'),
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
        onSuccess: (res: any) => {
            invalidate();
            setDeleteTarget(null);
            const isPending = res?.data?.status === 'pending' || res?.status === 202;
            const message = res?.data?.message || (isPending ? 'Backup deletion requested and requires countersign approval.' : 'Backup deleted.');
            addToast(message, isPending ? 'info' : 'success', isPending ? 8000 : undefined);
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to delete backup.', 'error'),
    });

    const restoreMutation = useMutation({
        mutationFn: () => restoreBackup({
            tier: restoreTarget?.tier,
            path: restoreTarget?.item?.path,
            archive: restoreTarget?.archive,
            scope: restoreForm.scope,
            current_password: restoreForm.current_password,
            confirmation: restoreForm.confirmation,
        }),
        onSuccess: (res: any) => {
            invalidate();
            setRestoreTarget(null);
            setRestoreForm({ scope: 'both', current_password: '', confirmation: '', acknowledged: false });
            const isPending = res?.data?.status === 'pending' || res?.status === 202;
            const message = res?.data?.message || (isPending ? 'Restore requested and requires countersign approval.' : 'Restore queued.');
            addToast(message, isPending ? 'info' : 'success', isPending ? 8000 : undefined);
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Restore failed.', 'error'),
    });

    if (isLoading) return <div className="space-y-3">{[...Array(4)].map((_, i) => <div key={i} className="h-24 animate-pulse rounded-xl bg-gray-100" />)}</div>;
    if (isError) return <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">Failed to load backups: {(error as any)?.message || 'Unknown error'}</div>;

    const settings = payload!.settings;
    const offsiteStatus = settings.offsite;
    const configuredDailyTime = settings.daily_time || '03:00';

    const tierTotal = (tier: BackupTier) =>
        payload!.tiers[tier].items.reduce((sum, item) => sum + (item.size ?? 0), 0);
    const onServerTotal = tierTotal('daily');
    const offsiteTotal = tierTotal('offsite');
    const onServerCount = payload!.tiers.daily.items.length;
    const offsiteCount = payload!.tiers.offsite.items.length;
    const totalArchives = onServerCount + offsiteCount;

    return (
        <div className="space-y-6">
            <SettingsTabHeader
                eyebrow="Data integrity"
                title="Backup & Restore"
                description="Automatic encrypted backups run daily at the selected time. Download archives, test the off-site connection, or restore when needed."
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
            {/* Storage summary */}
            <section className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="grid grid-cols-1 divide-y divide-gray-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                    <div className="flex items-center gap-3 px-5 py-4">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i className="fas fa-server"></i></div>
                        <div className="min-w-0">
                            <p className="text-xs uppercase tracking-wide text-gray-500">On-server storage</p>
                            <p className="text-lg font-semibold tabular-nums text-gray-900">{formatBytes(onServerTotal)}</p>
                            <p className="text-xs text-gray-500">{onServerCount} archive{onServerCount === 1 ? '' : 's'} (daily)</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 px-5 py-4">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600"><i className="fas fa-cloud"></i></div>
                        <div className="min-w-0">
                            <p className="text-xs uppercase tracking-wide text-gray-500">Off-site storage</p>
                            <p className="text-lg font-semibold tabular-nums text-gray-900">{formatBytes(offsiteTotal)}</p>
                            <p className="text-xs text-gray-500">{offsiteCount} archive{offsiteCount === 1 ? '' : 's'} (Backblaze B2)</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 px-5 py-4">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600"><i className="fas fa-box-archive"></i></div>
                        <div className="min-w-0">
                            <p className="text-xs uppercase tracking-wide text-gray-500">Total archives</p>
                            <p className="text-lg font-semibold tabular-nums text-gray-900">{totalArchives}</p>
                            <p className="text-xs text-gray-500">Across all tiers</p>
                        </div>
                    </div>
                </div>
            </section>
            {/* Tier lists */}
            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:col-span-2">
                    {(Object.keys(TIER_DEFS) as BackupTier[]).map(tier => {
                    const t = payload!.tiers[tier];
                    const offsiteUnconfigured = tier === 'offsite' && !offsiteStatus.configured;
                    return (
                        <section key={tier} className="flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div className="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4">
                                    <div className="flex min-w-0 items-center gap-3">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i className={TIER_DEFS[tier].icon}></i></div>
                                    <div className="min-w-0">
                                        <h3 className="font-semibold text-gray-900">{TIER_DEFS[tier].title}</h3>
                                        <p className="mt-0.5 text-xs text-gray-500">{TIER_DEFS[tier].description}</p>
                                    </div>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                {!offsiteUnconfigured && (
                                    <div className="shrink-0 text-right">
                                        <p className="text-sm font-semibold tabular-nums text-gray-900">{formatBytes(tierTotal(tier))}</p>
                                        <p className="mt-0.5 text-xs text-gray-500">{t.items.length} archive{t.items.length === 1 ? '' : 's'}</p>
                                    </div>
                                )}
                                <button type="button" onClick={() => void refetch()} disabled={isFetching} className="rounded-lg border border-gray-200 px-2.5 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50" title={`Refresh ${TIER_DEFS[tier].title}`} aria-label={`Refresh ${TIER_DEFS[tier].title}`}>
                                    <i className={`fas fa-rotate-right ${isFetching ? 'animate-spin' : ''}`}></i>
                                </button>
                                </div>
                            </div>
                            <div>
                            {offsiteUnconfigured ? (
                                <div className="p-5 text-center text-sm text-gray-500">
                                    <i className="fas fa-cloud-upload-alt mb-2 text-3xl text-gray-300"></i>
                                    <p>Backblaze B2 is not configured. Set the <strong>AWS_*</strong> values in <code className="rounded bg-gray-100 px-1">.env</code> to enable this tier.</p>
                                </div>
                            ) : (
                                <>
                                    <div className="h-[320px] overflow-y-auto divide-y divide-gray-100">
                                        {t.items.length === 0 && <p className="px-5 py-6 text-center text-sm text-gray-400">No backups yet. The scheduler will create one automatically, or run one manually.</p>}
                                        {t.items.map(item => (
                                            <div key={item.path} className="flex min-h-16 items-center justify-between gap-3 px-5 py-3">
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium text-gray-800" title={item.path}>{item.name}</p>
                                                    <p className="mt-0.5 text-xs text-gray-500">{formatDate(item.created_at)} · <span className="tabular-nums" title={`${item.size ?? 'unknown'} bytes · ${item.path}`}>{formatBytes(item.size)}</span></p>
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
                                        <div className="mt-auto flex flex-wrap items-center gap-2 border-t border-gray-100 bg-gray-50 px-5 py-3">
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
                            </div>
                        </section>
                    );
                    })}
                </div>
                <div className="grid grid-cols-1 gap-6">
                    <section className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <div className="flex items-start gap-3">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i className="fas fa-clock"></i></div>
                            <div className="min-w-0">
                                <h3 className="font-semibold text-gray-900">Daily backup schedule</h3>
                                <p className="mt-1 text-xs text-gray-500">Daily and off-site backups run at this time. Changes require chairman countersign approval.</p>
                            </div>
                        </div>
                        <div className="mt-4 flex items-end gap-2">
                            <label className="flex-1 text-sm text-gray-600">
                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide">Time</span>
                                <input type="time" value={dailyTime || configuredDailyTime} onChange={event => setDailyTime(event.target.value)} disabled={!canManage} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-50" />
                            </label>
                            {canManage && (
                                <button type="button" onClick={() => dailyScheduleMutation.mutate(dailyTime || configuredDailyTime)} disabled={dailyScheduleMutation.isPending} className="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                    {dailyScheduleMutation.isPending ? 'Requesting...' : 'Save'}
                                </button>
                            )}
                        </div>
                        <p className="mt-3 text-xs text-gray-500">Current: <strong>{scheduleLabel(configuredDailyTime)}</strong></p>
                    </section>
                    <section className="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                        <div className="flex items-start gap-3">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700"><i className="fas fa-upload"></i></div>
                            <div>
                                <h3 className="font-semibold text-amber-900">Restore backup</h3>
                                <p className="mt-1 text-xs text-amber-800">Upload a downloaded archive to restore it with the required confirmation checks.</p>
                            </div>
                        </div>
                        {canRestore && (
                            <>
                                <input
                                    ref={archiveInput}
                                    type="file"
                                    accept=".zip,application/zip"
                                    className="hidden"
                                    onChange={event => {
                                        const archive = event.target.files?.[0];
                                        if (archive) {
                                            setRestoreForm({ scope: 'both', current_password: '', confirmation: '', acknowledged: false });
                                            setRestoreTarget({ archive });
                                        }
                                        event.target.value = '';
                                    }}
                                />
                                <button type="button" onClick={() => archiveInput.current?.click()} className="mt-4 w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100">
                                    <i className="fas fa-upload mr-2"></i>Restore downloaded backup
                                </button>
                            </>
                        )}
                    </section>
                </div>
            </div>

            <section className="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800 shadow-sm">
                <div className="flex items-start gap-3">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700"><i className="fas fa-lock"></i></div>
                    <div className="min-w-0 flex-1">
                        <p className="font-semibold">Backup encryption</p>
                        <p className="mt-1">Backups are encrypted by default. Keep a local copy of your archives; the archive password is encrypted in the database and never shown after it is saved.</p>
                        {canManage && (
                            <div className="mt-4 border-t border-amber-200 pt-4">
                                    <p className="text-xs font-semibold uppercase tracking-wide">Change encryption password</p>
                                    <p className="mt-1 text-xs">Hidden after entry. Use at least 8 characters with uppercase, lowercase, a number, and a symbol.</p>
                                    <div className="mt-2 grid gap-2">
                                        <input type="password" value={archivePassword} onChange={event => setArchivePassword(event.target.value)} className="rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm" placeholder="New archive password" autoComplete="new-password" />
                                        <input type="password" value={archivePasswordConfirmation} onChange={event => setArchivePasswordConfirmation(event.target.value)} className="rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm" placeholder="Confirm archive password" autoComplete="new-password" />
                                        <button type="button" onClick={() => archivePasswordMutation.mutate()} disabled={archivePasswordMutation.isPending || archivePassword.length < 8 || archivePassword !== archivePasswordConfirmation} className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100 disabled:opacity-50">
                                            {archivePasswordMutation.isPending ? 'Requesting...' : 'Request password change'}
                                        </button>
                                    </div>
                            </div>
                        )}
                    </div>
                </div>
            </section>

            {/* Recent runs */}
            <section className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                    <div>
                    <h3 className="font-semibold text-gray-900">Recent Activity</h3>
                    <p className="mt-0.5 text-xs text-gray-500">The most recent backup runs across all tiers.</p>
                    </div>
                    <button type="button" onClick={() => void refetch()} disabled={isFetching} className="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50" title="Refresh recent activity" aria-label="Refresh recent activity">
                        <i className={`fas fa-rotate-right mr-1.5 ${isFetching ? 'animate-spin' : ''}`}></i>Refresh
                    </button>
                </div>
                <div className="divide-y divide-gray-100">
                    {payload!.recent_runs.length === 0 && <p className="px-5 py-6 text-center text-sm text-gray-400">No backup activity yet.</p>}
                    {payload!.recent_runs.map(run => (
                        <div key={run.id} className="flex items-center justify-between gap-3 px-5 py-3">
                            <div className="min-w-0">
                                <p className="text-sm font-medium text-gray-800 capitalize">{TIER_DEFS[run.tier]?.title ?? run.tier} · {SCOPE_LABELS[run.scope]}</p>
                                <p className="mt-0.5 truncate text-xs text-gray-500">{run.filename || (run.status === 'failed' ? (run.message || 'Failed') : 'Pending')} · <span className="tabular-nums">{formatBytes(run.size)}</span> · {formatDate(run.started_at)}</p>
                            </div>
                            <StatusBadge status={run.status} />
                        </div>
                    ))}
                </div>
            </section>

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
                description={restoreTarget?.item?.name ?? restoreTarget?.archive?.name}
                danger
                onCancel={() => { setRestoreTarget(null); setRestoreForm({ scope: 'both', current_password: '', confirmation: '', acknowledged: false }); }}
                onConfirm={() => restoreTarget && restoreMutation.mutate()}
                confirmLabel={restoreMutation.isPending ? 'Restoring...' : 'Restore Now'}
                confirmDisabled={restoreMutation.isPending || restoreForm.current_password.length === 0 || restoreForm.confirmation !== 'RESTORE' || !restoreForm.acknowledged}
            >
<div className="space-y-4">
                    <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        <p className="font-semibold flex items-center gap-2"><i className="fas fa-exclamation-triangle"></i> The whole system goes offline</p>
                        <p className="mt-1 text-xs">The restore requires chairman approval. After approval, there is a 15-second grace period before the cooperative management system goes offline for the restore. During the restore, every member, officer and administrator is blocked; nobody can deposit, withdraw, borrow, repay, or use any other feature until it finishes. It is gated by your password and requires typing <strong>RESTORE</strong> to confirm.</p>
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
