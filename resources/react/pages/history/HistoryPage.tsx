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

    const { data, isLoading } = useQuery({
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
            default: return 'bg-slate-100 text-slate-700';
        }
    };

    const getStatusColor = (s: string) => {
        switch (s) {
            case 'completed': case 'approved': return 'bg-emerald-100 text-emerald-700';
            case 'pending': return 'bg-amber-100 text-amber-700';
            case 'rejected': return 'bg-red-100 text-red-700';
            default: return 'bg-slate-100 text-slate-700';
        }
    };

    return (
        <div className="space-y-6">
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Transaction History</h1>

            {/* Filters */}
            <div className="flex flex-wrap gap-3">
                <input
                    type="text"
                    placeholder="Search transactions..."
                    value={search}
                    onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800"
                />
                <select value={category} onChange={e => { setCategory(e.target.value); setPage(1); }} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800">
                    <option value="all">All Types</option>
                    <option value="deposit">Deposits</option>
                    <option value="withdrawal">Withdrawals</option>
                    <option value="loan_payment">Loan Payments</option>
                    <option value="loan_disbursement">Loan Disbursements</option>
                </select>
                <select value={status} onChange={e => { setStatus(e.target.value); setPage(1); }} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800">
                    <option value="all">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <select value={dateFilter} onChange={e => { setDateFilter(e.target.value); setPage(1); }} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800">
                    <option value="all">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="year">This Year</option>
                </select>
                <button
                    onClick={() => setDirection(d => d === 'desc' ? 'asc' : 'desc')}
                    className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:hover:bg-slate-700"
                >
                    <i className={`fas fa-sort-${direction === 'desc' ? 'down' : 'up'} mr-1`}></i>
                    {direction === 'desc' ? 'Newest' : 'Oldest'}
                </button>
            </div>

            {/* Table */}
            {isLoading ? (
                <div className="space-y-3">
                    {[...Array(5)].map((_, i) => (
                        <div key={i} className="h-14 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
                    ))}
                </div>
            ) : transactions.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-600 dark:bg-slate-800">
                    <i className="fas fa-receipt text-4xl text-slate-300 dark:text-slate-600"></i>
                    <p className="mt-4 text-sm text-slate-500">No transactions found</p>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                            <tr>
                                <th className="px-4 py-3 font-medium text-slate-500">Transaction #</th>
                                <th className="px-4 py-3 font-medium text-slate-500">Type</th>
                                <th className="px-4 py-3 font-medium text-slate-500">Amount</th>
                                <th className="px-4 py-3 font-medium text-slate-500">Status</th>
                                <th className="px-4 py-3 font-medium text-slate-500">Date</th>
                                <th className="px-4 py-3 font-medium text-slate-500">Note</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                            {transactions.map(t => (
                                <tr key={t.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">{t.transaction_number}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${getTypeColor(t.transaction_type)}`}>
                                            {t.transaction_type.replace('_', ' ')}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">₱{Number(t.amount).toLocaleString()}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${getStatusColor(t.status)}`}>
                                            {t.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-500">{t.transaction_date}</td>
                                    <td className="px-4 py-3 text-slate-500 max-w-[200px] truncate">{t.note || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Pagination */}
            {pagination && pagination.last_page > 1 && (
                <div className="flex items-center justify-center gap-2">
                    <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:hover:bg-slate-700">Previous</button>
                    <span className="text-sm text-slate-500">Page {page} of {pagination.last_page}</span>
                    <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:hover:bg-slate-700">Next</button>
                </div>
            )}
        </div>
    );
}
