import HelpTip from '../../components/ui/HelpTip';
import LoadingIndicator from '../../components/ui/LoadingIndicator';
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
    daily: { title: 'Local Backups', description: 'On-server retention (14 Daily · 5 Weekly · 12 Monthly).', icon: 'fas fa-server' },
    offsite: { title: 'Off-Site Backups', description: 'Backblaze B2 replica retention (14 Daily · 5 Weekly · 12 Monthly).', icon: 'fas fa-cloud' },
};

function scheduleLabel(time: string): string {
    const [hour, minute] = time.split(':').map(Number);
    const suffix = hour >= 12 ? 'PM' : 'AM';
    return `Every day at ${hour % 12 || 12}:${String(minute).padStart(2, '0')} ${suffix} (GMT+8)`;
}

function retentionLabel(tier: BackupTier, payload?: BackupIndexData): string {
    const gfs = payload?.settings?.tiers?.[tier]?.retention?.gfs;
    if (gfs) {
        return `${gfs.daily_days} Daily · ${gfs.weekly_weeks} Weekly · ${gfs.monthly_months} Monthly`;
    }
    return '14 Daily · 5 Weekly · 12 Monthly';
}

function partitionGfsItems(items: BackupItem[], dailyDays = 14, weeklyWeeks = 5) {
    const daily: BackupItem[] = [];
    const weekly: BackupItem[] = [];
    const monthly: BackupItem[] = [];

    const now = Date.now();
    const dailyMs = dailyDays * 24 * 60 * 60 * 1000;
    const weeklyMs = (dailyDays + weeklyWeeks * 7) * 24 * 60 * 60 * 1000;

    for (const item of items) {
        if (item.gfs_tier === 'daily') {
            daily.push(item);
        } else if (item.gfs_tier === 'weekly') {
            weekly.push(item);
        } else if (item.gfs_tier === 'monthly') {
            monthly.push(item);
        } else {
            const timestamp = item.created_at ? new Date(item.created_at).getTime() : now;
            const age = now - timestamp;
            if (age <= dailyMs) {
                daily.push(item);
            } else if (age <= weeklyMs) {
                weekly.push(item);
            } else {
                monthly.push(item);
            }
        }
    }

    return { daily, weekly, monthly };
}

function ArchiveTierCard({
    title,
    badge,
    badgeColor,
    scheduleInfo,
    icon,
    items,
    tier,
    emptyMessage,
    canRestore,
    canManage,
    onRestore,
    onDelete,
    onBackupNow,
}: {
    title: string;
    badge: string;
    badgeColor: string;
    scheduleInfo: string;
    icon: string;
    items: BackupItem[];
    tier: BackupTier;
    emptyMessage: string;
    canRestore: boolean;
    canManage: boolean;
    onRestore: (item: BackupItem) => void;
    onDelete: (item: BackupItem) => void;
    onBackupNow?: () => void;
}) {
    const totalSize = items.reduce((sum, item) => sum + (item.size ?? 0), 0);

    return (
        <div className="flex flex-col rounded-xl border border-gray-200 bg-white shadow-2xs overflow-hidden">
            <div className="flex flex-col border-b border-gray-100 bg-gray-50/80 px-4 py-3 gap-2">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2.5">
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-600 shadow-2xs">
                            <i className={`${icon} text-sm`}></i>
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h4 className="text-xs font-bold uppercase tracking-wider text-gray-800">{title}</h4>
                                <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${badgeColor}`}>{badge}</span>
                            </div>
                        </div>
                    </div>
                    <div className="text-right text-xs text-gray-500 tabular-nums">
                        <span className="font-semibold text-gray-700">{items.length}</span> · {formatBytes(totalSize)}
                    </div>
                </div>
                <div className="flex items-center justify-between text-[11px] text-gray-500 pt-1 border-t border-gray-100">
                    <span className="flex items-center gap-1.5 text-gray-600 font-medium">
                        <i className="fas fa-clock text-gray-400 text-[10px]"></i>
                        <span>{scheduleInfo}</span>
                    </span>
                    {onBackupNow && canManage && (
                        <button
                            type="button"
                            onClick={onBackupNow}
                            className="rounded bg-emerald-600 px-2 py-0.5 text-[11px] font-medium text-white hover:bg-emerald-700 transition"
                            title="Run an on-demand daily backup now"
                        >
                            <i className="fas fa-play mr-1 text-[9px]"></i>Back Up Now
                        </button>
                    )}
                </div>
            </div>
            <div className="h-72 overflow-y-auto divide-y divide-gray-100 p-1.5">
                {items.length === 0 ? (
                    <div className="flex h-full flex-col items-center justify-center p-6 text-center text-xs text-gray-400">
                        <i className="fas fa-box-open mb-2 text-2xl text-gray-300"></i>
                        <p className="font-medium text-gray-500">{emptyMessage}</p>
                        <p className="mt-1 text-[11px] text-gray-400">Archives are retained according to the schedule.</p>
                    </div>
                ) : (
                    items.map(item => (
                        <div key={item.path} className="flex min-h-12 items-center justify-between gap-3 p-2.5 hover:bg-gray-50/90 rounded-lg transition">
                            <div className="min-w-0 pr-1">
                                <p className="truncate text-xs font-medium text-gray-800" title={item.path}>{item.name}</p>
                                <p className="mt-0.5 text-[11px] text-gray-500">
                                    {formatDate(item.created_at)} · <span className="tabular-nums font-mono text-gray-600">{formatBytes(item.size)}</span>
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-1.5">
                                {canRestore && (
                                    <button
                                        type="button"
                                        onClick={() => onRestore(item)}
                                        className="rounded bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-700 hover:bg-amber-100 transition"
                                        title="Restore this backup"
                                    >
                                        <i className="fas fa-rotate-left mr-1"></i>Restore
                                    </button>
                                )}
                                {canManage && (
                                    <a
                                        href={backupDownloadUrl(tier, item.path)}
                                        download
                                        className="rounded border border-gray-200 p-1.5 text-xs text-gray-600 hover:bg-gray-50 transition"
                                        title="Download archive"
                                    >
                                        <i className="fas fa-download"></i>
                                    </a>
                                )}
                                {canManage && (
                                    <button
                                        type="button"
                                        onClick={() => onDelete(item)}
                                        className="rounded border border-red-200 p-1.5 text-xs text-red-600 hover:bg-red-50 transition"
                                        title="Delete archive"
                                    >
                                        <i className="fas fa-trash"></i>
                                    </button>
                                )}
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
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

function ConfirmModal({ open, title, description, children, onCancel, onConfirm, confirmLabel, danger, confirmDisabled, isLoading }: {
    open: boolean;
    title: string;
    description?: string;
    children?: React.ReactNode;
    onCancel: () => void;
    onConfirm: () => void;
    confirmLabel: string;
    danger?: boolean;
    confirmDisabled?: boolean;
    isLoading?: boolean;
}) {
    if (!open) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
                <h3 className="text-base font-semibold text-gray-900">{title}</h3>
                {description && <p className="mt-1 text-xs text-gray-500">{description}</p>}
                <div className="mt-4">{children}</div>
                <div className="mt-6 flex justify-end gap-2">
                    <button type="button" onClick={onCancel} disabled={isLoading} className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50">Cancel</button>
                    <button type="button" onClick={onConfirm} disabled={confirmDisabled} className={`rounded-lg px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50 ${danger ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'}`}>{confirmLabel}</button>
                </div>
            </div>
        </div>
    );
}

export default function BackupPage() {
    const { user } = useAuth();
    const perms = user?.permissions || [];
    const canManage = perms.includes('backups.manage');
    const canRestore = perms.includes('backups.restore');
    const { addToast } = useToast();
    const queryClient = useQueryClient();

    const [dailyTime, setDailyTime] = useState<string>('');
    const [archivePassword, setArchivePassword] = useState('');
    const [archivePasswordConfirmation, setArchivePasswordConfirmation] = useState('');
    const [runTarget, setRunTarget] = useState<{ tier: BackupTier; scope: BackupScope } | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<{ tier: BackupTier; item: BackupItem } | null>(null);
    const [restoreTarget, setRestoreTarget] = useState<{ tier?: BackupTier; item?: BackupItem; archive?: File } | null>(null);
    const [restoreForm, setRestoreForm] = useState<{ scope: BackupScope; current_password: string; confirmation: string; acknowledged: boolean }>({ scope: 'both', current_password: '', confirmation: '', acknowledged: false });
    const archiveInput = useRef<HTMLInputElement | null>(null);

    const { data: payload, isLoading, isError, error, isFetching, refetch } = useQuery<BackupIndexData>({
        queryKey: ['backups'],
        queryFn: async () => (await getBackups()).data.data,
    });

    const dailyScheduleMutation = useMutation({
        mutationFn: (time: string) => updateBackupSettings({ daily_time: time }),
        onSuccess: () => {
            addToast('Daily backup schedule submitted for countersign approval.', 'success');
            void queryClient.invalidateQueries({ queryKey: ['backups'] });
        },
        onError: (err: any) => addToast(err?.response?.data?.message || 'Could not update daily schedule.', 'error'),
    });

    const archivePasswordMutation = useMutation({
        mutationFn: () => updateBackupSettings({
            archive_password: archivePassword,
            archive_password_confirmation: archivePasswordConfirmation,
        }),
        onSuccess: () => {
            addToast('Backup archive password update submitted for countersign approval.', 'success');
            setArchivePassword('');
            setArchivePasswordConfirmation('');
            void queryClient.invalidateQueries({ queryKey: ['backups'] });
        },
        onError: (err: any) => addToast(err?.response?.data?.message || 'Could not update archive password.', 'error'),
    });

    const runMutation = useMutation({
        mutationFn: ({ tier, scope }: { tier: BackupTier; scope: BackupScope }) => runBackup(tier, scope),
        onSuccess: (_data, vars) => {
            const label = vars.tier === 'daily' ? 'Daily backup started.' : 'Off-site replication started.';
            addToast(label, 'success');
            setRunTarget(null);
            void queryClient.invalidateQueries({ queryKey: ['backups'] });
        },
        onError: (err: any) => addToast(err?.response?.data?.message || 'Could not start backup.', 'error'),
    });

    const cleanupMutation = useMutation({
        mutationFn: (tier: BackupTier) => cleanupBackup(tier),
        onSuccess: (_data, tier) => {
            addToast(`${TIER_DEFS[tier].title} cleanup completed.`, 'success');
            void queryClient.invalidateQueries({ queryKey: ['backups'] });
        },
        onError: (err: any) => addToast(err?.response?.data?.message || 'Could not clean up archives.', 'error'),
    });

    const testOffsiteMutation = useMutation({
        mutationFn: () => testOffsiteStorage(),
        onSuccess: () => {
            addToast('Tested off-site storage connection.', 'success');
            void queryClient.invalidateQueries({ queryKey: ['backups'] });
        },
        onError: (err: any) => addToast(err?.response?.data?.message || 'Failed to connect to off-site storage.', 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: ({ tier, path }: { tier: BackupTier; path: string }) => deleteBackup(tier, path),
        onSuccess: () => {
            addToast('Backup deleted.', 'success');
            setDeleteTarget(null);
            void queryClient.invalidateQueries({ queryKey: ['backups'] });
        },
        onError: (err: any) => addToast(err?.response?.data?.message || 'Could not delete backup.', 'error'),
    });

    const restoreMutation = useMutation({
        mutationFn: () => {
            if (!restoreTarget) throw new Error('No restore target.');
            return restoreBackup({
                tier: restoreTarget.tier,
                path: restoreTarget.item?.path,
                archive: restoreTarget.archive,
                scope: restoreForm.scope,
                current_password: restoreForm.current_password,
                confirmation: restoreForm.confirmation,
            });
        },
        onSuccess: () => {
            addToast('Restore request submitted for chairman approval. System will go offline upon approval.', 'success');
            setRestoreTarget(null);
            setRestoreForm({ scope: 'both', current_password: '', confirmation: '', acknowledged: false });
            void queryClient.invalidateQueries({ queryKey: ['backups'] });
        },
        onError: (err: any) => addToast(err?.response?.data?.message || 'Restore request failed.', 'error'),
    });

    if (isLoading) return <LoadingIndicator message="Loading backups..." />;
    if (isError) return <div className="rounded-xl border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">Failed to load backups: {(error as any)?.message || 'Unknown error'}</div>;

    const settings = payload?.settings;
    const offsiteStatus = settings?.offsite ?? { configured: false, reachable: null, checked_at: null };
    const configuredDailyTime = settings?.daily_time || '03:00';

    const dailyItems = payload?.tiers?.daily?.items ?? [];
    const offsiteItems = payload?.tiers?.offsite?.items ?? [];

    const tierTotal = (items: BackupItem[]) =>
        items.reduce((sum, item) => sum + (item.size ?? 0), 0);
    const onServerTotal = tierTotal(dailyItems);
    const offsiteTotal = tierTotal(offsiteItems);
    const onServerCount = dailyItems.length;
    const offsiteCount = offsiteItems.length;
    const totalArchives = onServerCount + offsiteCount;

    const gfsConfig = settings?.tiers?.daily?.retention?.gfs ?? {
        daily_days: 14,
        weekly_weeks: 5,
        monthly_months: 12,
    };

    const localGfs = partitionGfsItems(dailyItems, gfsConfig.daily_days, gfsConfig.weekly_weeks);
    const offsiteGfs = partitionGfsItems(offsiteItems, gfsConfig.daily_days, gfsConfig.weekly_weeks);
    const offsiteUnconfigured = !offsiteStatus.configured;
    const recentRuns = payload?.recent_runs ?? [];

    return (
        <div className="space-y-6">
            <SettingsTabHeader
                eyebrow="Data integrity"
                title="Backup & Restore"
                description="Automatic encrypted backups run on schedule (GMT+8). Download archives, test the off-site connection, or restore when needed."
                icon="fas fa-hard-drive"
                helpText="Keep backups in a separate location and confirm schedules are running. Restoring replaces current data with the selected archive, so verify the archive and scope before starting a restore."
            />

            {/* Storage summary */}
            <section className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="grid grid-cols-1 divide-y divide-gray-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                    <div className="flex items-center gap-3 px-5 py-4">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i className="fas fa-server"></i></div>
                        <div className="min-w-0">
                            <p className="text-xs uppercase tracking-wide text-gray-500">On-server storage</p>
                            <p className="text-lg font-semibold tabular-nums text-gray-900">{formatBytes(onServerTotal)}</p>
                            <p className="text-xs text-gray-500">{onServerCount} archive{onServerCount === 1 ? '' : 's'} (Local)</p>
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
                            <p className="text-xs text-gray-500">Across local & off-site tiers</p>
                        </div>
                    </div>
                </div>
            </section>

            {/* Off-site connection banner */}
            <section className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <div className="flex min-w-0 items-center gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600"><i className="fas fa-cloud"></i></div>
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
                            {offsiteStatus.object_lock?.enabled ? (
                                <p className="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-emerald-900">
                                    <i className="fas fa-lock text-emerald-600"></i>
                                    <span>Object Lock Active ({offsiteStatus.object_lock.mode}, {offsiteStatus.object_lock.retention_days} days WORM immutability)</span>
                                </p>
                            ) : (
                                <p className="mt-1.5 text-xs text-emerald-700">
                                    <i className="fas fa-shield-alt text-emerald-600 mr-1"></i>
                                    Object Lock: Standard bucket WORM immutability supported
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            <p className="font-semibold flex items-center gap-2"><span className="h-2 w-2 rounded-full bg-red-500"></span> Off-site storage is unreachable</p>
                            <p className="mt-1 text-xs">Could not reach Backblaze B2. Check the .env credentials, bucket and endpoint, then use <strong>Test connection</strong>.{offsiteStatus.checked_at ? <> Last checked {formatDate(offsiteStatus.checked_at)}.</> : null}</p>
                        </div>
                    )}
                </div>
            </section>

            {/* SECTION 1: Local Backups (Full width) */}
            <section className="flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 bg-gradient-to-r from-emerald-50/40 via-white to-white px-5 py-4">
                    <div className="flex min-w-0 items-center gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                            <i className="fas fa-server"></i>
                        </div>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h3 className="font-semibold text-gray-900">Local Backups (On-Server)</h3>
                                <span className="rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                                    {retentionLabel('daily', payload)}
                                </span>
                            </div>
                            <p className="mt-0.5 text-xs text-gray-500">
                                Archives stored in <code className="rounded bg-gray-100 px-1">storage/app/backups</code>. Schedule (GMT+8): Daily (3:00 AM), Weekly (3:00 AM Monday), Monthly (3:00 AM 1st of month).
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2.5">
                        <div className="text-right mr-1">
                            <p className="text-sm font-semibold tabular-nums text-gray-900">{formatBytes(onServerTotal)}</p>
                            <p className="text-xs text-gray-500">{onServerCount} archive{onServerCount === 1 ? '' : 's'}</p>
                        </div>
                        {canManage && (
                            <>
                                <button
                                    type="button"
                                    onClick={() => setRunTarget({ tier: 'daily', scope: 'both' })}
                                    disabled={runMutation.isPending}
                                    className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                                    title="Run an on-demand daily backup immediately"
                                >
                                    <i className="fas fa-play mr-1"></i>Back Up Now (Daily)
                                </button>
                                <button
                                    type="button"
                                    onClick={() => cleanupMutation.mutate('daily')}
                                    disabled={cleanupMutation.isPending}
                                    className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                                    title="Delete archives older than the retention policy"
                                >
                                    <i className="fas fa-broom mr-1"></i>Clean
                                </button>
                            </>
                        )}
                        <button
                            type="button"
                            onClick={() => void refetch()}
                            disabled={isFetching}
                            className="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                            title="Refresh Local Backups"
                            aria-label="Refresh Local Backups"
                        >
                            <i className={`fas fa-rotate-right ${isFetching ? 'animate-spin' : ''}`}></i>
                        </button>
                    </div>
                </div>

                {/* 3 Containers for Local Backups */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50/50">
                    <ArchiveTierCard
                        title="Daily Archives"
                        badge={`${gfsConfig.daily_days} Days`}
                        badgeColor="bg-emerald-100 text-emerald-800"
                        scheduleInfo="Daily (3:00 AM GMT+8)"
                        icon="fas fa-calendar-day"
                        items={localGfs.daily}
                        tier="daily"
                        emptyMessage="No daily archives found."
                        canRestore={canRestore}
                        canManage={canManage}
                        onRestore={item => setRestoreTarget({ tier: 'daily', item })}
                        onDelete={item => setDeleteTarget({ tier: 'daily', item })}
                        onBackupNow={() => setRunTarget({ tier: 'daily', scope: 'both' })}
                    />
                    <ArchiveTierCard
                        title="Weekly Archives"
                        badge={`${gfsConfig.weekly_weeks} Weeks`}
                        badgeColor="bg-sky-100 text-sky-800"
                        scheduleInfo="Weekly (3:00 AM Monday GMT+8)"
                        icon="fas fa-calendar-week"
                        items={localGfs.weekly}
                        tier="daily"
                        emptyMessage="No weekly archives yet."
                        canRestore={canRestore}
                        canManage={canManage}
                        onRestore={item => setRestoreTarget({ tier: 'daily', item })}
                        onDelete={item => setDeleteTarget({ tier: 'daily', item })}
                    />
                    <ArchiveTierCard
                        title="Monthly Archives"
                        badge={`${gfsConfig.monthly_months} Months`}
                        badgeColor="bg-purple-100 text-purple-800"
                        scheduleInfo="Monthly (3:00 AM, 1st of month GMT+8)"
                        icon="fas fa-calendar"
                        items={localGfs.monthly}
                        tier="daily"
                        emptyMessage="No monthly archives yet."
                        canRestore={canRestore}
                        canManage={canManage}
                        onRestore={item => setRestoreTarget({ tier: 'daily', item })}
                        onDelete={item => setDeleteTarget({ tier: 'daily', item })}
                    />
                </div>
            </section>

            {/* SECTION 2: Off-Site Backups (Full width) */}
            <section className="flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 bg-gradient-to-r from-sky-50/40 via-white to-white px-5 py-4">
                    <div className="flex min-w-0 items-center gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                            <i className="fas fa-cloud"></i>
                        </div>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h3 className="font-semibold text-gray-900">Off-Site Backups (Backblaze B2)</h3>
                                <span className="rounded bg-sky-50 px-2 py-0.5 text-[11px] font-medium text-sky-700">
                                    {retentionLabel('offsite', payload)}
                                </span>
                                {offsiteStatus.object_lock?.enabled && (
                                    <span className="rounded bg-purple-50 px-2 py-0.5 text-[11px] font-medium text-purple-700 flex items-center gap-1">
                                        <i className="fas fa-lock text-[10px]"></i> Object Lock
                                    </span>
                                )}
                            </div>
                            <p className="mt-0.5 text-xs text-gray-500">
                                Remote S3 cloud replicas stored in Backblaze B2. Schedule (GMT+8): Daily (3:00 AM), Weekly (3:00 AM Monday), Monthly (3:00 AM 1st of month).
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2.5">
                        {!offsiteUnconfigured && (
                            <div className="text-right mr-1">
                                <p className="text-sm font-semibold tabular-nums text-gray-900">{formatBytes(offsiteTotal)}</p>
                                <p className="text-xs text-gray-500">{offsiteCount} archive{offsiteCount === 1 ? '' : 's'}</p>
                            </div>
                        )}
                        {canManage && !offsiteUnconfigured && (
                            <>
                                <button
                                    type="button"
                                    onClick={() => setRunTarget({ tier: 'offsite', scope: 'both' })}
                                    disabled={runMutation.isPending}
                                    className="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-sky-700 disabled:opacity-50"
                                    title="Replicate the latest daily archive to Backblaze B2"
                                >
                                    <i className="fas fa-arrows-rotate mr-1"></i>Replicate Daily to Off-Site
                                </button>
                                <button
                                    type="button"
                                    onClick={() => cleanupMutation.mutate('offsite')}
                                    disabled={cleanupMutation.isPending}
                                    className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                                    title="Delete archives older than the retention policy"
                                >
                                    <i className="fas fa-broom mr-1"></i>Clean
                                </button>
                            </>
                        )}
                        <button
                            type="button"
                            onClick={() => void refetch()}
                            disabled={isFetching}
                            className="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                            title="Refresh Off-Site Backups"
                            aria-label="Refresh Off-Site Backups"
                        >
                            <i className={`fas fa-rotate-right ${isFetching ? 'animate-spin' : ''}`}></i>
                        </button>
                    </div>
                </div>

                {offsiteUnconfigured ? (
                    <div className="p-8 text-center text-sm text-gray-500">
                        <i className="fas fa-cloud-upload-alt mb-2 text-3xl text-gray-300"></i>
                        <p className="font-semibold text-gray-700">Backblaze B2 is not configured</p>
                        <p className="mt-1 text-xs text-gray-500">
                            Set <code className="rounded bg-gray-100 px-1">AWS_ACCESS_KEY_ID</code>, <code className="rounded bg-gray-100 px-1">AWS_SECRET_ACCESS_KEY</code>, <code className="rounded bg-gray-100 px-1">AWS_DEFAULT_REGION</code>, <code className="rounded bg-gray-100 px-1">AWS_BUCKET</code> and <code className="rounded bg-gray-100 px-1">AWS_ENDPOINT</code> in <code className="rounded bg-gray-100 px-1">.env</code> to enable off-site backup replicas.
                        </p>
                    </div>
                ) : (
                    /* 3 Containers for Off-Site Backups */
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50/50">
                        <ArchiveTierCard
                            title="Daily Archives"
                            badge={`${gfsConfig.daily_days} Days`}
                            badgeColor="bg-emerald-100 text-emerald-800"
                            scheduleInfo="Daily (3:00 AM GMT+8)"
                            icon="fas fa-calendar-day"
                            items={offsiteGfs.daily}
                            tier="offsite"
                            emptyMessage="No daily off-site archives yet."
                            canRestore={canRestore}
                            canManage={canManage}
                            onRestore={item => setRestoreTarget({ tier: 'offsite', item })}
                            onDelete={item => setDeleteTarget({ tier: 'offsite', item })}
                        />
                        <ArchiveTierCard
                            title="Weekly Archives"
                            badge={`${gfsConfig.weekly_weeks} Weeks`}
                            badgeColor="bg-sky-100 text-sky-800"
                            scheduleInfo="Weekly (3:00 AM Monday GMT+8)"
                            icon="fas fa-calendar-week"
                            items={offsiteGfs.weekly}
                            tier="offsite"
                            emptyMessage="No weekly off-site archives yet."
                            canRestore={canRestore}
                            canManage={canManage}
                            onRestore={item => setRestoreTarget({ tier: 'offsite', item })}
                            onDelete={item => setDeleteTarget({ tier: 'offsite', item })}
                        />
                        <ArchiveTierCard
                            title="Monthly Archives"
                            badge={`${gfsConfig.monthly_months} Months`}
                            badgeColor="bg-purple-100 text-purple-800"
                            scheduleInfo="Monthly (3:00 AM, 1st of month GMT+8)"
                            icon="fas fa-calendar"
                            items={offsiteGfs.monthly}
                            tier="offsite"
                            emptyMessage="No monthly off-site archives yet."
                            canRestore={canRestore}
                            canManage={canManage}
                            onRestore={item => setRestoreTarget({ tier: 'offsite', item })}
                            onDelete={item => setDeleteTarget({ tier: 'offsite', item })}
                        />
                    </div>
                )}
            </section>

            {/* 3 Separate Column Sections: Schedule, Restoration, Encryption */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                {/* 1. Backup Schedules (GMT+8) */}
                <section className="flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div>
                        <div className="flex items-start gap-3">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                <i className="fas fa-clock"></i>
                            </div>
                            <div className="min-w-0">
                                <h3 className="font-semibold text-gray-900">Backup Schedules (GMT+8)</h3>
                                <p className="mt-1 text-xs text-gray-500">
                                    Automatic backups execute on a fixed schedule. Time changes apply to all tiers and require chairman countersign approval.
                                </p>
                            </div>
                        </div>

                        {/* Schedule Breakdown Box */}
                        <div className="mt-4 rounded-lg border border-gray-100 bg-gray-50/75 p-3 space-y-2 text-xs">
                            <div className="flex items-center justify-between">
                                <span className="font-medium text-gray-700 flex items-center gap-1.5">
                                    <i className="fas fa-calendar-day text-emerald-600"></i> Daily:
                                </span>
                                <span className="font-mono text-gray-900 font-semibold">Every day at 3:00 AM</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="font-medium text-gray-700 flex items-center gap-1.5">
                                    <i className="fas fa-calendar-week text-sky-600"></i> Weekly:
                                </span>
                                <span className="font-mono text-gray-900 font-semibold">Every Monday at 3:00 AM</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="font-medium text-gray-700 flex items-center gap-1.5">
                                    <i className="fas fa-calendar text-purple-600"></i> Monthly:
                                </span>
                                <span className="font-mono text-gray-900 font-semibold">1st of the month at 3:00 AM</span>
                            </div>
                        </div>

                        <div className="mt-4 flex items-end gap-2">
                            <label className="flex-1 text-sm text-gray-600">
                                <span className="mb-1 block text-xs font-semibold uppercase tracking-wide">Base Execution Time</span>
                                <input
                                    type="time"
                                    value={dailyTime || configuredDailyTime}
                                    onChange={event => setDailyTime(event.target.value)}
                                    disabled={!canManage}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-50"
                                />
                            </label>
                            {canManage && (
                                <button
                                    type="button"
                                    onClick={() => dailyScheduleMutation.mutate(dailyTime || configuredDailyTime)}
                                    disabled={dailyScheduleMutation.isPending}
                                    className="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    {dailyScheduleMutation.isPending ? 'Requesting...' : 'Save'}
                                </button>
                            )}
                        </div>
                    </div>
                    <p className="mt-4 text-xs text-gray-500 border-t border-gray-100 pt-3">
                        <i className="fas fa-info-circle mr-1 text-gray-400"></i>
                        &quot;Back Up Now&quot; creates a Daily backup only. Weekly and Monthly archives run on their fixed schedule.
                    </p>
                </section>

                {/* 2. Backup Restoration */}
                <section className="flex flex-col justify-between rounded-xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm">
                    <div>
                        <div className="flex items-start gap-3">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                                <i className="fas fa-upload"></i>
                            </div>
                            <div className="min-w-0">
                                <h3 className="font-semibold text-amber-950">Restore Backup</h3>
                                <p className="mt-1 text-xs text-amber-800">
                                    Upload an external or downloaded archive (.zip) to restore it with system-wide verification checks.
                                </p>
                            </div>
                        </div>
                        <div className="mt-4">
                            {canRestore ? (
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
                                    <button
                                        type="button"
                                        onClick={() => archiveInput.current?.click()}
                                        className="w-full rounded-lg border border-amber-300 bg-white px-3 py-2.5 text-sm font-medium text-amber-900 shadow-2xs hover:bg-amber-100 transition"
                                    >
                                        <i className="fas fa-upload mr-2 text-amber-700"></i>Upload & Restore Backup
                                    </button>
                                </>
                            ) : (
                                <div className="rounded-lg bg-amber-100/50 px-3 py-2 text-xs text-amber-800">
                                    You do not have permission to restore backups.
                                </div>
                            )}
                        </div>
                    </div>
                    <p className="mt-4 text-[11px] text-amber-700 border-t border-amber-200/60 pt-3">
                        <i className="fas fa-shield-halved mr-1"></i>Requires chairman countersign and offline maintenance mode.
                    </p>
                </section>

                {/* 3. Backup Encryption */}
                <section className="flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div>
                        <div className="flex items-start gap-3">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                                <i className="fas fa-lock"></i>
                            </div>
                            <div className="min-w-0 flex-1">
                                <h3 className="font-semibold text-gray-900">Backup Encryption</h3>
                                <p className="mt-1 text-xs text-gray-500">
                                    Backups use AES-256 encryption. The archive password is encrypted in the database and never displayed.
                                </p>
                            </div>
                        </div>
                        {canManage && (
                            <div className="mt-4 space-y-2">
                                <input
                                    type="password"
                                    value={archivePassword}
                                    onChange={event => setArchivePassword(event.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm"
                                    placeholder="New archive password"
                                    autoComplete="new-password"
                                />
                                <input
                                    type="password"
                                    value={archivePasswordConfirmation}
                                    onChange={event => setArchivePasswordConfirmation(event.target.value)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm"
                                    placeholder="Confirm archive password"
                                    autoComplete="new-password"
                                />
                                <button
                                    type="button"
                                    onClick={() => archivePasswordMutation.mutate()}
                                    disabled={archivePasswordMutation.isPending || archivePassword.length < 8 || archivePassword !== archivePasswordConfirmation}
                                    className="w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    {archivePasswordMutation.isPending ? 'Requesting...' : 'Request Password Change'}
                                </button>
                            </div>
                        )}
                    </div>
                    <p className="mt-4 text-[11px] text-gray-500 border-t border-gray-100 pt-3">
                        Password must be at least 8 chars with upper, lower, number, and symbol.
                    </p>
                </section>
            </div>

            {/* Recent Activity */}
            <section className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div className="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 bg-gray-50/50">
                    <div>
                        <h3 className="font-semibold text-gray-900">Recent Activity</h3>
                        <p className="mt-0.5 text-xs text-gray-500">The most recent backup runs across all tiers.</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => void refetch()}
                        disabled={isFetching}
                        className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                        title="Refresh recent activity"
                        aria-label="Refresh recent activity"
                    >
                        <i className={`fas fa-rotate-right mr-1.5 ${isFetching ? 'animate-spin' : ''}`}></i>Refresh
                    </button>
                </div>
                <div className="divide-y divide-gray-100">
                    {recentRuns.length === 0 ? (
                        <p className="px-5 py-6 text-center text-sm text-gray-400">No backup activity yet.</p>
                    ) : (
                        recentRuns.map(run => (
                            <div key={run.id} className="flex items-center justify-between gap-3 px-5 py-3 hover:bg-gray-50/50 transition">
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-gray-800 capitalize">{TIER_DEFS[run.tier]?.title ?? run.tier} · {SCOPE_LABELS[run.scope]}</p>
                                    <p className="mt-0.5 truncate text-xs text-gray-500">{run.filename || (run.status === 'failed' ? (run.message || 'Failed') : 'Pending')} · <span className="tabular-nums font-mono">{formatBytes(run.size)}</span> · {formatDate(run.started_at)}</p>
                                </div>
                                <StatusBadge status={run.status} />
                            </div>
                        ))
                    )}
                </div>
            </section>

            {/* Run Backup Modal */}
            <ConfirmModal
                open={!!runTarget}
                title={runTarget?.tier === 'daily' ? 'Run Daily Backup Now' : 'Replicate Daily Archive to Off-Site'}
                description={
                    runTarget?.tier === 'daily'
                        ? 'Trigger an immediate on-demand daily backup. (Weekly backups run Mondays at 3:00 AM GMT+8; Monthly backups run 1st of month at 3:00 AM GMT+8).'
                        : 'Replicate the latest verified daily archive to Backblaze B2 off-site cloud storage.'
                }
                onCancel={() => setRunTarget(null)}
                onConfirm={() => runTarget && runMutation.mutate(runTarget)}
                confirmLabel={runTarget?.tier === 'daily' ? 'Start Daily Backup' : 'Replicate Now'}
            >
                {runTarget?.tier === 'daily' ? (
                    <>
                        <div className="flex gap-2">
                            {(['db', 'files', 'both'] as const).map(scope => (
                                <button
                                    key={scope}
                                    type="button"
                                    onClick={() => setRunTarget(t => t ? { ...t, scope } : t)}
                                    className={`flex-1 rounded-lg px-3 py-2 text-sm font-medium ${runTarget?.scope === scope ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
                                >
                                    {SCOPE_LABELS[scope]}
                                </button>
                            ))}
                        </div>
                        <p className="mt-3 text-xs text-gray-500">
                            On-demand backup runs immediately for the Daily tier. Scheduled weekly and monthly backups are not altered.
                        </p>
                    </>
                ) : (
                    <p className="text-sm text-gray-600">
                        This queues an immediate copy of your newest local daily backup archive to Backblaze B2 with WORM immutability if enabled.
                    </p>
                )}
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
                isLoading={restoreMutation.isPending}
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
