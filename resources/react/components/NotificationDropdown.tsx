import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getNotifications, getUnreadCount, markAsRead, markAllAsRead, Notification } from '../api/notifications';
import { useNavigate } from 'react-router';

export default function NotificationDropdown() {
    const [isOpen, setIsOpen] = useState(false);
    const queryClient = useQueryClient();
    const navigate = useNavigate();

    const { data: unreadData } = useQuery({
        queryKey: ['notifications-unread-count'],
        queryFn: () => getUnreadCount(),
        refetchInterval: 30_000,
    });

    const { data: notificationsData } = useQuery({
        queryKey: ['notifications-recent'],
        queryFn: () => getNotifications({ status: 'unread' }),
        enabled: isOpen,
    });

    const markReadMutation = useMutation({
        mutationFn: markAsRead,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['notifications-unread-count'] });
            queryClient.invalidateQueries({ queryKey: ['notifications-recent'] });
        },
    });

    const markAllMutation = useMutation({
        mutationFn: markAllAsRead,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['notifications-unread-count'] });
            queryClient.invalidateQueries({ queryKey: ['notifications-recent'] });
        },
    });

    const unreadCount = unreadData?.data?.count ?? 0;
    const notifications: Notification[] = notificationsData?.data?.data ?? [];

    return (
        <div className="relative">
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700"
            >
                <i className="fas fa-bell"></i>
                {unreadCount > 0 && (
                    <span className="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <>
                    <div className="fixed inset-0 z-40" onClick={() => setIsOpen(false)} />
                    <div className="absolute right-0 top-full z-50 mt-2 w-80 rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800">
                        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-700">
                            <h3 className="text-sm font-semibold">Notifications</h3>
                            {unreadCount > 0 && (
                                <button
                                    onClick={() => markAllMutation.mutate()}
                                    className="text-xs text-emerald-600 hover:text-emerald-700"
                                >
                                    Mark all read
                                </button>
                            )}
                        </div>
                        <div className="max-h-80 overflow-y-auto">
                            {notifications.length === 0 ? (
                                <p className="p-4 text-center text-sm text-slate-500">No unread notifications</p>
                            ) : (
                                notifications.map(n => (
                                    <button
                                        key={n.id}
                                        onClick={() => {
                                            markReadMutation.mutate(n.id);
                                            setIsOpen(false);
                                            navigate('/dashboard/notifications');
                                        }}
                                        className="w-full border-b border-slate-50 px-4 py-3 text-left hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-700"
                                    >
                                        <p className="text-sm font-medium">{n.data.title}</p>
                                        <p className="mt-0.5 text-xs text-slate-500 line-clamp-2">{n.data.message}</p>
                                    </button>
                                ))
                            )}
                        </div>
                        <div className="border-t border-slate-100 dark:border-slate-700">
                            <button
                                onClick={() => {
                                    setIsOpen(false);
                                    navigate('/dashboard/notifications');
                                }}
                                className="w-full px-4 py-2.5 text-center text-xs font-medium text-emerald-600 hover:bg-slate-50 dark:hover:bg-slate-700"
                            >
                                View all notifications
                            </button>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
