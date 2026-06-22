import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getNotifications, markAsRead, markAllAsRead, Notification } from '../../api/notifications';

export default function NotificationsPage() {
    const [statusFilter, setStatusFilter] = useState('all');
    const [categoryFilter, setCategoryFilter] = useState('all');
    const [page, setPage] = useState(1);
    const [selected, setSelected] = useState<Notification | null>(null);
    const queryClient = useQueryClient();

    const { data, isLoading } = useQuery({
        queryKey: ['notifications', statusFilter, categoryFilter, page],
        queryFn: () => getNotifications({
            status: statusFilter === 'all' ? undefined : statusFilter,
            category: categoryFilter === 'all' ? undefined : categoryFilter,
            page,
            per_page: 15,
        }),
    });

    const markReadMutation = useMutation({
        mutationFn: markAsRead,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['notifications'] });
            queryClient.invalidateQueries({ queryKey: ['notifications-unread-count'] });
            setSelected(null);
        },
    });

    const markAllMutation = useMutation({
        mutationFn: markAllAsRead,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['notifications'] });
            queryClient.invalidateQueries({ queryKey: ['notifications-unread-count'] });
        },
    });

    const notifications: Notification[] = data?.data?.notifications ?? [];
    const pagination = data?.data?.pagination;

    const getCategoryColor = (type: string) => {
        if (type === 'security_alert') return 'bg-red-100 text-red-700';
        if (type === 'loan_due') return 'bg-amber-100 text-amber-700';
        if (type === 'announcement') return 'bg-blue-100 text-blue-700';
        return 'bg-slate-100 text-slate-700';
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Notifications</h1>
                <button
                    onClick={() => markAllMutation.mutate()}
                    className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                >
                    Mark All Read
                </button>
            </div>

            {/* Filters */}
            <div className="flex flex-wrap gap-3">
                <select
                    value={statusFilter}
                    onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800"
                >
                    <option value="all">All Status</option>
                    <option value="unread">Unread</option>
                    <option value="read">Read</option>
                </select>
                <select
                    value={categoryFilter}
                    onChange={(e) => { setCategoryFilter(e.target.value); setPage(1); }}
                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800"
                >
                    <option value="all">All Categories</option>
                    <option value="security">Security</option>
                    <option value="billing">Billing</option>
                    <option value="activity">Activity</option>
                    <option value="announcements">Announcements</option>
                </select>
            </div>

            {/* Notification List */}
            {isLoading ? (
                <div className="space-y-3">
                    {[...Array(5)].map((_, i) => (
                        <div key={i} className="h-20 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    ))}
                </div>
            ) : notifications.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-600 dark:bg-slate-800">
                    <i className="fas fa-bell-slash text-4xl text-slate-300 dark:text-slate-600"></i>
                    <p className="mt-4 text-sm text-slate-500">No notifications found</p>
                </div>
            ) : (
                <div className="space-y-2">
                    {notifications.map(n => (
                        <button
                            key={n.id}
                            onClick={() => {
                                setSelected(n);
                                if (!n.is_read) markReadMutation.mutate(n.id);
                            }}
                            className={`w-full rounded-lg border p-4 text-left transition-all hover:shadow-md ${
                                n.is_read
                                    ? 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800'
                                    : 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20'
                            }`}
                        >
                            <div className="flex items-start gap-3">
                                <div className={`mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${
                                    n.is_read ? 'bg-slate-100 dark:bg-slate-700' : 'bg-emerald-100 dark:bg-emerald-900'
                                }`}>
                                    <i className={`fas ${n.icon || 'fa-bell'} text-sm ${
                                        n.is_read ? 'text-slate-400' : 'text-emerald-600'
                                    }`}></i>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium text-slate-900 dark:text-white">{n.title}</p>
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${getCategoryColor(n.type)}`}>
                                            {n.type.replace('_', ' ')}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-sm text-slate-500 line-clamp-2">{n.message}</p>
                                    <p className="mt-1 text-xs text-slate-400">{n.time_ago}</p>
                                </div>
                            </div>
                        </button>
                    ))}
                </div>
            )}

            {/* Pagination */}
            {pagination && pagination.last_page > 1 && (
                <div className="flex items-center justify-center gap-2">
                    <button
                        onClick={() => setPage(p => Math.max(1, p - 1))}
                        disabled={page === 1}
                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:hover:bg-slate-700"
                    >
                        Previous
                    </button>
                    <span className="text-sm text-slate-500">Page {page} of {pagination.last_page}</span>
                    <button
                        onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))}
                        disabled={page === pagination.last_page}
                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:hover:bg-slate-700"
                    >
                        Next
                    </button>
                </div>
            )}

            {/* Detail Modal */}
            {selected && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setSelected(null)}>
                    <div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800" onClick={e => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-bold text-slate-900 dark:text-white">{selected.title}</h2>
                            <button onClick={() => setSelected(null)} className="text-slate-400 hover:text-slate-600">
                                <i className="fas fa-times"></i>
                            </button>
                        </div>
                        <p className="text-sm text-slate-500">{selected.message}</p>
                        <p className="mt-4 text-xs text-slate-400">{selected.time_ago}</p>
                    </div>
                </div>
            )}
        </div>
    );
}
