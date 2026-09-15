import { useState, useRef, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getNotifications, getUnreadCount, markAsRead, markAllAsRead, Notification } from '../api/notifications';
import { useNavigate } from 'react-router';
import { useToast } from '../contexts/ToastContext';

export default function NotificationDropdown() {
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);
    const queryClient = useQueryClient();
    const navigate = useNavigate();
    const { addToast } = useToast();

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
                setIsOpen(false);
            }
        }
        if (isOpen) {
            document.addEventListener('mousedown', handleClickOutside);
        }
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [isOpen]);

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
        onError: (err: any) => addToast(err?.response?.data?.message || 'Failed to update notification', 'error'),
    });

    const markAllMutation = useMutation({
        mutationFn: markAllAsRead,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['notifications-unread-count'] });
            queryClient.invalidateQueries({ queryKey: ['notifications-recent'] });
        },
        onError: (err: any) => addToast(err?.response?.data?.message || 'Failed to update notifications', 'error'),
    });

    const unreadCount = unreadData?.data?.count ?? 0;
    const notifications: Notification[] = notificationsData?.data?.data ?? [];

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="relative flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors"
                title="Notifications"
                aria-label="View notifications"
            >
                <i className="fas fa-bell text-base"></i>
                {unreadCount > 0 && (
                    <span className="absolute 1.5 top-1.5 flex h-4 min-w-4 px-1 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white shadow-xs">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <div className="absolute right-0 top-full z-50 mt-2 w-80 sm:w-96 rounded-xl border border-slate-200 bg-white shadow-xl animate-in fade-in zoom-in-95 duration-100">
                    <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3 bg-slate-50/50 rounded-t-xl">
                        <div className="flex items-center gap-2">
                            <h3 className="text-sm font-semibold text-slate-900">Notifications</h3>
                            {unreadCount > 0 && (
                                <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                                    {unreadCount} new
                                </span>
                            )}
                        </div>
                        {unreadCount > 0 && (
                            <button
                                onClick={() => markAllMutation.mutate()}
                                className="text-xs font-medium text-emerald-600 hover:text-emerald-700 cursor-pointer"
                            >
                                Mark all read
                            </button>
                        )}
                    </div>
                    <div className="max-h-80 overflow-y-auto divide-y divide-slate-50">
                        {notifications.length === 0 ? (
                            <div className="py-8 text-center">
                                <i className="fas fa-bell-slash text-slate-300 text-2xl mb-2"></i>
                                <p className="text-sm text-slate-500">No unread notifications</p>
                            </div>
                        ) : (
                            notifications.map(n => (
                                <button
                                    key={n.id}
                                    onClick={() => {
                                        markReadMutation.mutate(n.id);
                                        setIsOpen(false);
                                        navigate('/dashboard/notifications');
                                    }}
                                    className="w-full px-4 py-3 text-left hover:bg-slate-50 transition-colors flex flex-col gap-0.5"
                                >
                                    <div className="flex items-center justify-between">
                                        <p className="text-sm font-medium text-slate-900 line-clamp-1">{n.title}</p>
                                        <span className="h-2 w-2 rounded-full bg-emerald-500 shrink-0"></span>
                                    </div>
                                    <p className="text-xs text-slate-500 line-clamp-2">{n.message}</p>
                                </button>
                            ))
                        )}
                    </div>
                    <div className="border-t border-slate-100 bg-slate-50/50 rounded-b-xl">
                        <button
                            onClick={() => {
                                setIsOpen(false);
                                navigate('/dashboard/notifications');
                            }}
                            className="w-full px-4 py-2.5 text-center text-xs font-semibold text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50/50 transition-colors"
                        >
                            View all notifications
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
