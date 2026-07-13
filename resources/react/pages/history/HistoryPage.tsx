import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getHistory, Transaction } from '../../api/history';

export default function HistoryPage() {
    const [category, setCategory] = useState('all');
    const [status, setStatus] = useState('all');
    const [dateFilter, setDateFilter] = useState('all');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [direction, setDirection] = useState('desc');

    const { data, isLoading, refetch } = useQuery({
        queryKey: ['history', category, status, dateFilter, search, page, direction],
        queryFn: () => getHistory({
            category: category === 'all' ? undefined : category,
            status: status === 'all' ? undefined : status,
            date: dateFilter === 'all' ? undefined : dateFilter,
            search: search || undefined,
            page,
            per_page: 15,
            direction,
        }),
    });

    const transactions: Transaction[] = data?.data?.data ?? [];
    const pagination = data?.data?.pagination;

    const getTypeColor = (type: string) => {
        switch (type) {
            case 'deposit': return 'bg-emerald-100 text-emerald-700';
            case 'withdrawal': return 'bg-red-100 text-red-700';
            case 'loan_payment': return 'bg-blue-100 text-blue-700';
            case 'loan_disbursement': return 'bg-amber-100 text-amber-700';
            default: return 'bg-gray-100 text-gray-700';
        }
    };

    const getStatusColor = (s: string) => {
        switch (s) {
            case 'completed': case 'approved': return 'bg-emerald-100 text-emerald-700';
            case 'pending': return 'bg-amber-100 text-amber-700';
            case 'rejected': return 'bg-red-100 text-red-700';
            default: return 'bg-gray-100 text-gray-700';
        }
    };

    const getTypeIcon = (type: string) => {
        switch (type) {
            case 'deposit': return 'fas fa-plus-circle text-emerald-600';
            case 'withdrawal': return 'fas fa-minus-circle text-red-600';
            case 'loan_payment': return 'fas fa-hand-holding-usd text-blue-600';
            case 'loan_disbursement': return 'fas fa-file-invoice-dollar text-amber-600';
            default: return 'fas fa-receipt text-gray-600';
        }
    };

    return (
        <div className="space-y-6">
            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">Transaction History</h1>
                    <button
                        onClick={() => refetch()}
                        className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50"
                    >
                        <i className="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
                <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <input
                        type="text"
                        placeholder="Search transactions..."
                        value={search}
                        onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                        className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 focus:outline-none"
                    />
                    <select value={dateFilter} onChange={e => { setDateFilter(e.target.value); setPage(1); }} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 focus:outline-none">
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="year">This Year</option>
                    </select>
                    <select value={category} onChange={e => { setCategory(e.target.value); setPage(1); }} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 focus:outline-none">
                        <option value="all">All Types</option>
                        <option value="deposit">Deposits</option>
                        <option value="withdrawal">Withdrawals</option>
                        <option value="loan_payment">Loan Payments</option>
                        <option value="loan_disbursement">Loan Disbursements</option>
                    </select>
                </div>
                <div className="mt-3 flex flex-wrap items-center gap-3">
                    <select value={status} onChange={e => { setStatus(e.target.value); setPage(1); }} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 focus:outline-none">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <button
                        onClick={() => setDirection(d => d === 'desc' ? 'asc' : 'desc')}
                        className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm hover:bg-gray-50"
                    >
                        <i className={`fas fa-sort-${direction === 'desc' ? 'down' : 'up'} mr-1`}></i>
                        {direction === 'desc' ? 'Newest' : 'Oldest'}
                    </button>
                </div>
            </div>

            {isLoading ? (
                <div className="space-y-3">
                    {[...Array(5)].map((_, i) => (
                        <div key={i} className="h-20 animate-pulse rounded-xl bg-gray-100" />
                    ))}
                </div>
            ) : transactions.length === 0 ? (
                <div className="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center">
                    <i className="fas fa-receipt text-4xl text-gray-300"></i>
                    <p className="mt-4 text-sm text-gray-500">No transactions found</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {transactions.map(t => (
                        <div key={t.id} className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-shadow hover:shadow">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100">
                                        <i className={`${getTypeIcon(t.transaction_type)} text-lg`}></i>
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium text-gray-900">{t.transaction_number}</span>
                                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${getTypeColor(t.transaction_type)}`}>
                                                {t.transaction_type.replace('_', ' ')}
                                            </span>
                                        </div>
                                        <p className="text-xs text-gray-500">{t.transaction_date}</p>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <p className="font-bold text-gray-900">&#8369;{Number(t.amount).toLocaleString()}</p>
                                    <div className="mt-1 flex items-center gap-2 justify-end">
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${getStatusColor(t.status)}`}>
                                            {t.status}
                                        </span>
                                    </div>
                                    {t.note && <p className="mt-1 max-w-[200px] truncate text-xs text-gray-400">{t.note}</p>}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {pagination && pagination.last_page > 1 && (
                <div className="flex items-center justify-center gap-2">
                    <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm hover:bg-gray-50 disabled:opacity-50">Previous</button>
                    <span className="text-sm text-gray-500">Page {page} of {pagination.last_page}</span>
                    <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm hover:bg-gray-50 disabled:opacity-50">Next</button>
                </div>
            )}
        </div>
    );
}
