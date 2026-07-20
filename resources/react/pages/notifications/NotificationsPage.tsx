import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getNotifications, markAsRead, markAllAsRead, deleteNotification, deleteAllReadNotifications, Notification } from '../../api/notifications';

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

    const deleteMutation = useMutation({
        mutationFn: deleteNotification,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['notifications'] });
            queryClient.invalidateQueries({ queryKey: ['notifications-unread-count'] });
            setSelected(null);
        },
    });

    const deleteAllReadMutation = useMutation({
        mutationFn: deleteAllReadNotifications,
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
        <div className="max-w-7xl mx-auto p-6">
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-3xl font-bold text-gray-900">Notifications</h1>
                    <p className="text-sm text-gray-500">Stay on top of your alerts and updates</p>
                </div>
                <button
                    onClick={() => markAllMutation.mutate()}
                    className="bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg px-4 py-2 text-sm font-medium"
                >
                    Mark All Read
                </button>
                <button
                    onClick={() => { if (confirm('Delete all read notifications?')) deleteAllReadMutation.mutate(); }}
                    className="text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg px-4 py-2 text-sm font-medium"
                >
                    Clear Read
                </button>
            </div>

            {/* Filters */}
            <div className="flex flex-wrap gap-3 mb-4">
                <select
                    value={statusFilter}
                    onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
                    className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5"
                >
                    <option value="all">All Status</option>
                    <option value="unread">Unread</option>
                    <option value="read">Read</option>
                </select>
                <select
                    value={categoryFilter}
                    onChange={(e) => { setCategoryFilter(e.target.value); setPage(1); }}
                    className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5"
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
                        <div key={i} className="h-20 animate-pulse rounded-lg bg-gray-100" />
                    ))}
                </div>
            ) : notifications.length === 0 ? (
                <div className="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center">
                    <i className="fas fa-bell-slash text-4xl text-gray-300"></i>
                    <p className="mt-4 text-sm text-gray-500">No notifications found</p>
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
                            className={`w-full rounded-2xl bg-white border border-gray-200 shadow-sm p-4 md:p-6 text-left transition-all hover:shadow-md ${
                                n.is_read ? '' : 'border-l-4 border-l-blue-600'
                            }`}
                        >
                            <div className="flex items-start gap-3">
                                <div className={`mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${n.is_read ? 'bg-gray-100' : 'bg-blue-50'}`}>
                                    <i className={`fas ${n.icon || 'fa-bell'} text-sm ${n.is_read ? 'text-gray-400' : 'text-blue-600'}`}></i>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium text-gray-900">{n.title}</p>
                                        {!n.is_read && <span className="px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700">Unread</span>}
                                        <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${getCategoryColor(n.type)}`}>
                                            {n.type.replace('_', ' ')}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-sm text-gray-500 line-clamp-2">{n.message}</p>
                                    <p className="mt-1 text-xs text-gray-400">{n.time_ago}</p>
                                </div>
                            </div>
                        </button>
                    ))}
                </div>
            )}

            {/* Pagination */}
            {pagination && pagination.last_page > 1 && (
                <div className="sticky bottom-0 bg-white border-t border-gray-200 p-4 flex items-center justify-center gap-2 mt-4">
                    <button
                        onClick={() => setPage(p => Math.max(1, p - 1))}
                        disabled={page === 1}
                        className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm hover:bg-gray-50 disabled:opacity-50"
                    >
                        Previous
                    </button>
                    <span className="text-sm text-gray-500">Page {page} of {pagination.last_page}</span>
                    <button
                        onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))}
                        disabled={page === pagination.last_page}
                        className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm hover:bg-gray-50 disabled:opacity-50"
                    >
                        Next
                    </button>
                </div>
            )}

            {/* Detail Modal */}
            {selected && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setSelected(null)}>
                    <div className="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="text-lg font-bold text-gray-900">{selected.title}</h2>
                                <button onClick={() => setSelected(null)} className="text-gray-400 hover:text-gray-600">
                                    <i className="fas fa-times"></i>
                                </button>
                            </div>
                            <p className="text-sm text-gray-500">{selected.message}</p>
                            <p className="mt-4 text-xs text-gray-400">{selected.time_ago}</p>
                            <div className="mt-6 flex justify-end border-t border-gray-100 pt-4">
                                <button
                                    onClick={() => deleteMutation.mutate(selected.id)}
                                    disabled={deleteMutation.isPending}
                                    className="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-100 disabled:opacity-50"
                                >
                                    <i className="fas fa-trash text-xs"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
