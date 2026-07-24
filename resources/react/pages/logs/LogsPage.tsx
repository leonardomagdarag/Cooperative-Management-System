import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getLogs, LogEntry } from '../../api/logs';

export default function LogsPage() {
    const [search, setSearch] = useState('');
    const [tag, setTag] = useState('all');
    const [event, setEvent] = useState('all');
    const [dateRange, setDateRange] = useState('all');
    const [page, setPage] = useState(1);

    const { data, isLoading } = useQuery({
        queryKey: ['logs', search, tag, event, dateRange, page],
        queryFn: () => getLogs({
            search: search || undefined,
            tag: tag === 'all' ? undefined : tag,
            event: event === 'all' ? undefined : event,
            date_range: dateRange === 'all' ? undefined : dateRange,
            page,
            per_page: 15,
        }),
    });

    const logs: LogEntry[] = data?.data?.data ?? [];
    const pagination = data?.data?.pagination;
    const filters = data?.data?.filters;

    const getEventColor = (e: string) => {
        if (e === 'created') return 'bg-emerald-100 text-emerald-700';
        if (e === 'updated') return 'bg-blue-100 text-blue-700';
        if (e === 'deleted') return 'bg-red-100 text-red-700';
        return 'bg-slate-100 text-slate-700';
    };

    return (
        <div className="space-y-6">
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Audit Logs</h1>

            {/* Filters */}
            <div className="flex flex-wrap gap-3">
                <input
                    type="text"
                    placeholder="Search logs..."
                    value={search}
                    onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800"
                />
                <select value={tag} onChange={e => { setTag(e.target.value); setPage(1); }} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800">
                    <option value="all">All Tags</option>
                    {filters?.tags?.map((t: string) => <option key={t} value={t}>{t}</option>)}
                </select>
                <select value={event} onChange={e => { setEvent(e.target.value); setPage(1); }} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800">
                    <option value="all">All Events</option>
                    {filters?.events?.map((ev: string) => <option key={ev} value={ev}>{ev}</option>)}
                </select>
                <select value={dateRange} onChange={e => { setDateRange(e.target.value); setPage(1); }} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800">
                    <option value="all">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                </select>
            </div>

            {/* Table */}
            {isLoading ? (
                <div className="space-y-3">
                    {[...Array(5)].map((_, i) => (
                        <div key={i} className="h-14 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    ))}
                </div>
            ) : logs.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-600 dark:bg-slate-800">
                    <i className="fas fa-file-alt text-4xl text-slate-300 dark:text-slate-600"></i>
                    <p className="mt-4 text-sm text-slate-500">No logs found</p>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                            <tr>
                                <th className="px-4 py-3 font-medium text-slate-500">Time</th>
                                <th className="px-4 py-3 font-medium text-slate-500">Causer</th>
                                <th className="px-4 py-3 font-medium text-slate-500">Event</th>
                                <th className="px-4 py-3 font-medium text-slate-500">Description</th>
                                <th className="px-4 py-3 font-medium text-slate-500">Tag</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                            {logs.map(log => (
                                <tr key={log.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <td className="px-4 py-3 text-slate-500 whitespace-nowrap">
                                        {new Date(log.created_at).toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                        {log.causer?.name || 'System'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${getEventColor(log.event)}`}>
                                            {log.event}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-500 max-w-[300px] truncate">{log.description}</td>
                                    <td className="px-4 py-3 text-slate-500">{log.log_name || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {pagination && pagination.last_page > 1 && (
                <div className="flex items-center justify-center gap-2">
                    <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:hover:bg-slate-700">Previous</button>
                    <span className="text-sm text-slate-500">Page {page} of {pagination.last_page}</span>
                    <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:hover:bg-slate-700">Next</button>
                </div>
            )}
        </div>
    );
}
