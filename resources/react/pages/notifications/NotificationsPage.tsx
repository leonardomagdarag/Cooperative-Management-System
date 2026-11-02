import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getNotifications, markAsRead, markAllAsRead, deleteNotification, deleteAllReadNotifications, Notification } from '../../api/notifications';
import { useToast } from '../../contexts/ToastContext';
import ConfirmDialog from '../../components/ui/ConfirmDialog';
import Pagination from '../../components/ui/Pagination';
import DateRangeFilter from '../../components/ui/DateRangeFilter';
import { useLanguage } from '../../contexts/LanguageContext';
import PageHeader from '../../components/ui/PageHeader';

export default function NotificationsPage() {
    const { t } = useLanguage();
    const [statusFilter, setStatusFilter] = useState('all');
    const [categoryFilter, setCategoryFilter] = useState('all');
    const [search, setSearch] = useState('');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [sort, setSort] = useState('created_at');
    const [direction, setDirection] = useState<'asc' | 'desc'>('desc');
    const [page, setPage] = useState(1);
    const [selected, setSelected] = useState<Notification | null>(null);
    const [confirmState, setConfirmState] = useState<{
        isOpen: boolean;
        title: string;
        message: string;
        confirmText?: string;
        variant?: 'danger' | 'warning' | 'primary';
        onConfirm: () => void;
    }>({ isOpen: false, title: '', message: '', onConfirm: () => {} });
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['notifications', statusFilter, categoryFilter, search, from, to, sort, direction, page],
        queryFn: () => getNotifications({
            status: statusFilter === 'all' ? undefined : statusFilter,
            category: categoryFilter === 'all' ? undefined : categoryFilter,
            search: search || undefined,
            from: from || undefined,
            to: to || undefined,
            sort,
            direction,
            page,
            per_page: 15,
        }),
    });

    const markReadMutation = useMutation({
        mutationFn: markAsRead,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['notifications'] });
            queryClient.invalidateQueries({ queryKey: ['notifications-unread-count'] });
        },
        onError: (err: any) => addToast(err?.response?.data?.message || 'Failed to mark as read', 'error'),
    });

    const markAllMutation = useMutation({
        mutationFn: markAllAsRead,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['notifications'] });
            queryClient.invalidateQueries({ queryKey: ['notifications-unread-count'] });
        },
        onError: (err: any) => addToast(err?.response?.data?.message || 'Failed to mark all as read', 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: deleteNotification,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['notifications'] });
            queryClient.invalidateQueries({ queryKey: ['notifications-unread-count'] });
            setSelected(null);
        },
        onError: (err: any) => addToast(err?.response?.data?.message || 'Failed to delete notification', 'error'),
    });

    const deleteAllReadMutation = useMutation({
        mutationFn: deleteAllReadNotifications,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['notifications'] });
            queryClient.invalidateQueries({ queryKey: ['notifications-unread-count'] });
        },
        onError: (err: any) => addToast(err?.response?.data?.message || 'Failed to clear read notifications', 'error'),
    });

    const notifications: Notification[] = data?.data?.notifications ?? [];
    const pagination = data?.data?.pagination;
    const priorityCounts = data?.data?.priority_counts ?? { high: 0, normal: 0 };

    const getCategoryColor = (type: string) => {
        if (type === 'security_alert') return 'bg-red-100 text-red-700';
        if (type === 'loan_due') return 'bg-amber-100 text-amber-700';
        if (type === 'announcement') return 'bg-blue-100 text-blue-700';
        return 'bg-slate-100 text-slate-700';
    };

    return (
        <div className="max-w-7xl mx-auto p-6">
            <PageHeader className="mb-6" title={t('Notifications')} description={t('Stay on top of your alerts and updates')} icon="fas fa-bell" action={<div className="flex items-center gap-2">
                    <button
                        onClick={() => markAllMutation.mutate()}
                        className="bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    >
                        Mark All Read
                    </button>
                    <button
                        onClick={() => setConfirmState({
                            isOpen: true,
                            title: t('Clear Read Notifications'),
                            message: t('Are you sure you want to delete all read notifications? This action cannot be undone.'),
                            confirmText: 'Clear All Read',
                            variant: 'danger',
                            onConfirm: () => deleteAllReadMutation.mutate(),
                        })}
                        className="text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    >
                        Clear Read
                    </button>
                </div>} />

            <div className="mb-6 grid gap-3 sm:grid-cols-2">
                <div className="flex items-center gap-4 rounded-2xl border border-rose-100 bg-white p-4 shadow-sm">
                    <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-rose-50 text-rose-600"><i className="fas fa-triangle-exclamation" aria-hidden="true"></i></span>
                    <div><p className="text-sm font-medium text-slate-600">High importance</p><p className="text-2xl font-bold text-slate-900">{priorityCounts.high}</p></div>
                </div>
                <div className="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i className="fas fa-bell" aria-hidden="true"></i></span>
                    <div><p className="text-sm font-medium text-slate-600">Normal alerts</p><p className="text-2xl font-bold text-slate-900">{priorityCounts.normal}</p></div>
                </div>
            </div>

            {/* Filters */}
            <div className="table-toolbar mb-4">
                <input type="search" value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} placeholder="Search notifications..." aria-label="Search notifications" className="rounded-lg border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm" />
                <DateRangeFilter from={from} to={to} onFromChange={value => { setFrom(value); setPage(1); }} onToChange={value => { setTo(value); setPage(1); }} />
                <select value={`${sort}:${direction}`} onChange={e => { const [field, order] = e.target.value.split(':'); setSort(field); setDirection(order as 'asc' | 'desc'); setPage(1); }} aria-label="Sort notifications" className="rounded-lg border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm"><option value="created_at:desc">Newest first</option><option value="created_at:asc">Oldest first</option><option value="title:asc">Title A–Z</option><option value="title:desc">Title Z–A</option><option value="priority:desc">Priority high first</option></select>
                <select
                    value={statusFilter}
                    onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
                    className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 p-2.5"
                >
                    <option value="all">All Status</option>
                    <option value="unread">Unread</option>
                    <option value="read">Read</option>
                </select>
                <select
                    value={categoryFilter}
                    onChange={(e) => { setCategoryFilter(e.target.value); setPage(1); }}
                    className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 p-2.5"
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
                    <p className="mt-4 text-sm text-gray-500">{t('No notifications found')}</p>
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
                                n.is_read ? '' : 'border-l-4 border-l-emerald-600'
                            }`}
                        >
                            <div className="flex items-start gap-3">
                                <div className={`mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${n.is_read ? 'bg-gray-100' : 'bg-emerald-50'}`}>
                                    <i className={`fas ${n.icon || 'fa-bell'} text-sm ${n.is_read ? 'text-gray-400' : 'text-emerald-600'}`}></i>
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium text-gray-900">{n.title}</p>
                                        {!n.is_read && <span className="px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700">Unread</span>}
                                        <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${n.priority === 'high' || n.priority === 'urgent' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600'}`}>
                                            {n.priority === 'high' || n.priority === 'urgent' ? 'High importance' : 'Normal'}
                                        </span>
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
                <div className="mt-6">
                    <Pagination
                        page={page}
                        lastPage={pagination.last_page || 1}
                        onChange={setPage}
                        total={pagination.total}
                        perPage={pagination.per_page}
                    />
                </div>
            )}

            {/* Detail Modal */}
            {selected && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={() => setSelected(null)}>
                    <div className="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                        <div className="bg-gradient-to-r from-emerald-600 to-cyan-600 px-6 py-5 text-white shrink-0">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-bold">{selected.title}</h2>
                                <button onClick={() => setSelected(null)} className="rounded-full p-1 text-white/70 hover:bg-white/20 hover:text-white transition-colors">
                                    <i className="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div className="p-6">
                            <p className="text-sm text-gray-500 leading-relaxed">{selected.message}</p>
                            <p className="mt-4 text-xs text-gray-400">{selected.time_ago}</p>
                            <div className="mt-6 flex justify-end border-t border-gray-100 pt-4">
                                <button
                                    onClick={() => setConfirmState({
                                        isOpen: true,
                                        title: t('Delete Notification'),
                                        message: t('Are you sure you want to delete this notification?'),
                                        confirmText: 'Delete',
                                        variant: 'danger',
                                        onConfirm: () => deleteMutation.mutate(selected.id),
                                    })}
                                    disabled={deleteMutation.isPending}
                                    className="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-100 disabled:opacity-50 transition-colors"
                                >
                                    <i className="fas fa-trash text-xs"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <ConfirmDialog
                isOpen={confirmState.isOpen}
                title={confirmState.title}
                message={confirmState.message}
                confirmText={confirmState.confirmText}
                variant={confirmState.variant}
                onConfirm={() => {
                    confirmState.onConfirm();
                    setConfirmState(prev => ({ ...prev, isOpen: false }));
                }}
                onCancel={() => setConfirmState(prev => ({ ...prev, isOpen: false }))}
            />
        </div>
    );
}
