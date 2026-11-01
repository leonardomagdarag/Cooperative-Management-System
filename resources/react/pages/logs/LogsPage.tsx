import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { exportLogs, getLogs, LogEntry } from '../../api/logs';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import EmptyState from '../../components/ui/EmptyState';
import Pagination from '../../components/ui/Pagination';
import Modal from '../../components/ui/Modal';
import Button from '../../components/ui/Button';

type Intensity = 'routine' | 'important' | 'sensitive';

const areaLabels: Record<string, string> = {
    announcement: 'Announcements', auth: 'Sign in & security', backup: 'Backups', branch: 'Branches',
    collection_run: 'Collections', expense: 'Expenses', loan: 'Loans', member: 'Members',
    notification: 'Notifications', officer: 'Officers', receipt: 'Receipts', settings: 'Settings',
    system: 'System', system_setting: 'Settings', ticket: 'Support', transaction: 'Transactions',
};

function readableLabel(value?: string | null): string {
    if (!value) return 'Activity';
    return value
        .replace(/[._-]+/g, ' ')
        .replace(/\b\w/g, letter => letter.toUpperCase());
}

function areaLabel(area: string): string {
    return areaLabels[area] ?? readableLabel(area);
}

function intensityFor(event?: string | null): Intensity {
    const action = (event ?? '').toLowerCase();
    if (/delete|revers|restore|permission|role\.|otp|security|rejected|locked/.test(action)) return 'sensitive';
    if (/create|update|approve|disburs|close|assign|status|submit|withdraw|payment|publish|toggle/.test(action)) return 'important';
    return 'routine';
}

const intensityStyles: Record<Intensity, { label: string; icon: string; className: string; rail: string }> = {
    routine: { label: 'Routine', icon: 'fa-circle-info', className: 'bg-sky-50 text-sky-700 ring-sky-200', rail: 'border-l-sky-400' },
    important: { label: 'Important', icon: 'fa-bolt', className: 'bg-amber-50 text-amber-800 ring-amber-200', rail: 'border-l-amber-400' },
    sensitive: { label: 'Sensitive', icon: 'fa-shield-halved', className: 'bg-rose-50 text-rose-700 ring-rose-200', rail: 'border-l-rose-500' },
};

function displayValue(value: unknown) {
    if (value === null || value === undefined || value === '') return 'Not provided';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (typeof value === 'object') return value;
    return String(value);
}

const detailLabels: Record<string, string> = {
    id: 'Record number', user_id: 'User number', member_id: 'Member number', loan_id: 'Loan number',
    transaction_id: 'Transaction number', branch_id: 'Branch number', amount: 'Amount',
    old: 'Previous information', new: 'Updated information', attributes: 'Information saved',
    old_values: 'Previous information', new_values: 'Updated information', reason: 'Reason',
    status: 'Status', action: 'Action', delivery_method: 'How funds were delivered',
    approved_by: 'Approved by', rejected_by: 'Reviewed by',
};

function detailLabel(key: string): string {
    return detailLabels[key.toLowerCase()] ?? readableLabel(key);
}

const technicalRequestDetails = new Set(['path', 'route', 'method']);

function PropertyDetails({ value, depth = 0 }: { value: unknown; depth?: number }) {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        const entries = Object.entries(value as Record<string, unknown>)
            .filter(([key]) => !technicalRequestDetails.has(key.toLowerCase()));
        if (entries.length) return <dl className={depth ? 'mt-2 space-y-2 border-l-2 border-slate-200 pl-3' : 'divide-y divide-slate-100'}>
            {entries.map(([key, child]) => <div key={key} className="py-2 first:pt-0 last:pb-0">
                <dt className="text-xs font-semibold text-slate-600">{detailLabel(key)}</dt>
                <dd className="mt-0.5 break-words text-sm text-slate-800"><PropertyDetails value={child} depth={depth + 1} /></dd>
            </div>)}
        </dl>;
        return 'None';
    }
    if (Array.isArray(value)) return value.length ? <ul className="mt-1 list-inside list-disc space-y-1">{value.map((item, index) => <li key={index}><PropertyDetails value={item} depth={depth + 1} /></li>)}</ul> : 'None';
    return <>{displayValue(value) as string}</>;
}

export default function LogsPage() {
    const { user } = useAuth();
    const { addToast } = useToast();
    const [search, setSearch] = useState('');
    const [area, setArea] = useState('all');
    const [event, setEvent] = useState('all');
    const [userId, setUserId] = useState('all');
    const [dateRange, setDateRange] = useState('all');
    const [page, setPage] = useState(1);
    const [selectedLog, setSelectedLog] = useState<LogEntry | null>(null);
    const [isExporting, setIsExporting] = useState(false);
    const canExport = user?.permissions?.includes('logs.export') ?? false;

    const { data, isLoading } = useQuery({
        queryKey: ['logs', search, area, event, userId, dateRange, page],
        queryFn: () => getLogs({
            search: search || undefined,
            tag: area === 'all' ? undefined : area,
            event: event === 'all' ? undefined : event,
            user_id: userId === 'all' ? undefined : Number(userId),
            date_range: dateRange === 'all' ? undefined : dateRange,
            page,
            per_page: 15,
        }),
    });

    const logs: LogEntry[] = data?.data?.data ?? [];
    const pagination = data?.data?.pagination;
    const filters = data?.data?.filters;
    const updateFilter = (setter: (value: string) => void) => (value: string) => {
        setter(value);
        setPage(1);
    };

    const handleExport = async () => {
        setIsExporting(true);
        try {
            const response = await exportLogs({
                search: search || undefined,
                tag: area === 'all' ? undefined : area,
                event: event === 'all' ? undefined : event,
                user_id: userId === 'all' ? undefined : Number(userId),
                date_range: dateRange === 'all' ? undefined : dateRange,
            });
            const disposition = response.headers['content-disposition'] || '';
            const filename = disposition.match(/filename="?([^";]+)"?/i)?.[1]
                ?? `audit-logs-${new Date().toISOString().slice(0, 10)}.csv`;
            const url = URL.createObjectURL(response.data);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
            addToast('Audit log export downloaded', 'success');
        } catch (error: any) {
            let message = error.response?.data?.message || 'Audit log export failed';
            if (error.response?.data instanceof Blob) {
                try {
                    const payload = JSON.parse(await error.response.data.text());
                    message = payload.message || message;
                } catch { /* Keep the fallback message. */ }
            }
            addToast(message, 'error');
        } finally {
            setIsExporting(false);
        }
    };

    return (
        <>
            <header className="border-b border-slate-200 bg-white px-4 py-5 sm:px-6">
                <div className="mx-auto flex max-w-7xl items-start gap-3">
                    <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <i className="fas fa-clock-rotate-left text-lg" aria-hidden="true" />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold text-slate-900 sm:text-2xl">Activity history</h1>
                        <p className="mt-1 text-sm text-slate-500">A readable record of actions taken across the cooperative system.</p>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-7xl space-y-5 p-4 sm:p-6">
                <section aria-label="Action intensity guide" className="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-500">Action intensity</span>
                    {(Object.keys(intensityStyles) as Intensity[]).map(level => {
                        const style = intensityStyles[level];
                        return <span key={level} className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${style.className}`}><i className={`fas ${style.icon}`} aria-hidden="true" />{style.label}</span>;
                    })}
                    <span className="text-xs text-slate-500">Color reflects the action type, not its success or failure.</span>
                </section>

                <section aria-label="Activity filters" className="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
                    <label className="relative sm:col-span-2 lg:col-span-1">
                        <span className="sr-only">Search activity</span>
                        <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input type="search" placeholder="Search activity" value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} className="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                    </label>
                    <label>
                        <span className="sr-only">Filter by area</span>
                        <select value={area} onChange={e => updateFilter(setArea)(e.target.value)} className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                            <option value="all">All areas</option>
                            {filters?.tags?.map((tag: string) => <option key={tag} value={tag}>{areaLabel(tag)}</option>)}
                        </select>
                    </label>
                    <label>
                        <span className="sr-only">Filter by action</span>
                        <select value={event} onChange={e => updateFilter(setEvent)(e.target.value)} className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                            <option value="all">All actions</option>
                            {filters?.events?.map((value: string) => <option key={value} value={value}>{readableLabel(value)}</option>)}
                        </select>
                    </label>
                    <label>
                        <span className="sr-only">Filter by user</span>
                        <select value={userId} onChange={e => updateFilter(setUserId)(e.target.value)} className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                            <option value="all">All users</option>
                            {filters?.users?.map((user: { id: number; name: string }) => <option key={user.id} value={user.id}>{user.name}</option>)}
                        </select>
                    </label>
                    <label>
                        <span className="sr-only">Filter by date</span>
                        <select value={dateRange} onChange={e => updateFilter(setDateRange)(e.target.value)} className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                            <option value="all">Any date</option>
                            <option value="today">Today</option>
                            <option value="week">This week</option>
                            <option value="month">This month</option>
                        </select>
                    </label>
                </section>

                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-sm font-semibold text-slate-700">Recent activity</h2>
                    <div className="flex items-center gap-3">
                        {pagination && <span className="text-xs text-slate-500">{pagination.total.toLocaleString()} records</span>}
                        {canExport && <Button size="sm" variant="secondary" onClick={handleExport} disabled={isExporting}>
                            <i className={`fas ${isExporting ? 'fa-spinner fa-spin' : 'fa-download'} mr-1.5`} aria-hidden="true" />
                            {isExporting ? 'Exporting…' : 'Export CSV'}
                        </Button>}
                    </div>
                </div>

                {isLoading ? (
                    <div className="space-y-3" aria-label="Loading activity">
                        {[...Array(5)].map((_, index) => <div key={index} className="h-28 animate-pulse rounded-xl border border-slate-100 bg-white" />)}
                    </div>
                ) : logs.length === 0 ? (
                    <div className="rounded-xl border border-slate-200 bg-white p-6"><EmptyState icon="fa-file-lines" title="No activity found" /></div>
                ) : (
                    <div className="space-y-2">
                        {logs.map(log => {
                            const intensity = intensityStyles[intensityFor(log.event)];
                            const date = new Date(log.created_at);
                            return (
                                <article key={log.id} className={`rounded-xl border border-slate-200 border-l-4 ${intensity.rail} bg-white p-3 shadow-sm sm:p-4`}>
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset ${intensity.className}`}><i className={`fas ${intensity.icon}`} aria-hidden="true" />{intensity.label}</span>
                                                <span className="text-xs font-medium text-slate-500">{areaLabel(log.log_name)}</span>
                                                <span className="text-slate-300" aria-hidden="true">•</span>
                                                <span className="text-xs text-slate-500">{readableLabel(log.event)}</span>
                                            </div>
                                            <p className="mt-1 text-sm font-semibold leading-5 text-slate-900">{log.description || readableLabel(log.event)}</p>
                                            {(log.subject_name || log.subject_type) && <p className="mt-0.5 text-xs text-slate-600">{log.subject_name || `${readableLabel(log.subject_type)}${log.subject_id ? ` #${log.subject_id}` : ''}`}</p>}
                                        </div>
                                        <Button size="sm" variant="secondary" onClick={() => setSelectedLog(log)} className="shrink-0">View details</Button>
                                    </div>
                                    <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-slate-100 pt-2 text-xs text-slate-500">
                                        <span className="inline-flex items-center gap-1.5"><i className="fas fa-user text-slate-400" aria-hidden="true" />{log.causer?.name || 'System'}</span>
                                        <span className="inline-flex items-center gap-1.5"><i className="fas fa-calendar text-slate-400" aria-hidden="true" />{date.toLocaleDateString()}</span>
                                        <span className="inline-flex items-center gap-1.5"><i className="fas fa-clock text-slate-400" aria-hidden="true" />{date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}</span>
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                )}

                {pagination && pagination.last_page > 1 && <Pagination page={page} lastPage={pagination.last_page} onChange={setPage} total={pagination.total} perPage={pagination.per_page} />}
            </main>

            <Modal open={!!selectedLog} onClose={() => setSelectedLog(null)} title="Activity details" size="xl">
                {selectedLog && (() => {
                    const intensity = intensityStyles[intensityFor(selectedLog.event)];
                    const properties = selectedLog.properties && typeof selectedLog.properties === 'object' ? selectedLog.properties as Record<string, unknown> : {};
                    const permission = properties.permission ?? properties.permissions;
                    return <div className="space-y-5">
                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${intensity.className}`}><i className={`fas ${intensity.icon}`} aria-hidden="true" />{intensity.label} action</span>
                                <span className="text-xs font-medium text-slate-500">{areaLabel(selectedLog.log_name)} · {readableLabel(selectedLog.event)}</span>
                            </div>
                            <p className="mt-3 text-base font-semibold text-slate-900">{selectedLog.description}</p>
                        </div>
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <Detail label="Performed by" value={selectedLog.causer?.name || 'System'} hint={selectedLog.causer?.email} />
                            <Detail label="Date and time" value={new Date(selectedLog.created_at).toLocaleString()} />
                            <Detail label="Area" value={areaLabel(selectedLog.log_name)} />
                            <Detail label="Related record" value={selectedLog.subject_name || (selectedLog.subject_type ? `${readableLabel(selectedLog.subject_type)}${selectedLog.subject_id ? ` #${selectedLog.subject_id}` : ''}` : 'No related record')} />
                        </dl>
                        <section>
                            <h3 className="text-sm font-semibold text-slate-800">Permission accessed</h3>
                            <p className="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                {permission ? <PropertyDetails value={permission} /> : 'The permission used for this action was not recorded.'}
                            </p>
                        </section>
                        <section>
                            <h3 className="text-sm font-semibold text-slate-800">What changed or was recorded?</h3>
                            {Object.keys(properties).length === 0 ? <p className="mt-2 rounded-lg bg-slate-50 p-4 text-sm text-slate-500">No extra details were saved for this action.</p> : (
                                <div className="mt-2 rounded-lg border border-slate-200 px-4 py-3"><PropertyDetails value={properties} /></div>
                            )}
                        </section>
                    </div>;
                })()}
            </Modal>
        </>
    );
}

function Detail({ label, value, hint }: { label: string; value: string; hint?: string }) {
    return <div>
        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</dt>
        <dd className="mt-1 break-words text-sm font-medium text-slate-900">{value}</dd>
        {hint && <dd className="mt-0.5 break-words text-xs text-slate-500">{hint}</dd>}
    </div>;
}
