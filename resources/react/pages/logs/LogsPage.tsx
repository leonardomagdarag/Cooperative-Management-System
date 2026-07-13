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
        <>
            <div className="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
                <div>
                    <h1 className="text-xl font-semibold text-gray-900 sm:text-2xl">Activity Logs</h1>
                    <p className="text-sm text-gray-500">Track system events and user actions</p>
                </div>
            </div>

            <div className="p-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <div className="col-span-1 sm:col-span-2">
                        <input
                            type="text"
                            placeholder="Search logs..."
                            value={search}
                            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                            className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        />
                    </div>
                    <select value={tag} onChange={e => { setTag(e.target.value); setPage(1); }} className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        <option value="all">All Tags</option>
                        {filters?.tags?.map((t: string) => <option key={t} value={t}>{t}</option>)}
                    </select>
                    <select value={event} onChange={e => { setEvent(e.target.value); setPage(1); }} className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        <option value="all">All Events</option>
                        {filters?.events?.map((ev: string) => <option key={ev} value={ev}>{ev}</option>)}
                    </select>
                </div>

                {/* Table */}
                {isLoading ? (
                    <div className="space-y-3">
                        {[...Array(5)].map((_, i) => (
                            <div key={i} className="h-14 animate-pulse rounded-lg bg-gray-100" />
                        ))}
                    </div>
                ) : logs.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center">
                        <i className="fas fa-file-alt text-4xl text-gray-300"></i>
                        <p className="mt-4 text-sm text-gray-500">No logs found</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                        <table className="min-w-full divide-y divide-gray-200 table-fixed">
                            <thead className="bg-gray-100">
                                <tr>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Date</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">User</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Event</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Description</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {logs.map(log => (
                                    <tr key={log.id} className="hover:bg-gray-50">
                                        <td className="p-4 text-sm text-gray-500 whitespace-nowrap">
                                            {new Date(log.created_at).toLocaleString()}
                                        </td>
                                        <td className="p-4 text-sm font-medium text-gray-900">
                                            {log.causer?.name || 'System'}
                                        </td>
                                        <td className="p-4">
                                            <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${getEventColor(log.event)}`}>
                                                {log.event}
                                            </span>
                                        </td>
                                        <td className="p-4 text-sm text-gray-500 max-w-[300px] truncate">{log.description}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {pagination && pagination.last_page > 1 && (
                    <div className="sticky bottom-0 bg-white border-t border-gray-200 p-4 flex items-center justify-center gap-2">
                        <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm hover:bg-gray-50 disabled:opacity-50">Previous</button>
                        <span className="text-sm text-gray-500">Page {page} of {pagination.last_page}</span>
                        <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm hover:bg-gray-50 disabled:opacity-50">Next</button>
                    </div>
                )}
            </div>
        </>
    );
}
