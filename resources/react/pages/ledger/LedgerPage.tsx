import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import DatePicker from '../../components/DatePicker';
import { useToast } from '../../contexts/ToastContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { validate, ledgerSchema, type FieldErrors } from '../../lib/validation';

export default function LedgerPage() {
    const [category, setCategory] = useState('all');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [showAddModal, setShowAddModal] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [form, setForm] = useState({ transaction_type: 'deposit', amount: '', note: '', account_id: '', loan_id: '', transaction_date: new Date().toISOString().slice(0, 10) });
    const [formErrors, setFormErrors] = useState<FieldErrors>({});
    const { markDirty, reset, requestClose, renderDialog } = useDiscardGuard();
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const setFormD = (updater: (f: typeof form) => typeof form) => { markDirty(); setForm(updater); };
    const requestCloseGuard = () => requestClose(() => setShowAddModal(false));

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

    const openAdd = () => { reset(); setEditingId(null); setForm({ transaction_type: 'deposit', amount: '', note: '', account_id: '', loan_id: '', transaction_date: new Date().toISOString().slice(0, 10) }); setShowAddModal(true); };
    const openEdit = (t: any) => { reset(); setEditingId(t.id); setForm({ transaction_type: t.transaction_type, amount: t.amount, note: t.note || '', account_id: t.account_id || '', loan_id: t.loan_id || '', transaction_date: t.transaction_date || new Date().toISOString().slice(0, 10) }); setShowAddModal(true); };

    const exportCsv = async () => {
        try {
            const res = await api.get('/api/ledger/export', {
                params: { category: category === 'all' ? undefined : category, search: search || undefined },
                responseType: 'blob',
            });
            const url = URL.createObjectURL(res.data);
            const a = document.createElement('a');
            a.href = url;
            a.download = `ledger-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
            addToast('Exported', 'success');
        } catch (err: any) {
            addToast(err.response?.data?.message || 'Export failed', 'error');
        }
    };

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        const errs = validate(ledgerSchema, form);
        if (Object.keys(errs).length > 0) {
            setFormErrors(errs);
            addToast(Object.values(errs)[0], 'error');
            return;
        }
        setFormErrors({});
        saveMutation.mutate(form);
    };

    return (
        <>
            <div className="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
                <div>
                    <h1 className="text-xl font-semibold text-gray-900 sm:text-2xl">Ledger</h1>
                    <p className="text-sm text-gray-500">Track all financial transactions</p>
                </div>
                <div className="mt-3 sm:mt-0 flex gap-2">
                    <button onClick={exportCsv} className="bg-blue-700 hover:bg-blue-800 rounded-lg focus:ring-4 focus:ring-blue-300 text-white px-4 py-2 text-sm font-medium">
                        <i className="fas fa-download mr-1"></i> Export
                    </button>
                    {canEdit && (
                        <button onClick={openAdd} className="bg-blue-700 hover:bg-blue-800 rounded-lg focus:ring-4 focus:ring-blue-300 text-white px-4 py-2 text-sm font-medium">
                            <i className="fas fa-plus mr-1"></i> Add Entry
                        </button>
                    )}
                </div>
            </div>

            <div className="p-4">
                {/* Summary */}
                {summary && (
                    <div className="grid grid-cols-1 gap-3 mt-3 sm:grid-cols-2 lg:grid-cols-4 mb-4">
                        <MiniStat label="Deposits" value={summary.deposits ?? 0} color="emerald" icon="fas fa-arrow-down" />
                        <MiniStat label="Withdrawals" value={summary.withdrawals ?? 0} color="red" icon="fas fa-arrow-up" />
                        <MiniStat label="Loan Payments" value={summary.loan_payments ?? 0} color="blue" icon="fas fa-money-check" />
                        <MiniStat label="Loans Disbursed" value={summary.loan_disbursements ?? 0} color="amber" icon="fas fa-hand-holding-usd" />
                    </div>
                )}

                {/* Tab filters */}
                <div className="flex gap-2 mb-3 border-b border-gray-200">
                    {['all', 'deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'fee'].map(c => (
                        <button key={c} onClick={() => { setCategory(c); setPage(1); }} className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${category === c ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700 border-transparent'}`}>
                            {c === 'all' ? 'All' : c.replace(/_/g, ' ')}
                        </button>
                    ))}
                </div>

                <input
                    type="text"
                    placeholder="Search transactions..."
                    value={search}
                    onChange={e => { setSearch(e.target.value); setPage(1); }}
                    className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 mb-4"
                />

                {/* Table */}
                {isLoading ? (
                    <div className="space-y-3">{[...Array(5)].map((_, i) => <div key={i} className="h-14 animate-pulse rounded-lg bg-gray-100" />)}</div>
                ) : transactions.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center">
                        <i className="fas fa-book text-4xl text-gray-300"></i>
                        <p className="mt-4 text-sm text-gray-500">No transactions found</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                        <table className="min-w-full divide-y divide-gray-200 table-fixed">
                            <thead className="bg-gray-100">
                                <tr>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Txn #</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Type</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Amount</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Note</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Date</th>
                                    {canEdit && <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {transactions.map((t: any) => (
                                    <tr key={t.id} className="hover:bg-gray-50">
                                        <td className="p-4 text-sm font-medium text-gray-900">{t.transaction_number}</td>
                                        <td className="p-4"><span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${getTypeColor(t.transaction_type)}`}>{t.transaction_type.replace('_', ' ')}</span></td>
                                        <td className="p-4 text-sm font-medium text-gray-900">₱{Number(t.amount).toLocaleString()}</td>
                                        <td className="p-4"><span className={`text-xs font-medium ${t.status === 'completed' ? 'text-emerald-600' : 'text-amber-600'}`}>{t.status}</span></td>
                                        <td className="p-4 text-sm text-gray-500">{t.note || '—'}</td>
                                        <td className="p-4 text-sm text-gray-500">{t.transaction_date || '—'}</td>
                                        {canEdit && (
                                            <td className="p-4">
                                                <div className="flex gap-1">
                                                    <button onClick={() => openEdit(t)} className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg"><i className="fas fa-edit text-xs"></i></button>
                                                    <button onClick={() => { if (confirm('Delete?')) deleteMutation.mutate(t.id); }} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"><i className="fas fa-trash text-xs"></i></button>
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
                    <div className="sticky bottom-0 bg-white border-t border-gray-200 p-4 flex items-center justify-center gap-2">
                        <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm disabled:opacity-50">Previous</button>
                        <span className="text-sm text-gray-500">Page {page} of {pagination.last_page}</span>
                        <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm disabled:opacity-50">Next</button>
                    </div>
                )}
            </div>

            {showAddModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={requestCloseGuard}>
                    <div className="w-full max-w-2xl rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                        <div className="p-6">
                            <h2 className="mb-4 text-lg font-bold text-gray-900">{editingId ? 'Edit' : 'Add'} Transaction</h2>
                            <form onSubmit={handleSave} className="space-y-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Type</label>
                                    <select value={form.transaction_type} onChange={e => setFormD(f => ({ ...f, transaction_type: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                        <option value="deposit">Deposit</option><option value="withdrawal">Withdrawal</option><option value="loan_payment">Loan Payment</option><option value="loan_disbursement">Loan Disbursement</option><option value="fee">Fee</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Amount</label>
                                    <input type="number" step="0.01" value={form.amount} onChange={e => setFormD(f => ({ ...f, amount: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm ${formErrors.amount ? 'border-red-400' : 'border-gray-200'}`} />
                                    {formErrors.amount && <p className="mt-1 text-xs text-red-600">{formErrors.amount}</p>}
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Account ID</label>
                                        <input type="text" value={form.account_id} onChange={e => setFormD(f => ({ ...f, account_id: e.target.value }))} placeholder="Required for deposits/withdrawals" className={`w-full rounded-lg border px-3 py-2 text-sm ${formErrors.account_id ? 'border-red-400' : 'border-gray-200'}`} />
                                        {formErrors.account_id && <p className="mt-1 text-xs text-red-600">{formErrors.account_id}</p>}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Loan ID</label>
                                        <input type="text" value={form.loan_id} onChange={e => setFormD(f => ({ ...f, loan_id: e.target.value }))} placeholder="Required for loan types" className={`w-full rounded-lg border px-3 py-2 text-sm ${formErrors.loan_id ? 'border-red-400' : 'border-gray-200'}`} />
                                        {formErrors.loan_id && <p className="mt-1 text-xs text-red-600">{formErrors.loan_id}</p>}
                                    </div>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Date</label>
                                    <DatePicker value={form.transaction_date} onChange={v => setFormD(f => ({ ...f, transaction_date: v }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Note</label>
                                    <input type="text" value={form.note} onChange={e => setFormD(f => ({ ...f, note: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                                </div>
                                <div className="flex justify-end gap-3">
                                    <button type="button" onClick={requestCloseGuard} className="rounded-lg border border-gray-200 px-4 py-2 text-sm">Cancel</button>
                                    <button type="submit" disabled={saveMutation.isPending} className="bg-blue-700 hover:bg-blue-800 rounded-lg focus:ring-4 focus:ring-blue-300 text-white px-4 py-2 text-sm font-medium disabled:opacity-50">{saveMutation.isPending ? 'Saving...' : 'Save'}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    {renderDialog()}
                </div>
            )}
        </>
    );
}

function MiniStat({ label, value, color, icon }: { label: string; value: number; color: string; icon: string }) {
    const c: Record<string, string> = { emerald: 'text-emerald-600', red: 'text-red-600', blue: 'text-blue-600', amber: 'text-amber-600' };
    const bg: Record<string, string> = { emerald: 'bg-emerald-50', red: 'bg-red-50', blue: 'bg-blue-50', amber: 'bg-amber-50' };
    return (
        <div className="rounded-lg border border-gray-200 bg-white p-4 flex items-center gap-3">
            <div className={`h-10 w-10 rounded-lg ${bg[color]} flex items-center justify-center`}>
                <i className={`${icon} ${c[color]}`}></i>
            </div>
            <div>
                <p className="text-xs text-gray-500">{label}</p>
                <p className={`text-xl font-bold ${c[color]}`}>{value}</p>
            </div>
        </div>
    );
}
