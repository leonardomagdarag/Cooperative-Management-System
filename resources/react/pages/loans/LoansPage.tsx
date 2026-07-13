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
        <>
            <div className="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
                <div>
                    <h1 className="text-xl font-semibold text-gray-900 sm:text-2xl">Loans</h1>
                    <p className="text-sm text-gray-500">Manage loan applications and records</p>
                </div>
                {canEncode && (
                    <button onClick={() => setShowEncodeModal(true)} className="mt-3 sm:mt-0 bg-blue-700 hover:bg-blue-800 rounded-lg focus:ring-4 focus:ring-blue-300 text-white px-4 py-2 text-sm font-medium">
                        <i className="fas fa-plus mr-1"></i> Encode Loan
                    </button>
                )}
            </div>

            <div className="p-4">
                <input
                    type="text"
                    placeholder="Search loans..."
                    value={search}
                    onChange={e => { setSearch(e.target.value); setPage(1); }}
                    className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 mb-4"
                />

                {isLoading ? (
                    <div className="space-y-3">{[...Array(5)].map((_, i) => <div key={i} className="h-14 animate-pulse rounded-lg bg-gray-100" />)}</div>
                ) : loans.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center">
                        <i className="fas fa-file-invoice-dollar text-4xl text-gray-300"></i>
                        <p className="mt-4 text-sm text-gray-500">No loans found</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                        <table className="min-w-full divide-y divide-gray-200 table-fixed">
                            <thead className="bg-gray-100">
                                <tr>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Loan #</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Member</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Type</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Amount</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {loans.map((l: any) => (
                                    <tr key={l.id} className="hover:bg-gray-50">
                                        <td className="p-4 text-sm font-medium text-gray-900">{l.loan_number}</td>
                                        <td className="p-4 text-sm text-gray-500">{l.borrower?.name || 'Unknown'}</td>
                                        <td className="p-4 text-sm text-gray-500">{l.loan_type}</td>
                                        <td className="p-4 text-sm font-medium text-gray-900">₱{Number(l.loan_amount).toLocaleString()}</td>
                                        <td className="p-4">
                                            <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${getStatusColor(l.loan_status)}`}>{l.loan_status}</span>
                                        </td>
                                        <td className="p-4">
                                            <button onClick={() => setSelectedLoan(l)} className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg">
                                                <i className="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {pagination && pagination.last_page > 1 && (
                    <div className="sticky bottom-0 bg-white border-t border-gray-200 p-4 flex items-center justify-center gap-2">
                        <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm disabled:opacity-50">Previous</button>
                        <span className="text-sm text-gray-500">Page {page} of {pagination.last_page}</span>
                        <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm disabled:opacity-50">Next</button>
                    </div>
                )}
            </div>

            {/* Detail Modal */}
            {selectedLoan && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setSelectedLoan(null)}>
                    <div className="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                        <div className="p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="text-lg font-bold text-gray-900">{selectedLoan.loan_number}</h2>
                                <button onClick={() => setSelectedLoan(null)} className="text-gray-400 hover:text-gray-600"><i className="fas fa-times"></i></button>
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
                </div>
            )}

            {/* Encode Modal */}
            {showEncodeModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowEncodeModal(false)}>
                    <div className="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                        <div className="p-6">
                            <h2 className="mb-4 text-lg font-bold text-gray-900">Encode Loan</h2>
                            <form onSubmit={e => { e.preventDefault(); encodeMutation.mutate(encodeForm); }} className="space-y-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Search Member</label>
                                    <input type="text" value={memberSearch} onChange={e => { setMemberSearch(e.target.value); if (e.target.value.length >= 2) memberSearchMutation.mutate(e.target.value); }} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="Search by name or number..." />
                                    {searchResults.length > 0 && (
                                        <div className="mt-1 rounded-lg border border-gray-200 bg-white shadow-lg">
                                            {searchResults.map((m: any) => (
                                                <button key={m.id} type="button" onClick={() => { setEncodeForm(f => ({ ...f, user_id: m.id })); setMemberSearch(`${m.name} (${m.user_number})`); setSearchResults([]); }} className="w-full px-3 py-2 text-left text-sm hover:bg-gray-50">{m.name} ({m.user_number})</button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Amount</label><input type="number" value={encodeForm.loan_amount} onChange={e => setEncodeForm(f => ({ ...f, loan_amount: e.target.value }))} required className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" /></div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Interest Rate (%)</label><input type="number" step="0.1" value={encodeForm.interest_rate} onChange={e => setEncodeForm(f => ({ ...f, interest_rate: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" /></div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Term (months)</label><input type="number" value={encodeForm.term_months} onChange={e => setEncodeForm(f => ({ ...f, term_months: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" /></div>
                                    <div><label className="mb-1 block text-sm font-medium text-gray-700">Frequency</label><select value={encodeForm.payment_frequency} onChange={e => setEncodeForm(f => ({ ...f, payment_frequency: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"><option value="weekly">Weekly</option><option value="bi-weekly">Bi-weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="annually">Annually</option></select></div>
                                </div>
                                <div className="flex justify-end gap-3">
                                    <button type="button" onClick={() => setShowEncodeModal(false)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm">Cancel</button>
                                    <button type="submit" disabled={encodeMutation.isPending} className="bg-blue-700 hover:bg-blue-800 rounded-lg focus:ring-4 focus:ring-blue-300 text-white px-4 py-2 text-sm font-medium disabled:opacity-50">{encodeMutation.isPending ? 'Saving...' : 'Encode'}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

function InfoRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
            <span className="text-sm text-gray-500">{label}</span>
            <span className="text-sm font-medium text-gray-900">{value}</span>
        </div>
    );
}
