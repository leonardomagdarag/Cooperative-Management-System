import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getLogs, LogEntry } from '../../api/logs';
import EmptyState from '../../components/ui/EmptyState';
import Badge from '../../components/ui/Badge';
import Pagination from '../../components/ui/Pagination';

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
                    <EmptyState icon="fa-file-alt" title="No logs found" />
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
                                        <td className="p-4 text-sm font-medium text-gray-900 truncate" title={log.causer?.name || 'System'}>
                                            {log.causer?.name || 'System'}
                                        </td>
                                        <td className="p-4">
                                            <Badge variant={log.event === 'created' ? 'success' : log.event === 'updated' ? 'info' : log.event === 'deleted' ? 'danger' : 'neutral'}>{log.event}</Badge>
                                        </td>
                                        <td className="p-4 text-sm text-gray-500 max-w-[300px] truncate">{log.description}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {pagination && pagination.last_page > 1 && (
                    <Pagination page={page} lastPage={pagination.last_page} onChange={setPage} />
                )}
            </div>
        </>
    );
}
