import { useAuth } from '../../contexts/AuthContext';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { formatPermissionLabel } from '../../lib/permissions';

export default function OfficerOverview() {
    const { user } = useAuth();
    const { data, isLoading } = useQuery({ queryKey: ['overview-officer'], queryFn: () => api.get('/api/dashboard') });
    const d = data?.data || {};

    const permissions = user?.permissions || [];

    const kpis = [
        { key: 'pending_transactions', label: 'Pending approvals', icon: 'fa-clipboard-check', chip: 'bg-amber-100 text-amber-700' },
        { key: 'pending_loans', label: 'Pending loans', icon: 'fa-file-alt', chip: 'bg-indigo-100 text-indigo-700' },
        { key: 'members_new_month', label: 'New members (month)', icon: 'fa-user-plus', chip: 'bg-sky-100 text-sky-700' },
        { key: 'members_total', label: 'Total members', icon: 'fa-users', chip: 'bg-emerald-100 text-emerald-700' },
        { key: 'loans_active', label: 'Active loans', icon: 'fa-file-invoice-dollar', chip: 'bg-blue-100 text-blue-700' },
        { key: 'loans_overdue', label: 'Overdue loans', icon: 'fa-exclamation-triangle', chip: 'bg-red-100 text-red-700' },
    ].filter(k => d[k.key] != null);

    const announcements = Array.isArray(d.recent_announcements) ? d.recent_announcements : [];

    return (
        <div className="space-y-6">
            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="bg-linear-to-r from-slate-900 via-cyan-800 to-emerald-700 px-6 py-7 text-white">
                    <p className="text-sm font-medium text-cyan-100">{d.greeting || 'Good day'}</p>
                    <h1 className="mt-1 text-2xl font-medium">Officer dashboard</h1>
                    <p className="mt-2 max-w-2xl text-sm text-cyan-50/90">
                        Your role is served by the shared permission-driven dashboard path. The cards below reflect the sections
                        granted to this account — use the navigation to manage the rest.
                    </p>
                </div>

                {isLoading ? (
                    <div className="animate-pulse px-6 py-5">
                        <div className="grid gap-4 md:grid-cols-3">
                            {[0, 1, 2].map(i => (
                                <div key={i} className="h-20 rounded-xl bg-slate-200" />
                            ))}
                        </div>
                    </div>
                ) : (
                    <div className="grid gap-4 px-6 py-5 md:grid-cols-3">
                        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Active role</p>
                            <p className="mt-2 text-lg font-semibold text-slate-900">
                                {user?.primary_role ? user.primary_role.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : 'Officer'}
                            </p>
                        </div>

                        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-emerald-700">Granted permissions</p>
                            <p className="mt-2 text-lg font-semibold text-emerald-900">{permissions.length}</p>
                        </div>

                        <div className="rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-cyan-700">Account status</p>
                            <p className="mt-2 text-lg font-semibold text-cyan-900 capitalize">{user?.account_status || 'Active'}</p>
                        </div>
                    </div>
                )}
            </div>

            {!isLoading && kpis.length > 0 && (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {kpis.map(k => (
                        <div key={k.key} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div className="flex items-center justify-between">
                                <span className={`inline-flex h-10 w-10 items-center justify-center rounded-xl ${k.chip}`}>
                                    <i className={`fa-solid ${k.icon}`} />
                                </span>
                                <span className="text-2xl font-bold text-slate-900">{Number(d[k.key]).toLocaleString()}</span>
                            </div>
                            <p className="mt-3 text-sm font-medium text-slate-600">{k.label}</p>
                        </div>
                    ))}
                </div>
            )}

            {announcements.length > 0 && (
                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-4">
                        <h3 className="text-lg font-semibold text-slate-900">Recent announcements</h3>
                    </div>
                    <div className="divide-y divide-slate-100">
                        {announcements.map(a => (
                            <div key={a.id} className="px-6 py-4">
                                <div className="flex items-start justify-between gap-4">
                                    <p className="font-semibold text-slate-900">{a.title}</p>
                                    <span className="shrink-0 text-xs text-slate-400">{a.created_at_diff}</span>
                                </div>
                                {a.content && <p className="mt-1 line-clamp-2 text-sm text-slate-500">{a.content}</p>}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-200 px-6 py-4">
                    <h3 className="text-lg font-semibold text-slate-900">Permission summary</h3>
                    <p className="mt-1 text-sm text-slate-500">Inherited and direct permissions currently available to this role.</p>
                </div>

                <div className="px-6 py-5">
                    {permissions.length === 0 ? (
                        <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800">
                            No permissions are currently assigned. An administrator can grant access from Role Permission Management.
                        </div>
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            {permissions.map((p: string) => (
                                <span key={p} className="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700">
                                    {formatPermissionLabel(p)}
                                </span>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
