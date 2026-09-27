import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';

const peso = (n: number | string | undefined | null, d = 2) =>
    '₱' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
const num = (n: number | string | undefined | null) => (Number(n) || 0).toLocaleString();
const md = (v: string | undefined) => {
    if (!v) return '';
    const t = new Date(v);
    return isNaN(t.getTime()) ? v : t.toLocaleDateString('en-US', { month: 'short', day: '2-digit' });
};
const pendBadge = (type: string) =>
    (
        {
            deposit: 'bg-emerald-100 text-emerald-700',
            withdrawal: 'bg-amber-100 text-amber-700',
            loan_payment: 'bg-blue-100 text-blue-700',
            loan_disbursement: 'bg-cyan-100 text-cyan-700',
        } as Record<string, string>
    )[type] || 'bg-gray-100 text-gray-600';
const pendLabel = (type: string) => (type || '').replace(/[_-]/g, ' ').replace(/\b\w/g, (c: string) => c.toUpperCase());

export default function ManagerOverview() {
    const { user } = useAuth();
    const { data, isLoading } = useQuery({ queryKey: ['overview-manager'], queryFn: () => api.get('/api/dashboard') });
    const d = data?.data || {};
    if (isLoading) return <Skeleton />;

    const net = Number(d.month_net || 0);
    const netPos = net >= 0;
    const totalAlerts = (Number(d.pending_transactions) || 0) + (Number(d.loans_pending) || 0) + (Number(d.loans_overdue) || 0);
    const monthlySeries = d.monthly_series || [];
    const maxVal = Number(d.monthly_max_value) || 1;
    const pendingTx = d.pending_transactions_list || [];
    const newMembers = d.recent_members || [];
    const announcements = d.recent_announcements || [];

    return (
        <div className="space-y-6">
            <div className="relative overflow-hidden bg-gradient-to-br from-blue-700 via-blue-600 to-sky-700 rounded-2xl shadow-xl p-8 text-white">
                <div className="absolute -top-8 -right-8 w-48 h-48 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="absolute -bottom-12 -left-6 w-40 h-40 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p className="text-blue-200 text-sm font-medium uppercase tracking-widest mb-1">Manager</p>
                        <h1 className="text-3xl font-extrabold leading-tight">{d.greeting}, {user?.first_name || 'Manager'}</h1>
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
                        {Number(d.pending_transactions) > 0 && (
                            <span className="inline-flex items-center gap-1.5 bg-amber-400/20 border border-amber-300/40 text-amber-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                                <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clipRule="evenodd"/></svg>
                                {num(d.pending_transactions)} pending transaction{Number(d.pending_transactions) !== 1 ? 's' : ''}
                            </span>
                        )}
                        {Number(d.loans_pending) > 0 && (
                            <span className="inline-flex items-center gap-1.5 bg-sky-400/20 border border-sky-300/40 text-sky-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                                <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 00-2 2v4a2 2 0 002 2h12a2 2 0 002-2v-4a2 2 0 00-2-2H6zm6 5a1 1 0 110-2 1 1 0 010 2z"/></svg>
                                {num(d.loans_pending)} loan application{Number(d.loans_pending) !== 1 ? 's' : ''}
                            </span>
                        )}
                        {Number(d.loans_overdue) > 0 && (
                            <span className="inline-flex items-center gap-1.5 bg-red-400/20 border border-red-300/40 text-red-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                                <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd"/></svg>
                                {num(d.loans_overdue)} overdue loan{Number(d.loans_overdue) !== 1 ? 's' : ''}
                            </span>
                        )}
                    </div>
                )}
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-3">
                        <div className="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                            <i className="fas fa-users w-5 h-5 text-blue-600"></i>
                        </div>
                        <span className="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">+{num(d.members_new_month)} mo.</span>
                    </div>
                    <p className="text-2xl font-extrabold text-gray-900">{num(d.total_members || d.members_total)}</p>
                    <p className="text-xs text-gray-500 mt-0.5">Total Members</p>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-3">
                        <div className="w-10 h-10 bg-sky-50 rounded-xl flex items-center justify-center">
                            <i className="fas fa-wallet w-5 h-5 text-sky-600"></i>
                        </div>
                    </div>
                    <p className="text-2xl font-extrabold text-gray-900">{peso(d.savings_total)}</p>
                    <p className="text-xs text-gray-500 mt-0.5">Total Savings</p>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-3">
                        <div className="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center">
                            <i className="fas fa-file-invoice w-5 h-5 text-indigo-600"></i>
                        </div>
                    </div>
                    <p className="text-2xl font-extrabold text-gray-900">{num(d.loans_active || d.active_loans)}</p>
                    <p className="text-xs text-gray-500 mt-0.5">Active Loans</p>
                    <div className="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500">
                        <div className="flex justify-between"><span>Overdue</span><span className="font-semibold text-red-600">{num(d.loans_overdue)}</span></div>
                    </div>
                </div>

                <div className={`bg-white rounded-2xl shadow-sm border p-5 ${Number(d.pending_transactions) > 0 ? 'border-amber-200' : 'border-gray-100'}`}>
                    <div className="flex items-center justify-between mb-3">
                        <div className={`w-10 h-10 ${Number(d.pending_transactions) > 0 ? 'bg-amber-50' : 'bg-gray-50'} rounded-xl flex items-center justify-center`}>
                            <i className={`fas fa-clock w-5 h-5 ${Number(d.pending_transactions) > 0 ? 'text-amber-600' : 'text-gray-400'}`}></i>
                        </div>
                    </div>
                    <p className={`text-2xl font-extrabold ${Number(d.pending_transactions) > 0 ? 'text-amber-600' : 'text-gray-900'}`}>{num(d.pending_transactions)}</p>
                    <p className="text-xs text-gray-500 mt-0.5">Pending Approvals</p>
                    <div className="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500">
                        <div className="flex justify-between"><span>Loan apps</span><span className="font-semibold text-gray-700">{num(d.loans_pending)}</span></div>
                    </div>
                </div>
            </div>

            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
                    <div>
                        <h2 className="text-base font-bold text-gray-900">Monthly Cash Flow (Last 6 Months)</h2>
                        <p className="text-xs text-gray-500 mt-0.5">Deposits vs. withdrawals + expenses.</p>
                    </div>
                    <div className="flex gap-4 shrink-0">
                        <div className="text-right">
                            <p className="text-xs text-gray-400">This month net</p>
                            <p className={`text-sm font-bold ${netPos ? 'text-emerald-600' : 'text-red-500'}`}>{netPos ? '+' : ''}{peso(net, 0)}</p>
                        </div>
                        <div className="text-right">
                            <p className="text-xs text-gray-400">Deposits</p>
                            <p className="text-sm font-bold text-gray-900">{peso(d.month_deposits, 0)}</p>
                        </div>
                        <div className="text-right">
                            <p className="text-xs text-gray-400">Withdrawals</p>
                            <p className="text-sm font-bold text-gray-900">{peso(d.month_withdrawals, 0)}</p>
                        </div>
                    </div>
                </div>
                <div className="space-y-4">
                    {monthlySeries.length > 0 ? monthlySeries.map((m: any, i: number) => {
                        const mD = Number(m.deposits) || 0;
                        const mO = (Number(m.withdrawals) || 0) + (Number(m.expenses) || 0);
                        const n = Number(m.net) || 0;
                        const wD = maxVal > 0 ? Math.min(100, (mD / maxVal) * 100) : 0;
                        const wO = maxVal > 0 ? Math.min(100, (mO / maxVal) * 100) : 0;
                        return (
                            <div key={i}>
                                <div className="flex items-center justify-between text-xs mb-1">
                                    <span className="font-medium text-gray-700 w-16 shrink-0">{m.label || ''}</span>
                                    <span className={`${n >= 0 ? 'text-emerald-600' : 'text-red-500'} font-semibold ml-auto`}>{n >= 0 ? '+' : ''}{peso(n, 0)}</span>
                                </div>
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-gray-400 w-14 shrink-0">In</span>
                                        <div className="flex-1 bg-gray-100 rounded-full h-1.5"><div className="h-1.5 rounded-full bg-blue-400" style={{ width: `${wD}%` }}></div></div>
                                        <span className="text-xs text-gray-500 w-20 text-right">{peso(mD, 0)}</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-gray-400 w-14 shrink-0">Out</span>
                                        <div className="flex-1 bg-gray-100 rounded-full h-1.5"><div className="h-1.5 rounded-full bg-rose-400" style={{ width: `${wO}%` }}></div></div>
                                        <span className="text-xs text-gray-500 w-20 text-right">{peso(mO, 0)}</span>
                                    </div>
                                </div>
                            </div>
                        );
                    }) : (
                        <p className="text-sm text-gray-500 text-center py-6">No financial data yet.</p>
                    )}
                </div>
                <div className="mt-5 pt-4 border-t border-gray-100 flex flex-wrap gap-4 text-xs text-gray-500">
                    <span className="flex items-center gap-1.5"><span className="w-3 h-1.5 rounded-full bg-blue-400 inline-block"></span> Inflows</span>
                    <span className="flex items-center gap-1.5"><span className="w-3 h-1.5 rounded-full bg-rose-400 inline-block"></span> Outflows</span>
                </div>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div className="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-bold text-gray-900">Pending Transaction Approvals</h2>
                            <p className="text-xs text-gray-500">Items waiting for review</p>
                        </div>
                        {Number(d.pending_transactions) > 0 && (
                            <span className="text-xs font-semibold text-amber-700 bg-amber-100 px-2.5 py-1 rounded-full">{num(d.pending_transactions)} pending</span>
                        )}
                    </div>
                    <div className="overflow-x-auto">
                        {pendingTx.length > 0 ? (
                            <table className="min-w-full text-sm">
                                <thead><tr className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100"><th className="px-6 py-3">Txn #</th><th className="px-6 py-3">Type</th><th className="px-6 py-3">Member</th><th className="px-6 py-3">Amount</th><th className="px-6 py-3">Date</th></tr></thead>
                                <tbody className="divide-y divide-gray-50">
                                    {pendingTx.map((t: any) => (
                                        <tr key={t.id} className="hover:bg-gray-50/50 transition-colors">
                                            <td className="px-6 py-3 font-mono text-xs text-gray-700 truncate max-w-[160px]" title={t.transaction_number}>{t.transaction_number}</td>
                                            <td className="px-6 py-3"><span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${pendBadge(t.transaction_type)}`}>{pendLabel(t.transaction_type)}</span></td>
                                            <td className="px-6 py-3 font-medium text-gray-900 truncate max-w-[220px]" title={t.member_name}>{t.member_name}</td>
                                            <td className="px-6 py-3 font-bold text-gray-900">{peso(t.amount)}</td>
                                            <td className="px-6 py-3 text-xs text-gray-400">{md(t.created_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <div className="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center mb-3">
                                    <svg className="w-6 h-6 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd"/></svg>
                                </div>
                                <p className="text-sm font-medium text-gray-700">All clear!</p>
                                <p className="text-xs text-gray-400 mt-0.5">No pending transactions.</p>
                            </div>
                        )}
                    </div>
                </div>

                <div className="space-y-5">
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                            <h3 className="text-sm font-bold text-gray-900">Newest Members</h3>
                            <span className="text-xs text-gray-400">+{num(d.members_new_month)} this month</span>
                        </div>
                        <div className="p-5 space-y-3">
                            {newMembers.length > 0 ? newMembers.map((m: any) => (
                                <div key={m.id} className="flex items-center gap-3">
                                    <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs shrink-0">
                                        {((m.name || '').split(' ')[0] || 'M').charAt(0).toUpperCase()}
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">{m.name}</p>
                                        <p className="text-xs text-gray-400">{m.created_at}</p>
                                    </div>
                                </div>
                            )) : (
                                <p className="text-sm text-gray-400 text-center py-4">No members yet.</p>
                            )}
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-5 py-3.5 border-b border-gray-100"><h3 className="text-sm font-bold text-gray-900">Recent Announcements</h3></div>
                        <div className="p-5 space-y-3.5">
                            {announcements.length > 0 ? (
                                announcements.map((a: any) => (
                                    <div key={a.id} className="flex gap-3">
                                        <div className="w-1 shrink-0 rounded-full bg-blue-400 self-stretch"></div>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-gray-900 truncate">{a.title}</p>
                                            <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">{a.content}</p>
                                            <p className="text-xs text-gray-400 mt-1">{a.created_at_diff}</p>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-gray-400 text-center py-4">No announcements yet.</p>
                            )}
                        </div>
                    </div>
                </div>
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