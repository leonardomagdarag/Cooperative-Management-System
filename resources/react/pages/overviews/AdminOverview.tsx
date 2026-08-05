import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function AdminOverview() {
    const { data, isLoading } = useQuery({
        queryKey: ['overview-admin'],
        queryFn: () => api.get('/api/dashboard'),
    });

    const d = data?.data || {};

    if (isLoading) {
        return (
            <div className="space-y-4">
                <div className="h-36 animate-pulse rounded-2xl bg-slate-100" />
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {[...Array(4)].map((_, i) => <div key={i} className="h-24 animate-pulse rounded-2xl bg-slate-100" />)}
                </div>
            </div>
        );
    }

    const net = Number(d.month_net || 0);
    const netPos = net >= 0;
    const totalAlerts = (Number(d.pending_transactions) || 0) + (Number(d.pending_loans) || 0) + (Number(d.loans_overdue) || 0);
    const monthlySeries = d.monthly_series || [];
    const maxVal = Number(d.monthly_max_value) || 1;
    const loanSeries = d.loan_status_series || [];
    const pendingTx = d.pending_transactions_list || [];
    const expenses = d.recent_expenses || [];
    const announcements = d.recent_announcements || [];

    return (
        <div className="space-y-6">
            <div className="relative overflow-hidden bg-gradient-to-br from-emerald-700 via-green-700 to-emerald-800 rounded-2xl shadow-xl p-8 text-white">
                <div className="absolute -top-10 -right-10 w-52 h-52 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="absolute -bottom-14 -left-8 w-44 h-44 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p className="text-emerald-200 text-sm font-medium uppercase tracking-widest mb-1">System Administrator</p>
                        <h1 className="text-3xl font-extrabold leading-tight">{d.greeting}, Admin</h1>
                        <p className="mt-1 text-emerald-200">Cooperative-wide overview — {d.period_label || ''}</p>
                    </div>
                    <div className="sm:text-right shrink-0">
                        <p className="text-emerald-200 text-xs uppercase tracking-wide">Today</p>
                        <p className="text-2xl font-bold">{new Date().toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}</p>
                        <p className="text-emerald-200 text-sm">{new Date().toLocaleDateString('en-US', { weekday: 'long' })}</p>
                    </div>
                </div>
                {totalAlerts > 0 && (
                    <div className="relative mt-5 flex flex-wrap gap-3">
                        {Number(d.pending_transactions) > 0 && (
                            <span className="inline-flex items-center gap-1.5 bg-amber-400/20 border border-amber-300/40 text-amber-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                                <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clipRule="evenodd"/></svg>
                                {d.pending_transactions} pending transaction{Number(d.pending_transactions) !== 1 ? 's' : ''}
                            </span>
                        )}
                        {Number(d.pending_loans) > 0 && (
                            <span className="inline-flex items-center gap-1.5 bg-cyan-400/20 border border-cyan-300/40 text-cyan-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                                <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 00-2 2v4a2 2 0 002 2h12a2 2 0 002-2v-4a2 2 0 00-2-2H6zm6 5a1 1 0 110-2 1 1 0 010 2z"/></svg>
                                {d.pending_loans} loan application{Number(d.pending_loans) !== 1 ? 's' : ''}
                            </span>
                        )}
                        {Number(d.loans_overdue) > 0 && (
                            <span className="inline-flex items-center gap-1.5 bg-red-400/20 border border-red-300/40 text-red-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                                <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd"/></svg>
                                {d.loans_overdue} overdue loan{Number(d.loans_overdue) !== 1 ? 's' : ''}
                            </span>
                        )}
                    </div>
                )}
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <KpiCard label="Total Members" value={Number(d.members_total || d.total_members || 0).toLocaleString()} icon="users" color="emerald" badge={`+${Number(d.members_new_month || 0).toLocaleString()} mo.`} />
                <KpiCard label="Officers & Admins" value={Number(d.staff_total || d.total_officers || 0).toLocaleString()} icon="shield" color="teal" />
                <KpiCard label="Total Savings" value={`₱${Number(d.savings_total || d.total_savings || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`} icon="wallet" color="emerald" />
                <KpiCard label="Share Capital" value={`₱${Number(d.share_capital_total || d.total_share_capital || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`} icon="coins" color="cyan" />
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center gap-3 mb-2">
                        <div className={`w-10 h-10 ${netPos ? 'bg-emerald-50' : 'bg-red-50'} rounded-xl flex items-center justify-center shrink-0`}>
                            <svg className={`w-5 h-5 ${netPos ? 'text-emerald-600' : 'text-red-600'}`} fill="currentColor" viewBox="0 0 20 20">
                                {netPos ? <path fillRule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clipRule="evenodd"/> : <path fillRule="evenodd" d="M12 13a1 1 0 100 2h5a1 1 0 001-1V9a1 1 0 10-2 0v2.586l-4.293-4.293a1 1 0 00-1.414 0L8 9.586 3.707 5.293a1 1 0 00-1.414 1.414l5 5a1 1 0 001.414 0L11 9.414 14.586 13H12z" clipRule="evenodd"/>}
                            </svg>
                        </div>
                        <p className={`text-2xl font-bold ${netPos ? 'text-emerald-700' : 'text-red-600'}`}>{netPos ? '' : '-'}₱{Math.abs(net).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                    </div>
                    <p className="text-xs text-gray-500">Monthly Net for {new Date().toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}</p>
                    <div className="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500 space-y-0.5">
                        <div className="flex justify-between"><span>Deposits</span><span className="font-medium text-gray-700">₱{Number(d.month_deposits || 0).toLocaleString()}</span></div>
                        <div className="flex justify-between"><span>Withdrawals</span><span className="font-medium text-gray-700">₱{Number(d.month_withdrawals || 0).toLocaleString()}</span></div>
                        <div className="flex justify-between"><span>Expenses</span><span className="font-medium text-gray-700">₱{Number(d.month_expenses || 0).toLocaleString()}</span></div>
                    </div>
                </div>
                <KpiCard label="Active Loans" value={Number(d.loans_active || d.active_loans || 0).toLocaleString()} icon="file-invoice" color="blue" detail={`Outstanding ₱${Number(d.loans_outstanding || 0).toLocaleString()}`} />
                <div className={`bg-white rounded-2xl shadow-sm border p-5 ${Number(d.loans_overdue) > 0 ? 'border-red-200 bg-red-50' : 'border-gray-100'}`}>
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center shrink-0"><svg className="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd"/></svg></div>
                        <div className="min-w-0 flex-1"><p className="text-2xl font-bold text-gray-900">{Number(d.loans_overdue || 0).toLocaleString()}</p><p className="text-xs text-gray-500">Overdue Loans</p></div>
                    </div>
                    {Number(d.loans_due_soon) > 0 && <p className="mt-2 pt-2 border-t border-gray-100 text-xs text-amber-600 font-medium">+{Number(d.loans_due_soon).toLocaleString()} due in 14 days</p>}
                </div>
                <div className={`bg-white rounded-2xl shadow-sm border p-5 ${Number(d.pending_transactions) > 0 ? 'border-amber-200 bg-amber-50' : 'border-gray-100'}`}>
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center shrink-0"><svg className="w-5 h-5 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clipRule="evenodd"/></svg></div>
                        <div className="min-w-0 flex-1"><p className="text-2xl font-bold text-gray-900">{Number(d.pending_transactions || 0).toLocaleString()}</p><p className="text-xs text-gray-500">Pending Actions</p></div>
                    </div>
                    <div className="mt-2 pt-2 border-t border-gray-100 text-xs text-gray-500 space-y-0.5">
                        <div className="flex justify-between"><span>Transactions</span><span className="font-medium text-gray-700">{Number(d.pending_transactions || 0).toLocaleString()}</span></div>
                        <div className="flex justify-between"><span>Loan Apps</span><span className="font-medium text-gray-700">{Number(d.pending_loans || 0).toLocaleString()}</span></div>
                    </div>
                </div>
            </div>

            <div className="grid xl:grid-cols-5 gap-6">
                <div className="xl:col-span-3 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100"><h2 className="text-base font-bold text-gray-900">Monthly Cash Flow</h2><p className="text-xs text-gray-500 mt-0.5">Last 6 months</p></div>
                    <div className="p-5">
                        <div className="flex gap-4 mb-3 text-xs font-medium"><span className="inline-flex items-center gap-1.5"><span className="w-3 h-1.5 rounded-full bg-emerald-400"></span>Deposits</span><span className="inline-flex items-center gap-1.5"><span className="w-3 h-1.5 rounded-full bg-rose-400"></span>Outflows</span></div>
                        <div className="space-y-3">
                            {monthlySeries.map((m: any, i: number) => {
                                const inW = maxVal > 0 ? ((Number(m.deposits) || 0) / maxVal) * 100 : 0;
                                const outW = maxVal > 0 ? (((Number(m.withdrawals) || 0) + (Number(m.expenses) || 0)) / maxVal) * 100 : 0;
                                return (
                                    <div key={i} className="flex items-center gap-3">
                                        <span className="w-12 text-xs text-gray-500 shrink-0">{m.label}</span>
                                        <div className="flex-1 space-y-1">
                                            <div className="flex items-center gap-2"><div className="bg-gray-100 rounded-full h-1.5 flex-1"><div className="bg-emerald-400 h-1.5 rounded-full" style={{ width: `${Math.min(100, inW)}%` }}></div></div><span className="text-xs text-emerald-700 w-16 text-right">₱{Number(m.deposits || 0).toLocaleString()}</span></div>
                                            <div className="flex items-center gap-2"><div className="bg-gray-100 rounded-full h-1.5 flex-1"><div className="bg-rose-400 h-1.5 rounded-full" style={{ width: `${Math.min(100, outW)}%` }}></div></div><span className="text-xs text-rose-600 w-16 text-right">₱{(Number(m.withdrawals || 0) + Number(m.expenses || 0)).toLocaleString()}</span></div>
                                        </div>
                                        <span className={`text-xs font-semibold w-16 text-right ${Number(m.net) >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>{Number(m.net) >= 0 ? '' : '-'}₱{Math.abs(Number(m.net)).toLocaleString()}</span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                <div className="xl:col-span-2 space-y-6">
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100"><h2 className="text-base font-bold text-gray-900">Loan Distribution</h2></div>
                        <div className="p-5">
                            {loanSeries.length > 0 && (
                                <div className="h-4 rounded-full flex overflow-hidden mb-4">
                                    {loanSeries.map((s: any, i: number) => (
                                        <div key={i} className={`${s.color} h-full`} style={{ width: `${Math.max(2, s.percent)}%` }} title={`${s.label}: ${s.count}`}></div>
                                    ))}
                                </div>
                            )}
                            <div className="grid grid-cols-2 gap-2">
                                {loanSeries.map((s: any, i: number) => (
                                    <div key={i} className="flex items-center gap-2 text-xs"><span className={`w-2.5 h-2.5 rounded-full shrink-0 ${s.color}`}></span><span className="text-gray-600">{s.label}</span><span className="font-semibold text-gray-700 ml-auto">{s.count} ({s.percent}%)</span></div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100"><h2 className="text-base font-bold text-gray-900">Loan Portfolio</h2></div>
                        <div className="p-5">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="bg-gray-50 rounded-lg px-2.5 py-1.5 text-xs"><span className="text-gray-500">Active</span><p className="font-bold text-gray-800">{Number(d.loans_active || d.active_loans || 0).toLocaleString()}</p></div>
                                <div className="bg-gray-50 rounded-lg px-2.5 py-1.5 text-xs"><span className="text-gray-500">Outstanding</span><p className="font-bold text-gray-800">₱{Number(d.loans_outstanding || 0).toLocaleString()}</p></div>
                                <div className="bg-gray-50 rounded-lg px-2.5 py-1.5 text-xs"><span className="text-gray-500">Due &lt;14d</span><p className="font-bold text-gray-800">{Number(d.loans_due_soon || 0).toLocaleString()}</p></div>
                                <div className="bg-gray-50 rounded-lg px-2.5 py-1.5 text-xs"><span className="text-gray-500">Overdue</span><p className="font-bold text-red-600">{Number(d.loans_overdue || 0).toLocaleString()}</p></div>
                            </div>
                        </div>
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
                                        <tr key={t.id} className="hover:bg-gray-50/50 transition-colors">
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
                        <div className="px-6 py-4 border-b border-gray-100"><h2 className="text-base font-bold text-gray-900">Recent Announcements</h2></div>
                        <div className="p-4">
                            {announcements.length > 0 ? (
                                <div className="space-y-3">
                                    {announcements.map((a: any) => (
                                        <div key={a.id} className="flex gap-3">
                                            <div className="w-1 rounded-full bg-emerald-400 self-stretch shrink-0"></div>
                                            <div><p className="text-sm font-semibold text-gray-800">{a.title}</p><p className="text-xs text-gray-500 line-clamp-2">{a.content}</p><p className="text-xs text-gray-400 mt-1">{a.created_at_diff}</p></div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="py-4 text-center text-sm text-gray-400">No recent announcements</div>
                            )}
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100"><h2 className="text-base font-bold text-gray-900">Recent Expenses</h2></div>
                        <div className="p-4">
                            {expenses.length > 0 ? (
                                <div className="space-y-2">
                                    {expenses.map((e: any) => (
                                        <div key={e.id} className="flex justify-between items-center text-sm"><div><span className="font-medium text-gray-800">{e.category}</span><span className="text-gray-400 ml-2">{e.expense_date}</span></div><span className="font-semibold text-red-600">₱{Number(e.amount).toLocaleString()}</span></div>
                                    ))}
                                </div>
                            ) : (
                                <div className="py-4 text-center text-sm text-gray-400">No expenses recorded</div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function KpiCard({ label, value, icon, color, badge, detail }: { label: string; value: string; icon: string; color: string; badge?: string; detail?: string }) {
    const colorMap: Record<string, { bg: string; text: string; svgBg: string }> = {
        emerald: { bg: 'bg-emerald-50', text: 'text-emerald-600', svgBg: 'bg-emerald-50' },
        teal: { bg: 'bg-teal-50', text: 'text-teal-600', svgBg: 'bg-teal-50' },
        cyan: { bg: 'bg-cyan-50', text: 'text-cyan-600', svgBg: 'bg-cyan-50' },
        blue: { bg: 'bg-blue-50', text: 'text-blue-600', svgBg: 'bg-blue-50' },
    };
    const c = colorMap[color] || colorMap.emerald;
    return (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div className="flex items-center gap-3">
                <div className={`w-10 h-10 ${c.bg} rounded-xl flex items-center justify-center shrink-0`}>
                    <i className={`fas fa-${icon} w-5 h-5 ${c.text}`}></i>
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-2xl font-bold text-gray-900">{value}</p>
                    <p className="text-xs text-gray-500">{label}</p>
                </div>
                {badge && <span className="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full self-start">{badge}</span>}
            </div>
            {detail && <p className="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500">{detail}</p>}
        </div>
    );
}
