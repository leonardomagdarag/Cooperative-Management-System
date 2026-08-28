import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';

const peso = (n: number | string | undefined | null, d = 2) =>
    '₱' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
const num = (n: number | string | undefined | null) => (Number(n) || 0).toLocaleString();
const txBadge = (type: string) =>
    (
        {
            deposit: 'bg-emerald-100 text-emerald-700',
            withdrawal: 'bg-amber-100 text-amber-700',
            loan_payment: 'bg-blue-100 text-blue-700',
            loan_disbursement: 'bg-cyan-100 text-cyan-700',
        } as Record<string, string>
    )[type] || 'bg-gray-100 text-gray-600';
const txLabel = (type: string) => (type || '').replace(/[_-]/g, ' ').replace(/\b\w/g, (c: string) => c.toUpperCase());

export default function TreasurerOverview() {
    const { user } = useAuth();
    const { data, isLoading } = useQuery({ queryKey: ['overview-treasurer'], queryFn: () => api.get('/api/dashboard') });
    const d = data?.data || {};
    if (isLoading) return <Skeleton />;

    const net = Number(d.month_net || 0);
    const netPos = net >= 0;
    const periodLabel = d.period_label || '';
    const monthlySeries = d.monthly_series || [];
    const maxVal = Number(d.monthly_max_value) || 1;
    const transactions = d.recent_transactions || [];
    const expenses = d.recent_expenses || [];
    const announcements = d.recent_announcements || [];

    return (
        <div className="space-y-6">
            <div className="relative overflow-hidden bg-gradient-to-br from-emerald-700 via-green-700 to-emerald-800 rounded-2xl shadow-xl p-8 text-white">
                <div className="absolute -top-8 -right-8 w-48 h-48 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="absolute -bottom-12 -left-6 w-40 h-40 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p className="text-emerald-200 text-sm font-medium uppercase tracking-widest mb-1">Accountant</p>
                        <h1 className="text-3xl font-extrabold leading-tight">{d.greeting}, {user?.first_name || 'Accountant'}</h1>
                        <p className="mt-1 text-emerald-200">Financial overview — {d.period_label || ''}</p>
                    </div>
                    <div className="sm:text-right shrink-0">
                        <p className="text-emerald-200 text-xs uppercase tracking-wide">Today</p>
                        <p className="text-2xl font-bold">{new Date().toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}</p>
                        <p className="text-emerald-200 text-sm">{new Date().toLocaleDateString('en-US', { weekday: 'long' })}</p>
                    </div>
                </div>
                <div className="relative mt-5 flex flex-wrap gap-3">
                    {net > 0 && (
                        <span className="inline-flex items-center gap-1.5 bg-white/15 border border-white/20 text-emerald-50 text-xs font-semibold px-3 py-1.5 rounded-full">
                            <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clipRule="evenodd"/></svg>
                            Net +{peso(net, 0)} this month
                        </span>
                    )}
                    {net < 0 && (
                        <span className="inline-flex items-center gap-1.5 bg-red-500/20 border border-red-300/30 text-red-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                            <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd"/></svg>
                            Net -{peso(Math.abs(net), 0)} this month
                        </span>
                    )}
                    {Number(d.pending_transactions) > 0 && (
                        <span className="inline-flex items-center gap-1.5 bg-amber-400/20 border border-amber-300/40 text-amber-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                            <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clipRule="evenodd"/></svg>
                            {num(d.pending_transactions)} pending transaction{Number(d.pending_transactions) !== 1 ? 's' : ''}
                        </span>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-3">
                        <div className="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center">
                            <i className="fas fa-piggy-bank w-5 h-5 text-emerald-600"></i>
                        </div>
                    </div>
                    <p className="text-2xl font-extrabold text-gray-900">{peso(d.savings_total || d.total_savings)}</p>
                    <p className="text-xs text-gray-500 mt-0.5">Total Savings</p>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-3">
                        <div className="w-10 h-10 bg-cyan-50 rounded-xl flex items-center justify-center">
                            <i className="fas fa-landmark w-5 h-5 text-cyan-600"></i>
                        </div>
                    </div>
                    <p className="text-2xl font-extrabold text-gray-900">{peso(d.share_capital_total || d.total_share_capital)}</p>
                    <p className="text-xs text-gray-500 mt-0.5">Share Capital</p>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-3">
                        <div className="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                            <i className="fas fa-arrow-down w-5 h-5 text-blue-600"></i>
                        </div>
                        {periodLabel && <span className="text-xs text-gray-400">{periodLabel}</span>}
                    </div>
                    <p className="text-2xl font-extrabold text-gray-900">{peso(d.month_deposits)}</p>
                    <p className="text-xs text-gray-500 mt-0.5">Deposits This Month</p>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-3">
                        <div className={`w-10 h-10 ${netPos ? 'bg-emerald-50' : 'bg-red-50'} rounded-xl flex items-center justify-center`}>
                            <i className={`fas fa-chart-line w-5 h-5 ${netPos ? 'text-emerald-600' : 'text-red-500'}`}></i>
                        </div>
                        {periodLabel && <span className="text-xs text-gray-400">{periodLabel}</span>}
                    </div>
                    <p className={`text-2xl font-extrabold ${netPos ? 'text-emerald-700' : 'text-red-600'}`}>{netPos ? '+' : '-'}{peso(Math.abs(net))}</p>
                    <p className="text-xs text-gray-500 mt-0.5">Monthly Net</p>
                    <div className="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500 space-y-0.5">
                        <div className="flex justify-between"><span>Withdrawals</span><span className="font-medium text-gray-700">{peso(d.month_withdrawals, 0)}</span></div>
                        <div className="flex justify-between"><span>Expenses</span><span className="font-medium text-gray-700">{peso(d.month_expenses, 0)}</span></div>
                    </div>
                </div>
            </div>

            <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
                    <div>
                        <h2 className="text-base font-bold text-gray-900">Cash Flow Trend (Last 6 Months)</h2>
                        <p className="text-xs text-gray-500 mt-0.5">Monthly deposits vs. total outflows (withdrawals + expenses).</p>
                    </div>
                    <div className="flex gap-4 shrink-0 text-right">
                        <div>
                            <p className="text-xs text-gray-400">Total Assets</p>
                            <p className="text-sm font-bold text-gray-900">{peso(d.total_assets, 0)}</p>
                        </div>
                    </div>
                </div>
                <div className="space-y-4">
                    {monthlySeries.length > 0 ? monthlySeries.map((m: any, i: number) => {
                        const mD = Number(m.deposits) || 0;
                        const mO = (Number(m.withdrawals) || 0) + (Number(m.expenses) || 0);
                        const mEx = Number(m.expenses) || 0;
                        const n = Number(m.net) || 0;
                        const wD = maxVal > 0 ? Math.min(100, (mD / maxVal) * 100) : 0;
                        const wO = maxVal > 0 ? Math.min(100, (mO / maxVal) * 100) : 0;
                        return (
                            <div key={i}>
                                <div className="flex items-center justify-between text-xs mb-1">
                                    <span className="font-medium text-gray-700 w-16 shrink-0">{m.label || ''}</span>
                                    <div className="flex gap-4 ml-auto">
                                        <span className="text-gray-400">Exp: {peso(mEx, 0)}</span>
                                        <span className={`${n >= 0 ? 'text-emerald-600' : 'text-red-500'} font-semibold`}>{n >= 0 ? '+' : ''}{peso(n, 0)}</span>
                                    </div>
                                </div>
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-gray-400 w-14 shrink-0">Deposits</span>
                                        <div className="flex-1 bg-gray-100 rounded-full h-1.5"><div className="h-1.5 rounded-full bg-emerald-500" style={{ width: `${wD}%` }}></div></div>
                                        <span className="text-xs text-gray-500 w-20 text-right">{peso(mD, 0)}</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-gray-400 w-14 shrink-0">Outflows</span>
                                        <div className="flex-1 bg-gray-100 rounded-full h-1.5"><div className="h-1.5 rounded-full bg-rose-500" style={{ width: `${wO}%` }}></div></div>
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
                    <span className="flex items-center gap-1.5"><span className="w-3 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Deposits</span>
                    <span className="flex items-center gap-1.5"><span className="w-3 h-1.5 rounded-full bg-rose-500 inline-block"></span> Outflows</span>
                </div>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-100">
                        <h2 className="text-base font-bold text-gray-900">Recent Transactions</h2>
                        <p className="text-xs text-gray-500">Latest completed transactions</p>
                    </div>
                    <div className="p-6">
                        {transactions.length > 0 ? (
                            <div className="space-y-2.5">
                                {transactions.map((t: any) => (
                                    <div key={t.id} className="flex items-center justify-between gap-3 py-2 border-b border-gray-50 last:border-0">
                                        <div className="flex items-center gap-3 min-w-0">
                                            <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${txBadge(t.transaction_type)} shrink-0`}>{txLabel(t.transaction_type)}</span>
                                            <span className="text-sm text-gray-700 truncate">{t.member_name}</span>
                                        </div>
                                        <div className="text-right shrink-0">
                                            <p className={`text-sm font-bold ${t.transaction_type === 'deposit' || t.transaction_type === 'loan_payment' ? 'text-emerald-700' : 'text-gray-900'}`}>{peso(t.amount)}</p>
                                            <p className="text-xs text-gray-400">{t.transaction_date}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-400 text-center py-8">No transactions recorded yet.</p>
                        )}
                    </div>
                </div>

                <div className="space-y-5">
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                            <h3 className="text-sm font-bold text-gray-900">Recent Expenses</h3>
                            {periodLabel && <span className="text-xs text-gray-400">{periodLabel}</span>}
                        </div>
                        <div className="p-5">
                            {expenses.length > 0 ? (
                                <div className="space-y-3">
                                    {expenses.map((e: any) => (
                                        <div key={e.id} className="flex items-center justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-gray-900 truncate">{e.category}</p>
                                                <p className="text-xs text-gray-400">{e.expense_date}</p>
                                            </div>
                                            <span className="text-sm font-bold text-red-600 shrink-0">{peso(e.amount)}</span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-gray-400 text-center py-4">No expenses recorded.</p>
                            )}
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-5 py-3.5 border-b border-gray-100">
                            <h3 className="text-sm font-bold text-gray-900">Announcements</h3>
                        </div>
                        <div className="p-5 space-y-3.5">
                            {announcements.length > 0 ? (
                                announcements.map((a: any) => (
                                    <div key={a.id} className="flex gap-3">
                                        <div className="w-1 shrink-0 rounded-full bg-emerald-400 self-stretch"></div>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-gray-900 truncate">{a.title}</p>
                                            <p className="text-xs text-gray-400 mt-1">{a.created_at_diff}</p>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-gray-400 text-center py-4">No announcements.</p>
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