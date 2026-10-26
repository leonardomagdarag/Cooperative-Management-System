import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';

export default function MemberOverview() {
    const { user } = useAuth();
    const { data, isLoading } = useQuery({
        queryKey: ['overview-member', user?.id],
        queryFn: () => api.get('/api/dashboard'),
    });

    const d = data?.data || {};
    const displayName = user?.first_name ? `${user.first_name}${user.last_name ? ` ${user.last_name}` : ''}` : user?.name || d.user_name || 'Member';

    if (isLoading) {
        return (
            <div className="space-y-4">
                <div className="grid gap-4 md:grid-cols-3">
                    {[...Array(3)].map((_, i) => <div key={i} className="h-44 animate-pulse rounded-xl bg-slate-100" />)}
                </div>
            </div>
        );
    }

    const activeLoans = (d.loans || []).filter((l: any) => ['approved', 'active'].includes(l.loan_status));

    return (
        <div className="space-y-6">
            <div className="bg-gradient-to-r from-emerald-600 to-cyan-600 rounded-2xl shadow-xl p-5 sm:p-8 text-white">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-bold mb-2">{d.greeting || 'Welcome back'}, {displayName}!</h1>
                        <p className="text-emerald-50 text-base sm:text-lg">Here's your financial overview for today</p>
                    </div>
                    <div className="text-left sm:text-right">
                        <p className="text-emerald-100 text-sm">Member ID</p>
                        <p className="text-xl sm:text-2xl font-bold font-mono">{d.user_number || user?.user_number || '—'}</p>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow overflow-hidden">
                    <div className="bg-gradient-to-br from-blue-500 to-blue-600 p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div className="bg-white/20 p-3 rounded-lg">
                                <svg className="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" />
                                    <path fillRule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <span className="text-white/80 text-sm font-medium">SAVINGS</span>
                        </div>
                        <div>
                            <p className="text-white/90 text-sm mb-1">Available Balance</p>
                            <p className="text-3xl font-bold text-white">₱{Number(d.total_savings || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                        </div>
                    </div>
                    {d.savings_account ? (
                        <div className="p-4 bg-gray-50">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-gray-600">Account No.</span>
                                <span className="font-mono font-semibold text-gray-900">{d.savings_account.account_number}</span>
                            </div>
                        </div>
                    ) : (
                        <div className="p-4 bg-gray-50">
                            <p className="text-sm text-gray-500 text-center">No savings account yet</p>
                        </div>
                    )}
                </div>

                <div className="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow overflow-hidden">
                    <div className="bg-gradient-to-br from-emerald-500 to-emerald-600 p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div className="bg-white/20 p-3 rounded-lg">
                                <svg className="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <span className="text-white/80 text-sm font-medium">SHARE CAPITAL</span>
                        </div>
                        <div>
                            <p className="text-white/90 text-sm mb-1">Total Investment</p>
                            <p className="text-3xl font-bold text-white">₱{Number(d.total_share_capital || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                        </div>
                    </div>
                    {d.share_capital_account ? (
                        <div className="p-4 bg-gray-50">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-gray-600">Account No.</span>
                                <span className="font-mono font-semibold text-gray-900">{d.share_capital_account.account_number}</span>
                            </div>
                        </div>
                    ) : (
                        <div className="p-4 bg-gray-50">
                            <p className="text-sm text-gray-500 text-center">No share capital yet</p>
                        </div>
                    )}
                </div>

                <div className="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow overflow-hidden">
                    <div className="bg-gradient-to-br from-orange-500 to-red-500 p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div className="bg-white/20 p-3 rounded-lg">
                                <svg className="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <span className="text-white/80 text-sm font-medium">TOTAL LOANS</span>
                        </div>
                        <div>
                            <p className="text-white/90 text-sm mb-1">Outstanding Balance</p>
                            <p className="text-3xl font-bold text-white">₱{Number(d.total_loan_balance || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                        </div>
                    </div>
                    <div className="p-4 bg-gray-50">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-gray-600">Active Loans</span>
                            <span className="font-semibold text-gray-900">{activeLoans.length}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 bg-white rounded-xl shadow-lg overflow-hidden">
                    <div className="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                        <h2 className="text-xl font-bold text-gray-800 flex items-center gap-2">
                            <svg className="w-6 h-6 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clipRule="evenodd" />
                            </svg>
                            Active Loans
                        </h2>
                    </div>
                    <div className="p-6">
                        {activeLoans.length > 0 ? (
                            <div className="space-y-4">
                                {activeLoans.map((l: any) => (
                                    <div key={l.id} className="bg-gradient-to-r from-gray-50 to-white border-2 border-gray-200 rounded-lg p-5 hover:shadow-md transition-shadow">
                                        <div className="flex items-start justify-between mb-3">
                                            <div className="flex-1">
                                                <h3 className="text-lg font-bold text-gray-900">{l.loan_type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())} Loan</h3>
                                                <p className="text-sm text-gray-500">Loan No: <span className="font-mono">{l.loan_number}</span></p>
                                            </div>
                                            <span className={`px-3 py-1 text-xs font-semibold rounded-full ${['approved', 'active'].includes(l.loan_status) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}`}>
                                                {l.loan_status.charAt(0).toUpperCase() + l.loan_status.slice(1)}
                                            </span>
                                            {l.is_overdue && (
                                                <span className="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                    Overdue{l.days_overdue > 0 ? ` · ${l.days_overdue} days` : ''}
                                                </span>
                                            )}
                                        </div>
                                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                            <div>
                                                <p className="text-xs text-gray-600 mb-1">Principal Amount</p>
                                                <p className="text-lg font-bold text-gray-900">₱{Number(l.loan_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                                            </div>
                                            <div>
                                                <p className="text-xs text-gray-600 mb-1">Remaining Balance</p>
                                                <p className="text-lg font-bold text-red-600">₱{Number(l.remaining_balance || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                                            </div>
                                            <div>
                                                <p className="text-xs text-gray-600 mb-1">Maturity Date</p>
                                                <p className="text-sm font-semibold text-gray-900">{l.due_date || 'N/A'}</p>
                                            </div>
                                        </div>
                                        <div className="mt-4">
                                            <div className="flex justify-between text-xs text-gray-600 mb-1">
                                                <span>Payment Progress</span>
                                                <span>{Number(l.progress || 0).toFixed(1)}%</span>
                                            </div>
                                            <div className="w-full bg-gray-200 rounded-full h-2">
                                                <div className="bg-gradient-to-r from-cyan-500 to-emerald-500 h-2 rounded-full transition-all" style={{ width: `${Math.min(100, Number(l.progress || 0))}%` }}></div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-12">
                                <svg className="w-20 h-20 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <h3 className="text-lg font-semibold text-gray-600 mb-2">No Active Loans</h3>
                                <p className="text-gray-500">You currently have no active loans</p>
                            </div>
                        )}
                    </div>
                </div>

                <div className="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div className="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                        <h2 className="text-xl font-bold text-gray-800 flex items-center gap-2">
                            <svg className="w-6 h-6 text-cyan-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
                            </svg>
                            Announcements
                        </h2>
                    </div>
                    <div className="p-4 max-h-96 overflow-y-auto">
                        {d.announcements && d.announcements.length > 0 ? (
                            <div className="space-y-3">
                                {d.announcements.map((a: any) => (
                                    <div key={a.id} className="bg-emerald-50/70 border-l-4 border-emerald-500 p-4 rounded-r-lg">
                                        <div className="flex items-start gap-3">
                                            <svg className="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                                            </svg>
                                            <div className="flex-1">
                                                <h4 className="text-sm font-bold text-gray-900 mb-1">{a.title}</h4>
                                                <p className="text-xs text-gray-700 whitespace-pre-line">{a.content?.substring(0, 150)}{a.content?.length > 150 ? '...' : ''}</p>
                                                <div className="flex items-center gap-3 mt-2 text-xs text-gray-500">
                                                    {a.creator_name && <span>By {a.creator_name}</span>}
                                                    <span>{a.created_at_diff}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-8">
                                <svg className="w-16 h-16 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <p className="text-sm text-gray-500">No announcements</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
