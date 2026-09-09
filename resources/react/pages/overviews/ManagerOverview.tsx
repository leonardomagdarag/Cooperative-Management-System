import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function ManagerOverview() {
    const { data, isLoading } = useQuery({ queryKey: ['overview-manager'], queryFn: () => api.get('/api/dashboard') });
    const d = data?.data || {};
    if (isLoading) return <Skeleton />;

    const monthlySeries = d.monthly_series || [];
    const maxVal = Number(d.monthly_max_value) || 1;
    const pendingTx = d.pending_transactions_list || [];
    const newMembers = d.recent_members || [];
    const announcements = d.recent_announcements || [];
    const totalAlerts = (Number(d.pending_transactions) || 0) + (Number(d.loans_pending) || 0) + (Number(d.loans_overdue) || 0);

    return (
        <div className="space-y-6">
            <div className="relative overflow-hidden bg-gradient-to-br from-blue-700 via-blue-600 to-sky-700 rounded-2xl shadow-xl p-8 text-white">
                <div className="absolute -top-8 -right-8 w-48 h-48 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="absolute -bottom-12 -left-6 w-40 h-40 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p className="text-blue-200 text-sm font-medium uppercase tracking-widest mb-1">Manager</p>
                        <h1 className="text-3xl font-extrabold leading-tight">{d.greeting}</h1>
                        <p className="mt-1 text-blue-200">Operations overview — {d.period_label || ''}</p>
                    </div>
                    <div className="sm:text-right shrink-0">
                        <p className="text-blue-200 text-xs uppercase tracking-wide">Today</p>
                        <p className="text-2xl font-bold">{new Date().toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}</p>
                        <p className="text-blue-200 text-sm">{new Date().toLocaleDateString('en-US', { weekday: 'long' })}</p>
                    </div>
                </div>
                {totalAlerts > 0 && (
                    <div className="relative mt-5 flex flex-wrap gap-3">
                        {Number(d.pending_transactions) > 0 && <span className="inline-flex items-center gap-1.5 bg-amber-400/20 border border-amber-300/40 text-amber-100 text-xs font-semibold px-3 py-1.5 rounded-full">{d.pending_transactions} pending txn{Number(d.pending_transactions) !== 1 ? 's' : ''}</span>}
                        {Number(d.loans_pending) > 0 && <span className="inline-flex items-center gap-1.5 bg-sky-400/20 border border-sky-300/40 text-sky-100 text-xs font-semibold px-3 py-1.5 rounded-full">{d.loans_pending} loan app{Number(d.loans_pending) !== 1 ? 's' : ''}</span>}
                        {Number(d.loans_overdue) > 0 && <span className="inline-flex items-center gap-1.5 bg-red-400/20 border border-red-300/40 text-red-100 text-xs font-semibold px-3 py-1.5 rounded-full">{d.loans_overdue} overdue</span>}
                    </div>
                )}
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <KpiCard label="Total Members" value={Number(d.members_total || d.total_members || 0).toLocaleString()} icon="users" color="emerald" badge={`+${Number(d.members_new_month || 0).toLocaleString()} mo.`} />
                <KpiCard label="Total Savings" value={`₱${Number(d.savings_total || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`} icon="piggy-bank" color="cyan" />
                <KpiCard label="Active Loans" value={Number(d.loans_active || 0).toLocaleString()} icon="file-invoice" color="blue" detail={`Overdue: ${Number(d.loans_overdue || 0)}`} />
                <div className={`bg-white rounded-2xl shadow-sm border p-5 ${Number(d.pending_transactions) > 0 ? 'border-amber-200 bg-amber-50' : 'border-gray-100'}`}>
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center shrink-0"><i className="fas fa-clock text-amber-600"></i></div>
                        <div><p className="text-2xl font-bold text-gray-900">{Number(d.pending_transactions || 0).toLocaleString()}</p><p className="text-xs text-gray-500">Pending Approvals</p></div>
                    </div>
                    <p className="mt-2 pt-2 border-t border-gray-100 text-xs text-gray-500">Loan apps: {Number(d.loans_pending || 0)}</p>
                </div>
            </div>

            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-100"><h2 className="text-base font-bold text-gray-900">Monthly Cash Flow</h2></div>
                <div className="p-5">
                    <div className="flex gap-4 mb-3 text-xs font-medium"><span className="inline-flex items-center gap-1.5"><span className="w-3 h-1.5 rounded-full bg-blue-400"></span>Deposits</span><span className="inline-flex items-center gap-1.5"><span className="w-3 h-1.5 rounded-full bg-rose-400"></span>Outflows</span></div>
                    <div className="space-y-3">
                        {monthlySeries.map((m: any, i: number) => {
                            const inW = maxVal > 0 ? ((Number(m.deposits) || 0) / maxVal) * 100 : 0;
                            const outW = maxVal > 0 ? (((Number(m.withdrawals) || 0) + (Number(m.expenses) || 0)) / maxVal) * 100 : 0;
                            return (
                                <div key={i} className="flex items-center gap-3">
                                    <span className="w-12 text-xs text-gray-500 shrink-0">{m.label}</span>
                                    <div className="flex-1 space-y-1">
                                        <div className="flex items-center gap-2"><div className="bg-gray-100 rounded-full h-1.5 flex-1"><div className="bg-blue-400 h-1.5 rounded-full" style={{ width: `${Math.min(100, inW)}%` }}></div></div><span className="text-xs text-blue-700 w-14 text-right">₱{Number(m.deposits || 0).toLocaleString()}</span></div>
                                        <div className="flex items-center gap-2"><div className="bg-gray-100 rounded-full h-1.5 flex-1"><div className="bg-rose-400 h-1.5 rounded-full" style={{ width: `${Math.min(100, outW)}%` }}></div></div><span className="text-xs text-rose-600 w-14 text-right">₱{(Number(m.withdrawals || 0) + Number(m.expenses || 0)).toLocaleString()}</span></div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            <div className="grid xl:grid-cols-3 gap-6">
                <div className="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100"><h2 className="text-base font-bold text-gray-900">Pending Transaction Approvals</h2></div>
                    <div className="overflow-x-auto">
                        {pendingTx.length > 0 ? (
                            <table className="min-w-full text-sm">
                                <thead><tr className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100"><th className="px-6 py-3">Txn #</th><th className="px-6 py-3">Type</th><th className="px-6 py-3">Member</th><th className="px-6 py-3">Amount</th><th className="px-6 py-3">Date</th></tr></thead>
                                <tbody className="divide-y divide-gray-50">
                                    {pendingTx.map((t: any) => (
                                        <tr key={t.id} className="hover:bg-gray-50/50">
                                            <td className="px-6 py-3 font-mono text-xs">{t.transaction_number}</td>
                                            <td className="px-6 py-3"><span className="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-700">{t.transaction_type?.replace(/_/g, ' ')}</span></td>
                                            <td className="px-6 py-3 text-xs">{t.member_name}</td>
                                            <td className="px-6 py-3 font-medium">₱{Number(t.amount).toLocaleString()}</td>
                                            <td className="px-6 py-3 text-xs text-gray-400">{t.created_at}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <div className="p-8 text-center"><p className="text-sm text-gray-400">All clear — no pending approvals.</p></div>
                        )}
                    </div>
                </div>

                <div className="space-y-6">
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100"><h2 className="text-base font-bold text-gray-900">Newest Members</h2></div>
                        <div className="p-4 space-y-3">
                            {newMembers.length > 0 ? newMembers.map((m: any) => (
                                <div key={m.id} className="flex items-center gap-3">
                                    <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-600">{m.name?.charAt(0) || '?'}</div>
                                    <div className="min-w-0 flex-1"><p className="text-sm font-medium text-gray-800 truncate">{m.name}</p><p className="text-xs text-gray-400">{m.created_at}</p></div>
                                </div>
                            )) : <div className="py-4 text-center text-sm text-gray-400">No recent members</div>}
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100"><h2 className="text-base font-bold text-gray-900">Recent Announcements</h2></div>
                        <div className="p-4 space-y-3">
                            {announcements.length > 0 ? announcements.map((a: any) => (
                                <div key={a.id} className="flex gap-3"><div className="w-1 rounded-full bg-blue-400 self-stretch shrink-0"></div><div><p className="text-sm font-semibold text-gray-800">{a.title}</p><p className="text-xs text-gray-500 line-clamp-2">{a.content}</p><p className="text-xs text-gray-400 mt-1">{a.created_at_diff}</p></div></div>
                            )) : <div className="py-4 text-center text-sm text-gray-400">No recent announcements</div>}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function KpiCard({ label, value, icon, color, badge, detail }: { label: string; value: string; icon: string; color: string; badge?: string; detail?: string }) {
    const cols: Record<string, string> = { emerald: 'bg-emerald-50 text-emerald-600', cyan: 'bg-cyan-50 text-cyan-600', blue: 'bg-blue-50 text-blue-600' };
    const c = cols[color] || cols.emerald;
    return (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div className="flex items-center gap-3">
                <div className={`w-10 h-10 ${c.split(' ')[0]} rounded-xl flex items-center justify-center shrink-0`}><i className={`fas fa-${icon} ${c.split(' ')[1]}`}></i></div>
                <div className="min-w-0 flex-1"><p className="text-2xl font-bold text-gray-900">{value}</p><p className="text-xs text-gray-500">{label}</p></div>
                {badge && <span className="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full self-start">{badge}</span>}
            </div>
            {detail && <p className="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500">{detail}</p>}
        </div>
    );
}

function Skeleton() {
    return (
        <div className="space-y-4">
            <div className="h-36 animate-pulse rounded-2xl bg-slate-100" />
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">{[...Array(4)].map((_, i) => <div key={i} className="h-24 animate-pulse rounded-2xl bg-slate-100" />)}</div>
        </div>
    );
}
