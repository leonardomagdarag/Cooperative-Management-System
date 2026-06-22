import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

export default function LedgerPage() {
    const [category, setCategory] = useState('all');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [showAddModal, setShowAddModal] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [form, setForm] = useState({ transaction_type: 'deposit', amount: '', note: '', account_id: '', loan_id: '' });
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['ledger', category, search, page],
        queryFn: () => api.get('/api/ledger', { params: { category: category === 'all' ? undefined : category, search: search || undefined, page, per_page: 10 } }),
    });

    const { data: summaryData } = useQuery({
        queryKey: ['ledger-summary'],
        queryFn: () => api.get('/api/ledger/summary', { params: { range: 'today' } }),
    });

    const saveMutation = useMutation({
        mutationFn: (payload: any) => editingId
            ? api.put(`/api/ledger/${editingId}`, payload)
            : api.post('/api/ledger', payload),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['ledger'] }); setShowAddModal(false); setEditingId(null); addToast(editingId ? 'Updated' : 'Created', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/api/ledger/${id}`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['ledger'] }); addToast('Deleted', 'success'); },
    });

    const transactions = data?.data?.data ?? [];
    const pagination = data?.data?.pagination;
    const canEdit = data?.data?.can_edit;
    const summary = summaryData?.data;

    const getTypeColor = (t: string) => {
        const c: Record<string, string> = { deposit: 'bg-emerald-100 text-emerald-700', withdrawal: 'bg-red-100 text-red-700', loan_payment: 'bg-blue-100 text-blue-700', loan_disbursement: 'bg-amber-100 text-amber-700', fee: 'bg-slate-100 text-slate-700' };
        return c[t] || 'bg-slate-100 text-slate-700';
    };

    const openAdd = () => { setEditingId(null); setForm({ transaction_type: 'deposit', amount: '', note: '', account_id: '', loan_id: '' }); setShowAddModal(true); };
    const openEdit = (t: any) => { setEditingId(t.id); setForm({ transaction_type: t.transaction_type, amount: t.amount, note: t.note || '', account_id: t.account_id || '', loan_id: t.loan_id || '' }); setShowAddModal(true); };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Ledger</h1>
                {canEdit && <button onClick={openAdd} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"><i className="fas fa-plus mr-1"></i> Add Transaction</button>}
            </div>

            {/* Summary */}
            {summary && (
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <MiniStat label="Deposits" value={summary.deposit_count} color="emerald" />
                    <MiniStat label="Withdrawals" value={summary.withdrawal_count} color="red" />
                    <MiniStat label="Loan Payments" value={summary.loan_payment_count} color="blue" />
                    <MiniStat label="Loans" value={summary.loan_count} color="amber" />
                </div>
            )}

            {/* Filters */}
            <div className="flex flex-wrap gap-3">
                <input type="text" placeholder="Search transactions..." value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800" />
                <div className="flex gap-1 rounded-lg border border-slate-200 bg-white p-1 dark:border-slate-600 dark:bg-slate-800">
                    {['all', 'deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'fee'].map(c => (
                        <button key={c} onClick={() => { setCategory(c); setPage(1); }} className={`rounded-md px-3 py-1 text-xs font-medium ${category === c ? 'bg-emerald-600 text-white' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700'}`}>
                            {c === 'all' ? 'All' : c.replace('_', ' ')}
                        </button>
                    ))}
                </div>
            </div>

            {/* Table */}
            {isLoading ? (
                <div className="space-y-3">{[...Array(5)].map((_, i) => <div key={i} className="h-14 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />)}</div>
            ) : transactions.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-600 dark:bg-slate-800">
                    <i className="fas fa-book text-4xl text-slate-300 dark:text-slate-600"></i>
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
                                <th className="px-4 py-3 font-medium text-slate-500">Account Holder</th>
                                <th className="px-4 py-3 font-medium text-slate-500">Date</th>
                                {canEdit && <th className="px-4 py-3 font-medium text-slate-500">Actions</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                            {transactions.map((t: any) => (
                                <tr key={t.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">{t.transaction_number}</td>
                                    <td className="px-4 py-3"><span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${getTypeColor(t.transaction_type)}`}>{t.transaction_type.replace('_', ' ')}</span></td>
                                    <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">₱{Number(t.amount).toLocaleString()}</td>
                                    <td className="px-4 py-3"><span className={`text-xs font-medium ${t.status === 'completed' ? 'text-emerald-600' : 'text-amber-600'}`}>{t.status}</span></td>
                                    <td className="px-4 py-3 text-slate-500">{t.account_holder || '—'}</td>
                                    <td className="px-4 py-3 text-slate-500">{t.transaction_date || '—'}</td>
                                    {canEdit && (
                                        <td className="px-4 py-3">
                                            <div className="flex gap-1">
                                                <button onClick={() => openEdit(t)} className="rounded p-1 text-slate-400 hover:bg-slate-100"><i className="fas fa-edit text-xs"></i></button>
                                                <button onClick={() => { if (confirm('Delete?')) deleteMutation.mutate(t.id); }} className="rounded p-1 text-red-400 hover:bg-red-50"><i className="fas fa-trash text-xs"></i></button>
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {pagination && pagination.last_page > 1 && (
                <div className="flex items-center justify-center gap-2">
                    <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-50">Previous</button>
                    <span className="text-sm text-slate-500">Page {page} of {pagination.last_page}</span>
                    <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-50">Next</button>
                </div>
            )}

            {showAddModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowAddModal(false)}>
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800" onClick={e => e.stopPropagation()}>
                        <h2 className="mb-4 text-lg font-bold text-slate-900 dark:text-white">{editingId ? 'Edit' : 'Add'} Transaction</h2>
                        <form onSubmit={e => { e.preventDefault(); saveMutation.mutate(form); }} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Type</label>
                                <select value={form.transaction_type} onChange={e => setForm(f => ({ ...f, transaction_type: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700">
                                    <option value="deposit">Deposit</option><option value="withdrawal">Withdrawal</option><option value="loan_payment">Loan Payment</option><option value="loan_disbursement">Loan Disbursement</option><option value="fee">Fee</option>
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Amount</label>
                                <input type="number" step="0.01" value={form.amount} onChange={e => setForm(f => ({ ...f, amount: e.target.value }))} required className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Note</label>
                                <input type="text" value={form.note} onChange={e => setForm(f => ({ ...f, note: e.target.value }))} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" />
                            </div>
                            <div className="flex justify-end gap-3">
                                <button type="button" onClick={() => setShowAddModal(false)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                                <button type="submit" disabled={saveMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">{saveMutation.isPending ? 'Saving...' : 'Save'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

function MiniStat({ label, value, color }: { label: string; value: number; color: string }) {
    const c: Record<string, string> = { emerald: 'text-emerald-600', red: 'text-red-600', blue: 'text-blue-600', amber: 'text-amber-600' };
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800">
            <p className="text-xs text-slate-500">{label}</p>
            <p className={`text-xl font-bold ${c[color]}`}>{value}</p>
        </div>
    );
}
