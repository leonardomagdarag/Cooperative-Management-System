import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

export default function LoansPage() {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [selectedLoan, setSelectedLoan] = useState<any>(null);
    const [showEncodeModal, setShowEncodeModal] = useState(false);
    const [encodeForm, setEncodeForm] = useState({ user_id: '', loan_product: 'business', loan_type: 'business', loan_status: 'pending', loan_amount: '', interest_rate: '3', term_months: '12', payment_frequency: 'monthly', applied_at: new Date().toISOString().split('T')[0] });
    const [memberSearch, setMemberSearch] = useState('');
    const [searchResults, setSearchResults] = useState<any[]>([]);
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['loans', search, page],
        queryFn: () => api.get('/api/loans', { params: { search: search || undefined, page, per_page: 10 } }),
    });

    const { data: productsData } = useQuery({
        queryKey: ['loan-products-options'],
        queryFn: () => api.get('/api/loans/loan-products'),
    });

    const encodeMutation = useMutation({
        mutationFn: (payload: any) => api.post('/api/loans', payload),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['loans'] }); setShowEncodeModal(false); addToast('Loan encoded', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const memberSearchMutation = useMutation({
        mutationFn: (q: string) => api.get('/api/loans/search-members', { params: { q } }),
        onSuccess: (res) => setSearchResults(res.data.data),
    });

    const loans = data?.data?.data ?? [];
    const pagination = data?.data?.pagination;
    const canEncode = data?.data?.can_encode;

    const getStatusColor = (s: string) => {
        const c: Record<string, string> = { pending: 'bg-amber-100 text-amber-700', approved: 'bg-blue-100 text-blue-700', active: 'bg-emerald-100 text-emerald-700', paid_off: 'bg-slate-100 text-slate-700', rejected: 'bg-red-100 text-red-700' };
        return c[s] || 'bg-slate-100 text-slate-700';
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Loans</h1>
                {canEncode && <button onClick={() => setShowEncodeModal(true)} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"><i className="fas fa-plus mr-1"></i> Encode Loan</button>}
            </div>

            <input type="text" placeholder="Search loans..." value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} className="w-full max-w-md rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800" />

            {isLoading ? (
                <div className="space-y-3">{[...Array(5)].map((_, i) => <div key={i} className="h-20 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />)}</div>
            ) : loans.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-600 dark:bg-slate-800">
                    <i className="fas fa-file-invoice-dollar text-4xl text-slate-300 dark:text-slate-600"></i>
                    <p className="mt-4 text-sm text-slate-500">No loans found</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {loans.map((l: any) => (
                        <button key={l.id} onClick={() => setSelectedLoan(l)} className="w-full rounded-xl border border-slate-200 bg-white p-4 text-left transition-all hover:shadow-md dark:border-slate-700 dark:bg-slate-800">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-semibold text-slate-900 dark:text-white">{l.loan_number}</p>
                                    <p className="text-sm text-slate-500">{l.borrower?.name || 'Unknown'} · {l.loan_type} · {l.applied_at}</p>
                                </div>
                                <div className="text-right">
                                    <p className="text-lg font-bold text-slate-900 dark:text-white">₱{Number(l.loan_amount).toLocaleString()}</p>
                                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${getStatusColor(l.loan_status)}`}>{l.loan_status}</span>
                                </div>
                            </div>
                        </button>
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

            {/* Detail Modal */}
            {selectedLoan && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setSelectedLoan(null)}>
                    <div className="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800" onClick={e => e.stopPropagation()}>
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-bold text-slate-900 dark:text-white">{selectedLoan.loan_number}</h2>
                            <button onClick={() => setSelectedLoan(null)} className="text-slate-400 hover:text-slate-600"><i className="fas fa-times"></i></button>
                        </div>
                        <div className="space-y-3">
                            <InfoRow label="Borrower" value={selectedLoan.borrower?.name || '—'} />
                            <InfoRow label="Type" value={selectedLoan.loan_type} />
                            <InfoRow label="Amount" value={`₱${Number(selectedLoan.loan_amount).toLocaleString()}`} />
                            <InfoRow label="Interest" value={`${selectedLoan.interest_rate}%`} />
                            <InfoRow label="Term" value={`${selectedLoan.term_months} months`} />
                            <InfoRow label="Status" value={selectedLoan.loan_status} />
                            <InfoRow label="Applied" value={selectedLoan.applied_at || '—'} />
                            <InfoRow label="Due Date" value={selectedLoan.due_date || '—'} />
                        </div>
                    </div>
                </div>
            )}

            {/* Encode Modal */}
            {showEncodeModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowEncodeModal(false)}>
                    <div className="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800" onClick={e => e.stopPropagation()}>
                        <h2 className="mb-4 text-lg font-bold text-slate-900 dark:text-white">Encode Loan</h2>
                        <form onSubmit={e => { e.preventDefault(); encodeMutation.mutate(encodeForm); }} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Search Member</label>
                                <input type="text" value={memberSearch} onChange={e => { setMemberSearch(e.target.value); if (e.target.value.length >= 2) memberSearchMutation.mutate(e.target.value); }} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" placeholder="Search by name or number..." />
                                {searchResults.length > 0 && (
                                    <div className="mt-1 rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-600 dark:bg-slate-700">
                                        {searchResults.map((m: any) => (
                                            <button key={m.id} type="button" onClick={() => { setEncodeForm(f => ({ ...f, user_id: m.id })); setMemberSearch(`${m.name} (${m.user_number})`); setSearchResults([]); }} className="w-full px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-600">{m.name} ({m.user_number})</button>
                                        ))}
                                    </div>
                                )}
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Amount</label><input type="number" value={encodeForm.loan_amount} onChange={e => setEncodeForm(f => ({ ...f, loan_amount: e.target.value }))} required className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" /></div>
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Interest Rate (%)</label><input type="number" step="0.1" value={encodeForm.interest_rate} onChange={e => setEncodeForm(f => ({ ...f, interest_rate: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" /></div>
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Term (months)</label><input type="number" value={encodeForm.term_months} onChange={e => setEncodeForm(f => ({ ...f, term_months: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" /></div>
                                <div><label className="mb-1 block text-sm font-medium text-slate-700">Frequency</label><select value={encodeForm.payment_frequency} onChange={e => setEncodeForm(f => ({ ...f, payment_frequency: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700"><option value="weekly">Weekly</option><option value="bi-weekly">Bi-weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="annually">Annually</option></select></div>
                            </div>
                            <div className="flex justify-end gap-3">
                                <button type="button" onClick={() => setShowEncodeModal(false)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                                <button type="submit" disabled={encodeMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">{encodeMutation.isPending ? 'Saving...' : 'Encode'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

function InfoRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-700">
            <span className="text-sm text-slate-500">{label}</span>
            <span className="text-sm font-medium text-slate-900 dark:text-white">{value}</span>
        </div>
    );
}
