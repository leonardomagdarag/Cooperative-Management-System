import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';

export default function SecretaryOverview() {
    const { data, isLoading } = useQuery({ queryKey: ['overview-secretary'], queryFn: () => api.get('/api/dashboard') });
    const { user } = useAuth();
    const d = data?.data || {};
    if (isLoading) return <div className="space-y-4"><div className="h-36 animate-pulse rounded-2xl bg-slate-100" /><div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">{[...Array(4)].map((_, i) => <div key={i} className="h-24 animate-pulse rounded-2xl bg-slate-100" />)}</div></div>;

    const members = d.recent_members || [];
    const announcements = d.recent_announcements || [];

    return (
        <div className="space-y-6">
            <div className="relative overflow-hidden bg-gradient-to-br from-teal-700 via-teal-600 to-cyan-700 rounded-2xl shadow-xl p-8 text-white">
                <div className="absolute -top-8 -right-8 w-48 h-48 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="absolute -bottom-12 -left-6 w-40 h-40 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p className="text-teal-200 text-sm font-medium uppercase tracking-widest mb-1">Secretary</p>
                        <h1 className="text-3xl font-extrabold leading-tight">{d.greeting}, {user?.first_name || 'Secretary'}</h1>
                        <p className="mt-1 text-teal-200">Member records and communications - {d.period_label || ''}</p>
                    </div>
                    <div className="sm:text-right shrink-0">
                        <p className="text-teal-200 text-xs uppercase tracking-wide">Today</p>
                        <p className="text-2xl font-bold">{new Date().toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}</p>
                        <p className="text-teal-200 text-sm">{new Date().toLocaleDateString('en-US', { weekday: 'long' })}</p>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <KpiCard label="Total Members" value={Number(d.members_total || d.total_members || 0).toLocaleString()} />
                <KpiCard label="New This Month" value={Number(d.members_new_month || d.new_this_month || 0).toLocaleString()} />
                <KpiCard label="Active Members" value={Number(d.members_active || 0).toLocaleString()} />
                <KpiCard label="Announcements" value={Number(d.announcements_total || d.total_announcements || 0).toLocaleString()} detail={`+${d.announcements_this_month || 0} this month`} />
            </div>

            <div className="grid xl:grid-cols-3 gap-6">
                <div className="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100">
                        <h2 className="text-base font-bold text-gray-900">Recently Registered Members</h2>
                        <p className="text-xs text-gray-500">Latest member enrollments</p>
                    </div>
                    <div className="p-6">
                        {members.length > 0 ? (
                            <div>
                                {members.map((m: any) => (
                                    <div key={m.id} className="flex items-center gap-4 border-b border-gray-50 py-2 last:border-0">
                                        <div className="w-9 h-9 rounded-full bg-teal-100 flex items-center justify-center text-sm font-bold text-teal-700 shrink-0">{m.name?.charAt(0).toUpperCase() || 'M'}</div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-semibold text-gray-900 truncate">{m.name}</p>
                                            <p className="text-xs text-gray-400">{m.user_number}</p>
                                        </div>
                                        <div className="shrink-0 text-right">
                                            <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${m.account_status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}>{m.account_status ? m.account_status.charAt(0).toUpperCase() + m.account_status.slice(1) : 'Active'}</span>
                                            <p className="text-xs text-gray-400 mt-1">{m.created_at}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="py-8 text-center text-sm text-gray-400">No members registered yet.</p>
                        )}
                    </div>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="px-5 py-3.5 border-b border-gray-100"><h3 className="text-sm font-bold text-gray-900">Announcements</h3></div>
                    <div className="p-5 space-y-4">
                        {announcements.length > 0 ? announcements.map((a: any) => (
                            <div key={a.id} className="flex gap-3"><div className="w-1 rounded-full bg-teal-400 self-stretch shrink-0"></div><div className="min-w-0"><p className="text-sm font-semibold text-gray-800 truncate">{a.title}</p><p className="text-xs text-gray-500 line-clamp-1 mt-0.5">{a.content}</p><div className="flex items-center gap-2 mt-1 text-xs text-gray-400"><span>{a.creator_name}</span><span>{a.created_at_diff}</span></div></div></div>
                        )) : <p className="py-4 text-center text-sm text-gray-400">No announcements yet.</p>}
                    </div>
                </div>
            </div>
        </div>
    );
}

function KpiCard({ label, value, detail }: { label: string; value: string; detail?: string }) {
    return (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <p className="text-2xl font-extrabold text-gray-900">{value}</p>
            <p className="text-xs text-gray-500 mt-0.5">{label}</p>
            {detail && <p className="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500">{detail}</p>}
        </div>
    );
}
