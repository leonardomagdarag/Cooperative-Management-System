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
const titleCase = (v: string) => (v || '').replace(/[_-]/g, ' ').replace(/\b\w/g, (c: string) => c.toUpperCase());

export default function LoanOverview() {
    const { user } = useAuth();
    const { data, isLoading } = useQuery({ queryKey: ['overview-loan'], queryFn: () => api.get('/api/dashboard') });
    const d = data?.data || {};
    if (isLoading) return <Skeleton />;

    const pending = Number(d.pending_loans || d.loans_pending || 0);
    const overdue = Number(d.loans_overdue || d.overdue_loans || 0);
    const dueSoon = Number(d.loans_due_soon || 0);
    const showChips = pending > 0 || overdue > 0;
    const pendingLoans = d.pending_loans_list || [];
    const overdueLoans = d.overdue_loans_list || [];
    const announcements = d.recent_announcements || [];
    const loanSeries = d.loan_status_series || [];
    const loanTotal = loanSeries.reduce((sum: number, r: any) => sum + (Number(r.count) || 0), 0);
    const periodLabel = new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

    return (
        <div className="space-y-6">
            <div className="relative overflow-hidden bg-gradient-to-br from-amber-600 via-orange-600 to-amber-700 rounded-2xl shadow-xl p-8 text-white">
                <div className="absolute -top-8 -right-8 w-48 h-48 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="absolute -bottom-12 -left-6 w-40 h-40 bg-white/5 rounded-full pointer-events-none"></div>
                <div className="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p className="text-amber-200 text-sm font-medium uppercase tracking-widest mb-1">Loan Officer</p>
                        <h1 className="text-3xl font-extrabold leading-tight">{d.greeting}, {user?.first_name || 'Officer'}</h1>
                        <p className="mt-1 text-amber-200">Loan portfolio overview — {periodLabel}</p>
                    </div>
                    <div className="sm:text-right shrink-0">
                        <p className="text-amber-200 text-xs uppercase tracking-wide">Today</p>
                        <p className="text-2xl font-bold">{new Date().toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })}</p>
                        <p className="text-amber-200 text-sm">{new Date().toLocaleDateString('en-US', { weekday: 'long' })}</p>
                    </div>
                </div>
                {showChips && (
                    <div className="relative mt-5 flex flex-wrap gap-3">
                        {pending > 0 && (
                            <span className="inline-flex items-center gap-1.5 bg-white/15 border border-white/20 text-amber-50 text-xs font-semibold px-3 py-1.5 rounded-full">
                                <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clipRule="evenodd"/></svg>
                                {num(pending)} application{pending !== 1 ? 's' : ''} awaiting review
                            </span>
                        )}
                        {overdue > 0 && (
                            <span className="inline-flex items-center gap-1.5 bg-red-500/20 border border-red-300/30 text-red-100 text-xs font-semibold px-3 py-1.5 rounded-full">
                                <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd"/></svg>
                                {num(overdue)} overdue loan{overdue !== 1 ? 's' : ''}
                            </span>
                        )}
                        {dueSoon > 0 && (
                            <span className="inline-flex items-center gap-1.5 bg-white/15 border border-white/20 text-amber-50 text-xs font-semibold px-3 py-1.5 rounded-full">
                                <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
                                {num(dueSoon)} due within 14 days
                            </span>
                        )}
                    </div>
                )}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div className={`bg-white rounded-2xl shadow-sm border p-5 ${pending > 0 ? 'border-amber-200' : 'border-gray-100'}`}>
                    <div className="flex items-center justify-between mb-3">
                        <div className={`w-10 h-10 ${pending > 0 ? 'bg-amber-50' : 'bg-gray-50'} rounded-xl flex items-center justify-center`}>
                            <i className={`fas fa-clock w-5 h-5 ${pending > 0 ? 'text-amber-600' : 'text-gray-400'}`}></i>
                        </div>
                    </div>
                    <p className={`text-2xl font-extrabold ${pending > 0 ? 'text-amber-600' : 'text-gray-900'}`}>{num(pending)}</p>
                    <p className="text-xs text-gray-500 mt-0.5">Pending Applications</p>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-3">
                        <div className="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center">
                            <i className="fas fa-file-invoice w-5 h-5 text-emerald-600"></i>
                        </div>
                    </div>
                    <p className="text-2xl font-extrabold text-gray-900">{num(d.active_loans || d.loans_active)}</p>
                    <p className="text-xs text-gray-500 mt-0.5">Active Loans</p>
                    <div className="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500">
                        <div className="flex justify-between"><span>Outstanding</span><span className="font-medium text-gray-700">{peso(d.outstanding_amount || d.loans_outstanding, 0)}</span></div>
                    </div>
                </div>

                <div className={`bg-white rounded-2xl shadow-sm border p-5 ${overdue > 0 ? 'border-red-200' : 'border-gray-100'}`}>
                    <div className="flex items-center justify-between mb-3">
                        <div className={`w-10 h-10 ${overdue > 0 ? 'bg-red-50' : 'bg-gray-50'} rounded-xl flex items-center justify-center`}>
                            <i className={`fas fa-exclamation-triangle w-5 h-5 ${overdue > 0 ? 'text-red-500' : 'text-gray-400'}`}></i>
                        </div>
                    </div>
                    <p className={`text-2xl font-extrabold ${overdue > 0 ? 'text-red-600' : 'text-gray-900'}`}>{num(overdue)}</p>
                    <p className="text-xs text-gray-500 mt-0.5">Overdue Loans</p>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <div className="flex items-center justify-between mb-3">
                        <div className="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center">
                            <i className="fas fa-calendar w-5 h-5 text-orange-500"></i>
                        </div>
                    </div>
                    <p className="text-2xl font-extrabold text-gray-900">{num(dueSoon)}</p>
                    <p className="text-xs text-gray-500 mt-0.5">Due in 14 Days</p>
                    <div className="mt-2 pt-2 border-t border-gray-50 text-xs text-gray-500">
                        <div className="flex justify-between"><span>Total disbursed</span><span className="font-medium text-gray-700">{peso(d.loans_disbursed, 0)}</span></div>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div className="xl:col-span-2 space-y-6">
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h2 className="text-base font-bold text-gray-900">Pending Loan Applications</h2>
                                <p className="text-xs text-gray-500">Applications awaiting review or approval</p>
                            </div>
                            {pending > 0 && <span className="text-xs font-semibold text-amber-700 bg-amber-100 px-2.5 py-1 rounded-full">{num(pending)} pending</span>}
                        </div>
                        <div className="overflow-x-auto">
                            {pendingLoans.length > 0 ? (
                                <table className="min-w-[500px] w-full text-sm">
                                    <thead><tr className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100"><th className="px-6 py-3">Member</th><th className="px-6 py-3">Product</th><th className="px-6 py-3">Amount</th><th className="px-6 py-3">Applied</th></tr></thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {pendingLoans.map((l: any) => (
                                            <tr key={l.id} className="hover:bg-gray-50/50 transition-colors">
                                                <td className="px-6 py-3 font-medium text-gray-900 truncate max-w-[220px]" title={l.member_name}>{l.member_name}</td>
                                                <td className="px-6 py-3 text-gray-600 text-xs truncate max-w-[180px]" title={l.loan_type}>{titleCase(l.loan_type)}</td>
                                                <td className="px-6 py-3 font-bold text-gray-900">{peso(l.loan_amount)}</td>
                                                <td className="px-6 py-3 text-xs text-gray-400">{md(l.created_at)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-12 text-center">
                                    <div className="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center mb-3">
                                        <svg className="w-6 h-6 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd"/></svg>
                                    </div>
                                    <p className="text-sm font-medium text-gray-700">No pending applications</p>
                                </div>
                            )}
                        </div>
                    </div>

                    {overdueLoans.length > 0 && (
                        <div className="bg-white rounded-2xl shadow-sm border border-red-200 overflow-hidden">
                            <div className="px-6 py-4 border-b border-red-100 flex items-center justify-between bg-red-50/50">
                                <div>
                                    <h2 className="text-base font-bold text-red-700">Overdue Loans</h2>
                                    <p className="text-xs text-red-500">Past due date — follow-up required</p>
                                </div>
                                <span className="text-xs font-semibold text-red-700 bg-red-100 px-2.5 py-1 rounded-full">{num(overdue)} overdue</span>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead><tr className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100"><th className="px-6 py-3">Member</th><th className="px-6 py-3">Amount</th><th className="px-6 py-3">Due Date</th><th className="px-6 py-3">Days Overdue</th></tr></thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {overdueLoans.map((l: any) => (
                                            <tr key={l.id} className="hover:bg-red-50/30 transition-colors">
                                                <td className="px-6 py-3 font-medium text-gray-900 truncate max-w-[220px]" title={l.member_name}>{l.member_name}</td>
                                                <td className="px-6 py-3 font-bold text-gray-900">{peso(l.loan_amount)}</td>
                                                <td className="px-6 py-3 text-xs text-red-600">{l.due_date}</td>
                                                <td className="px-6 py-3">
                                                    <span className="text-xs font-semibold text-red-700 bg-red-100 px-2 py-0.5 rounded-full">{num(l.days_overdue)}d</span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>

                <div className="space-y-5">
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-sm font-bold text-gray-900">Loan Distribution</h3>
                            <span className="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-lg">{num(loanTotal)} total</span>
                        </div>
                        <div className="space-y-3">
                            {loanSeries.length > 0 ? loanSeries.map((s: any, i: number) => (
                                <div key={i}>
                                    <div className="flex items-center justify-between text-xs mb-1">
                                        <span className="font-medium text-gray-700">{s.label}</span>
                                        <span className="text-gray-500">{num(s.count)} <span className="text-gray-400">({Number(s.percent).toFixed(1)}%)</span></span>
                                    </div>
                                    <div className="w-full bg-gray-100 rounded-full h-2">
                                        <div className={`h-2 rounded-full ${s.color}`} style={{ width: `${Math.min(100, Number(s.percent))}%` }}></div>
                                    </div>
                                </div>
                            )) : (
                                <p className="text-sm text-gray-400 text-center py-4">No loans yet.</p>
                            )}
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-5 py-3.5 border-b border-gray-100"><h3 className="text-sm font-bold text-gray-900">Announcements</h3></div>
                        <div className="p-5 space-y-3.5">
                            {announcements.length > 0 ? (
                                announcements.map((a: any) => (
                                    <div key={a.id} className="flex gap-3">
                                        <div className="w-1 shrink-0 rounded-full bg-amber-400 self-stretch"></div>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-gray-900 truncate">{a.title}</p>
                                            <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">{a.content}</p>
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