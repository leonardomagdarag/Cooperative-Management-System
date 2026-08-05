import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function LoanOverview() {
    const { data, isLoading } = useQuery({ queryKey: ['overview-loan'], queryFn: () => api.get('/api/dashboard') });
    const d = data?.data || {};
    if (isLoading) return <div className="space-y-4"><div className="h-36 animate-pulse rounded-2xl bg-slate-100" /><div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">{[...Array(4)].map((_, i) => <div key={i} className="h-24 animate-pulse rounded-2xl bg-slate-100" />)}</div></div>;

    const totalAlerts = (Number(d.pending_loans) || 0) + (Number(d.loans_overdue) || 0);
    const pendingLoans = d.pending_loans_list || [];
    const overdueLoans = d.overdue_loans_list || [];
    const announcements = d.recent_announcements || [];
    const loanSeries = d.loan_status_series || [];

    return (
        <div className="space-y-6">
            <div className="relative overflow-hidden bg-gradient-to-br from-amber-600 via-orange-600 to-amber-700 rounded-2xl shadow-xl p-8 text-white">
                <div className="absolute -top-8 -right-8 w-48 h-48 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="absolute -bottom-12 -left-6 w-40 h-40 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p className="text-amber-200 text-sm font-medium uppercase tracking-widest mb-1">Loan Officer</p>
                        <h1 className="text-3xl font-extrabold leading-tight">{d.greeting}</h1>
                        <p className="mt-1 text-amber-200">Loan portfolio overview</p>
                    </div>
                    <div className="sm:text-right shrink-0">
                        <p className="text-amber-200 text-xs uppercase tracking-wide">Today</p>
                        <p className="text-2xl font-bold">{new Date().toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}</p>
                        <p className="text-amber-200 text-sm">{new Date().toLocaleDateString('en-US', { weekday: 'long' })}</p>
                    </div>
                </div>
                {totalAlerts > 0 && (
                    <div className="relative mt-5 flex flex-wrap gap-3">
                        {Number(d.pending_loans) > 0 && <span className="inline-flex items-center gap-1.5 bg-amber-400/20 border border-amber-300/40 text-amber-100 text-xs font-semibold px-3 py-1.5 rounded-full">{d.pending_loans} applications awaiting review</span>}
                        {Number(d.loans_overdue) > 0 && <span className="inline-flex items-center gap-1.5 bg-red-400/20 border border-red-300/40 text-red-100 text-xs font-semibold px-3 py-1.5 rounded-full">{d.loans_overdue} overdue</span>}
                        {Number(d.loans_due_soon) > 0 && <span className="inline-flex items-center gap-1.5 bg-white/15 border border-white/20 text-white text-xs font-semibold px-3 py-1.5 rounded-full">{d.loans_due_soon} due within 14 days</span>}
                    </div>
                )}
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <KpiCard label="Pending Applications" value={Number(d.pending_loans || d.loans_pending || 0).toLocaleString()} icon="clock" color="amber" alert={Number(d.pending_loans) > 0} />
                <KpiCard label="Active Loans" value={Number(d.active_loans || d.loans_active || 0).toLocaleString()} icon="file-invoice" color="blue" detail={`Outstanding ₱${Number(d.outstanding_amount || d.loans_outstanding || 0).toLocaleString()}`} />
                <KpiCard label="Overdue Loans" value={Number(d.overdue_loans || d.loans_overdue || 0).toLocaleString()} icon="exclamation-triangle" color="red" alert={Number(d.loans_overdue) > 0} />
                <KpiCard label="Due in 14 Days" value={Number(d.loans_due_soon || 0).toLocaleString()} icon="calendar" color="cyan" detail={`Total disbursed: ₱${Number(d.loans_disbursed || 0).toLocaleString()}`} />
            </div>

            <div className="grid xl:grid-cols-2 gap-6">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between"><h2 className="text-base font-bold text-gray-900">Pending Loan Applications</h2>{Number(d.pending_loans) > 0 && <span className="text-xs font-semibold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">{d.pending_loans} pending</span>}</div>
                    <div className="overflow-x-auto">
                        {pendingLoans.length > 0 ? (
                            <table className="min-w-full text-sm"><thead><tr className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100"><th className="px-6 py-3">Member</th><th className="px-6 py-3">Product</th><th className="px-6 py-3">Amount</th><th className="px-6 py-3">Applied</th></tr></thead><tbody className="divide-y divide-gray-50">{pendingLoans.map((l: any) => (<tr key={l.id} className="hover:bg-gray-50/50"><td className="px-6 py-3 text-xs">{l.member_name}</td><td className="px-6 py-3 text-xs">{l.loan_type?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</td><td className="px-6 py-3 font-medium">₱{Number(l.loan_amount).toLocaleString()}</td><td className="px-6 py-3 text-xs text-gray-400">{l.created_at}</td></tr>))}</tbody></table>
                        ) : (
                            <div className="p-8 text-center"><svg className="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><p className="text-sm text-gray-400">No pending loan applications</p></div>
                        )}
                    </div>
                </div>

                <div className="space-y-6">
                    {overdueLoans.length > 0 && (
                        <div className="bg-white rounded-2xl shadow-sm border border-red-200 overflow-hidden">
                            <div className="px-6 py-4 border-b border-red-100 bg-red-50/50"><h2 className="text-base font-bold text-gray-900">Overdue Loans</h2></div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm"><thead><tr className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100"><th className="px-6 py-3">Member</th><th className="px-6 py-3">Amount</th><th className="px-6 py-3">Due Date</th><th className="px-6 py-3">Days Overdue</th></tr></thead><tbody className="divide-y divide-gray-50">{overdueLoans.map((l: any) => (<tr key={l.id} className="hover:bg-gray-50/50"><td className="px-6 py-3 text-xs">{l.member_name}</td><td className="px-6 py-3 font-medium">₱{Number(l.loan_amount).toLocaleString()}</td><td className="px-6 py-3 text-xs text-red-600">{l.due_date}</td><td className="px-6 py-3"><span className="inline-flex px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700">{l.days_overdue}d</span></td></tr>))}</tbody></table>
                            </div>
                        </div>
                    )}

                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100"><h2 className="text-base font-bold text-gray-900">Loan Distribution</h2></div>
                        <div className="p-5">
                            {loanSeries.length > 0 ? (
                                <div className="space-y-2">
                                    {loanSeries.map((s: any, i: number) => (
                                        <div key={i}>
                                            <div className="flex justify-between text-xs mb-1"><span className="text-gray-600">{s.label}</span><span className="font-semibold text-gray-700">{s.count} ({s.percent}%)</span></div>
                                            <div className="w-full bg-gray-100 rounded-full h-2"><div className={`${s.color} h-2 rounded-full`} style={{ width: `${Math.max(2, s.percent)}%` }}></div></div>
                                        </div>
                                    ))}
                                </div>
                            ) : <div className="py-4 text-center text-sm text-gray-400">No loan data</div>}
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100"><h2 className="text-base font-bold text-gray-900">Announcements</h2></div>
                        <div className="p-4 space-y-3">
                            {announcements.length > 0 ? announcements.map((a: any) => (
                                <div key={a.id} className="flex gap-3"><div className="w-1 rounded-full bg-amber-400 self-stretch shrink-0"></div><div><p className="text-sm font-semibold text-gray-800">{a.title}</p><p className="text-xs text-gray-500 line-clamp-2">{a.content}</p><p className="text-xs text-gray-400 mt-1">{a.created_at_diff}</p></div></div>
                            )) : <div className="py-4 text-center text-sm text-gray-400">No announcements</div>}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function KpiCard({ label, value, icon, color, alert, detail }: { label: string; value: string; icon: string; color: string; alert?: boolean; detail?: string }) {
    const cols: Record<string, { bg: string; text: string }> = {
        amber: { bg: 'bg-amber-50', text: 'text-amber-600' },
        blue: { bg: 'bg-blue-50', text: 'text-blue-600' },
        red: { bg: 'bg-red-50', text: 'text-red-600' },
        cyan: { bg: 'bg-cyan-50', text: 'text-cyan-600' },
    };
    const c = cols[color] || cols.amber;
    return (
        <div className={`bg-white rounded-2xl shadow-sm border p-5 ${alert ? 'border-amber-200 bg-amber-50' : 'border-gray-100'}`}>
            <div className="flex items-center gap-3">
                <div className={`w-10 h-10 ${c.bg} rounded-xl flex items-center justify-center shrink-0`}><i className={`fas fa-${icon} ${c.text}`}></i></div>
                <div><p className="text-2xl font-bold text-gray-900">{value}</p><p className="text-xs text-gray-500">{label}</p></div>
            </div>
            {detail && <p className="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500">{detail}</p>}
        </div>
    );
}
