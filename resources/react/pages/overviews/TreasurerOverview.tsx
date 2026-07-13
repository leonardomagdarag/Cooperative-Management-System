import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function TreasurerOverview() {
    const { data, isLoading } = useQuery({ queryKey: ['overview-treasurer'], queryFn: () => api.get('/api/dashboard') });
    const d = data?.data || {};
    if (isLoading) return <Skeleton />;

    const net = Number(d.month_net || 0);
    const netPos = net >= 0;
    const monthlySeries = d.monthly_series || [];
    const maxVal = Number(d.monthly_max_value) || 1;
    const transactions = d.recent_transactions || [];
    const expenses = d.recent_expenses || [];
    const announcements = d.recent_announcements || [];

    return (
        <div className="space-y-6">
            <div className="relative overflow-hidden bg-gradient-to-br from-emerald-700 via-green-700 to-emerald-800 rounded-2xl shadow-xl p-8 text-white">
                <div className="absolute -top-10 -right-10 w-52 h-52 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="absolute -bottom-14 -left-8 w-44 h-44 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p className="text-emerald-200 text-sm font-medium uppercase tracking-widest mb-1">Accountant</p>
                        <h1 className="text-3xl font-extrabold leading-tight">{d.greeting}</h1>
                        <p className="mt-1 text-emerald-200">Financial overview — {d.period_label || ''}</p>
                    </div>
                    <div className="sm:text-right shrink-0">
                        <p className="text-emerald-200 text-xs uppercase tracking-wide">Today</p>
                        <p className="text-2xl font-bold">{new Date().toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}</p>
                        <p className="text-emerald-200 text-sm">{new Date().toLocaleDateString('en-US', { weekday: 'long' })}</p>
                    </div>
                </div>
                <div className="relative mt-5 flex flex-wrap gap-3">
                    <span className={`inline-flex items-center gap-1.5 ${netPos ? 'bg-white/15 border border-white/20' : 'bg-red-400/20 border border-red-300/40'} text-xs font-semibold px-3 py-1.5 rounded-full ${netPos ? 'text-white' : 'text-red-100'}`}>
                        Monthly net: {netPos ? '' : '-'}₱{Math.abs(net).toLocaleString()}
                    </span>
                    {Number(d.pending_transactions) > 0 && (
                        <span className="inline-flex items-center gap-1.5 bg-amber-400/20 border border-amber-300/40 text-amber-100 text-xs font-semibold px-3 py-1.5 rounded-full">{d.pending_transactions} pending</span>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <KpiCard label="Total Savings" value={`₱${Number(d.savings_total || d.total_savings || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`} icon="piggy-bank" color="emerald" />
                <KpiCard label="Share Capital" value={`₱${Number(d.share_capital_total || d.total_share_capital || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`} icon="landmark" color="cyan" />
                <KpiCard label="Deposits This Month" value={`₱${Number(d.month_deposits || 0).toLocaleString()}`} icon="arrow-down" color="blue" badge={d.period_label} />
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center gap-3 mb-2">
                        <div className={`w-10 h-10 ${netPos ? 'bg-emerald-50' : 'bg-red-50'} rounded-xl flex items-center justify-center shrink-0`}>
                            <i className={`fas fa-chart-line ${netPos ? 'text-emerald-600' : 'text-red-600'}`}></i>
                        </div>
                        <p className={`text-2xl font-bold ${netPos ? 'text-emerald-700' : 'text-red-600'}`}>{netPos ? '' : '-'}₱{Math.abs(net).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                    </div>
                    <p className="text-xs text-gray-500 mb-2">Monthly Net</p>
                    <div className="pt-2 border-t border-gray-50 text-xs text-gray-500 space-y-0.5">
                        <div className="flex justify-between"><span>Withdrawals</span><span>₱{Number(d.month_withdrawals || 0).toLocaleString()}</span></div>
                        <div className="flex justify-between"><span>Expenses</span><span>₱{Number(d.month_expenses || 0).toLocaleString()}</span></div>
                    </div>
                </div>
            </div>

            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <div><h2 className="text-base font-bold text-gray-900">Cash Flow Trend</h2><p className="text-xs text-gray-500 mt-0.5">Total Assets: ₱{Number(d.total_assets || 0).toLocaleString()}</p></div>
                </div>
                <div className="p-5">
                    <div className="flex gap-4 mb-3 text-xs font-medium"><span className="inline-flex items-center gap-1.5"><span className="w-3 h-1.5 rounded-full bg-emerald-500"></span>Deposits</span><span className="inline-flex items-center gap-1.5"><span className="w-3 h-1.5 rounded-full bg-rose-500"></span>Outflows</span></div>
                    <div className="space-y-3">
                        {monthlySeries.map((m: any, i: number) => {
                            const inW = maxVal > 0 ? ((Number(m.deposits) || 0) / maxVal) * 100 : 0;
                            const outW = maxVal > 0 ? (((Number(m.withdrawals) || 0) + (Number(m.expenses) || 0)) / maxVal) * 100 : 0;
                            return (
                                <div key={i} className="flex items-center gap-3">
                                    <span className="w-12 text-xs text-gray-500 shrink-0">{m.label}</span>
                                    <div className="flex-1 space-y-1">
                                        <div className="flex items-center gap-2"><div className="bg-gray-100 rounded-full h-1.5 flex-1"><div className="bg-emerald-500 h-1.5 rounded-full" style={{ width: `${Math.min(100, inW)}%` }}></div></div><span className="text-xs text-emerald-700 w-14 text-right">₱{Number(m.deposits || 0).toLocaleString()}</span></div>
                                        <div className="flex items-center gap-2"><div className="bg-gray-100 rounded-full h-1.5 flex-1"><div className="bg-rose-500 h-1.5 rounded-full" style={{ width: `${Math.min(100, outW)}%` }}></div></div><span className="text-xs text-rose-600 w-14 text-right">₱{Number(m.expenses || 0).toLocaleString()}</span></div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            <div className="grid xl:grid-cols-2 gap-6">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100"><h2 className="text-base font-bold text-gray-900">Recent Transactions</h2></div>
                    <div className="p-4 space-y-2">
                        {transactions.length > 0 ? transactions.map((t: any) => (
                            <div key={t.id} className="flex justify-between items-center text-sm">
                                <div>
                                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${t.transaction_type === 'deposit' || t.transaction_type === 'loan_payment' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'}`}>{t.transaction_type?.replace(/_/g, ' ')}</span>
                                    <span className="text-gray-400 ml-2">{t.member_name}</span>
                                </div>
                                <div className="text-right">
                                    <span className={`font-semibold ${t.transaction_type === 'deposit' || t.transaction_type === 'loan_payment' ? 'text-emerald-600' : 'text-gray-600'}`}>₱{Number(t.amount).toLocaleString()}</span>
                                    <span className="text-xs text-gray-400 ml-2">{t.transaction_date}</span>
                                </div>
                            </div>
                        )) : <div className="py-4 text-center text-sm text-gray-400">No recent transactions</div>}
                    </div>
                </div>

                <div className="space-y-6">
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100"><h2 className="text-base font-bold text-gray-900">Recent Expenses</h2></div>
                        <div className="p-4 space-y-2">
                            {expenses.length > 0 ? expenses.map((e: any) => (
                                <div key={e.id} className="flex justify-between items-center text-sm"><span className="text-gray-800">{e.category || e.description}</span><div className="text-right"><span className="font-semibold text-red-600">₱{Number(e.amount).toLocaleString()}</span><span className="text-xs text-gray-400 ml-2">{e.expense_date}</span></div></div>
                            )) : <div className="py-4 text-center text-sm text-gray-400">No expenses recorded</div>}
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100"><h2 className="text-base font-bold text-gray-900">Announcements</h2></div>
                        <div className="p-4 space-y-3">
                            {announcements.length > 0 ? announcements.map((a: any) => (
                                <div key={a.id} className="flex gap-3"><div className="w-1 rounded-full bg-emerald-400 self-stretch shrink-0"></div><div><p className="text-sm font-semibold text-gray-800">{a.title}</p><p className="text-xs text-gray-500 line-clamp-2">{a.content}</p><p className="text-xs text-gray-400 mt-1">{a.created_at_diff}</p></div></div>
                            )) : <div className="py-4 text-center text-sm text-gray-400">No announcements</div>}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function KpiCard({ label, value, icon, color, badge }: { label: string; value: string; icon: string; color: string; badge?: string }) {
    const cols: Record<string, string> = { emerald: 'bg-emerald-50 text-emerald-600', cyan: 'bg-cyan-50 text-cyan-600', blue: 'bg-blue-50 text-blue-600' };
    const c = cols[color] || cols.emerald;
    return (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div className="flex items-center gap-3">
                <div className={`w-10 h-10 ${c.split(' ')[0]} rounded-xl flex items-center justify-center shrink-0`}><i className={`fas fa-${icon} ${c.split(' ')[1]}`}></i></div>
                <div className="min-w-0 flex-1"><p className="text-2xl font-bold text-gray-900">{value}</p><p className="text-xs text-gray-500">{label}</p></div>
                {badge && <span className="text-xs font-medium bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{badge}</span>}
            </div>
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
