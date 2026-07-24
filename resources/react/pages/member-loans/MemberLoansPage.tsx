import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function MemberLoansPage() {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    const { data, isLoading } = useQuery({
        queryKey: ['member-loans', search, page],
        queryFn: () => api.get('/api/history', { params: { category: 'loan_disbursement', search: search || undefined, page, per_page: 15 } }),
    });

    const loans = data?.data?.data ?? [];
    const pagination = data?.data?.pagination;

    return (
        <div className="space-y-6">
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">My Loans</h1>

            <input
                type="text"
                placeholder="Search loans..."
                value={search}
                onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                className="w-full max-w-md rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800"
            />

            {isLoading ? (
                <div className="space-y-3">
                    {[...Array(5)].map((_, i) => <div key={i} className="h-20 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />)}
                </div>
            ) : loans.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-600 dark:bg-slate-800">
                    <i className="fas fa-file-invoice-dollar text-4xl text-slate-300 dark:text-slate-600"></i>
                    <p className="mt-4 text-sm text-slate-500">No loans found</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {loans.map((l: any) => (
                        <div key={l.id} className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-slate-900 dark:text-white">{l.transaction_number}</p>
                                    <p className="text-sm text-slate-500">{l.note || 'Loan disbursement'}</p>
                                </div>
                                <div className="text-right">
                                    <p className="font-bold text-slate-900 dark:text-white">₱{Number(l.amount).toLocaleString()}</p>
                                    <span className={`rounded-full px-2 py-0.5 text-xs ${
                                        l.status === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
                                    }`}>{l.status}</span>
                                </div>
                            </div>
                            <p className="mt-2 text-xs text-slate-400">{l.transaction_date}</p>
                        </div>
                    ))}
                </div>
            )}

            {pagination && pagination.last_page > 1 && (
                <div className="flex items-center justify-center gap-2">
                    <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-50">Previous</button>
                    <span className="text-sm text-slate-500">Page {page} of {pagination.last_page}</span>
                    <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-50">Next</button>
                </div>
            )}
        </div>
    );
}
