import HelpTip from '../../components/ui/HelpTip';
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import DatePicker from '../../components/DatePicker';
import BranchBadge from '../../components/BranchBadge';
import BranchSelect from '../../components/BranchSelect';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { validate, ledgerSchema, type FieldErrors } from '../../lib/validation';
import ConfirmDialog from '../../components/ui/ConfirmDialog';
import Pagination from '../../components/ui/Pagination';
import Modal from '../../components/ui/Modal';
import Button from '../../components/ui/Button';

export default function LedgerPage() {
    const [category, setCategory] = useState('all');
    const [search, setSearch] = useState('');
    const [branchFilter, setBranchFilter] = useState<number | ''>('');
    const [page, setPage] = useState(1);
    const [showAddModal, setShowAddModal] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [reversalTarget, setReversalTarget] = useState<{ id: number; transactionNumber: string } | null>(null);
    const [reversalOtp, setReversalOtp] = useState('');
    const [form, setForm] = useState({ transaction_type: 'deposit', amount: '', note: '', account_id: '', loan_id: '', branch_id: '' as number | '', transaction_date: new Date().toISOString().slice(0, 10) });
    const [formErrors, setFormErrors] = useState<FieldErrors>({});
    const [confirmState, setConfirmState] = useState<{
        isOpen: boolean;
        title: string;
        message: string;
        confirmText?: string;
        variant?: 'danger' | 'warning' | 'primary';
        onConfirm: () => void;
    }>({ isOpen: false, title: '', message: '', onConfirm: () => {} });
    const { markDirty, reset, requestClose, renderDialog } = useDiscardGuard();
    const queryClient = useQueryClient();
    const { addToast } = useToast();
    const { user } = useAuth();
    const canAssignBranch = !!user?.permissions?.includes('branches.view');
    const canReverse = !!user?.permissions?.includes('transactions.reversal');

    const setFormD = (updater: (f: typeof form) => typeof form) => { markDirty(); setForm(updater); };
    const requestCloseGuard = () => requestClose(() => setShowAddModal(false));

    const { data, isLoading } = useQuery({
        queryKey: ['ledger', category, search, page, branchFilter],
        queryFn: () => api.get('/api/ledger', { params: { category: category === 'all' || category === 'corrections' ? undefined : category, corrections: category === 'corrections' ? 1 : undefined, search: search || undefined, branch_id: branchFilter || undefined, page, per_page: 10 } }),
    });

    const { data: summaryData } = useQuery({
        queryKey: ['ledger-summary', branchFilter],
        queryFn: () => api.get('/api/ledger/summary', { params: { range: 'today', branch_id: branchFilter || undefined } }),
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

    const sendReversalOtpMutation = useMutation({
        mutationFn: (transactionId: number) => api.post(`/api/ledger/${transactionId}/reverse/send-otp`),
        onSuccess: (_response, transactionId) => {
            const transaction = transactions.find((item: any) => item.id === transactionId);
            setReversalTarget({ id: transactionId, transactionNumber: transaction?.transaction_number || String(transactionId) });
            setReversalOtp('');
            addToast('A one-time verification code was sent to your email.', 'info');
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Could not send the verification code.', 'error'),
    });

    const reverseMutation = useMutation({
        mutationFn: () => api.post(`/api/ledger/${reversalTarget!.id}/reverse`, { otp_code: reversalOtp }),
        onSuccess: (response) => {
            queryClient.invalidateQueries({ queryKey: ['ledger'] });
            setReversalTarget(null);
            setReversalOtp('');
            addToast(response.data.message, 'success');
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Reversal failed', 'error'),
    });

    const transactions = data?.data?.data ?? [];
    const pagination = data?.data?.pagination;
    const canEdit = data?.data?.can_edit;
    const summary = summaryData?.data;

    const getTypeColor = (t: string) => {
        const c: Record<string, string> = { deposit: 'bg-blue-100 text-blue-800', withdrawal: 'bg-orange-100 text-orange-800', loan_payment: 'bg-green-100 text-green-800', loan_disbursement: 'bg-purple-100 text-purple-800', fee: 'bg-red-100 text-red-800' };
        return c[t] || 'bg-gray-100 text-gray-800';
    };

    const getTypeLabel = (t: string) => {
        const c: Record<string, string> = { deposit: 'Deposit', withdrawal: 'Withdrawal', loan_payment: 'Loan Payment', loan_disbursement: 'Loan Disbursement', fee: 'Fee' };
        return c[t] || t.replace(/_/g, ' ');
    };

    const openAdd = () => { reset(); setEditingId(null); setForm({ transaction_type: 'deposit', amount: '', note: '', account_id: '', loan_id: '', branch_id: '', transaction_date: new Date().toISOString().slice(0, 10) }); setShowAddModal(true); };
    const openEdit = (t: any) => { reset(); setEditingId(t.id); setForm({ transaction_type: t.transaction_type, amount: t.amount, note: t.note || '', account_id: t.account_id || '', loan_id: t.loan_id || '', branch_id: t.branch_id || '', transaction_date: t.transaction_date || new Date().toISOString().slice(0, 10) }); setShowAddModal(true); };

    const exportCsv = async () => {
        try {
            const res = await api.get('/api/ledger/export', {
                params: { category: category === 'all' || category === 'corrections' ? undefined : category, corrections: category === 'corrections' ? 1 : undefined, search: search || undefined },
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
                    <h1 className="flex items-center gap-2 text-xl font-semibold text-gray-900 sm:text-2xl">Ledger <HelpTip text="The ledger is the financial transaction record. Use filters to narrow the entries; exports follow the currently selected filters and your branch access." /></h1>
                    <p className="text-sm text-gray-500">Track all financial transactions</p>
                </div>
                <div className="mt-3 sm:mt-0 flex gap-2">
                    <button onClick={exportCsv} className="bg-emerald-600 hover:bg-emerald-700 rounded-lg focus:ring-4 focus:ring-emerald-200 text-white px-4 py-2 text-sm font-medium transition-colors">
                        <i className="fas fa-download mr-1"></i> Export
                    </button>
                    {canEdit && (
                        <button onClick={openAdd} className="bg-emerald-600 hover:bg-emerald-700 rounded-lg focus:ring-4 focus:ring-emerald-200 text-white px-4 py-2 text-sm font-medium transition-colors">
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
                    {['all', 'deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'fee', 'corrections'].map(c => (
                        <button key={c} onClick={() => { setCategory(c); setPage(1); }} className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${category === c ? 'border-b-2 border-emerald-600 text-emerald-600 font-semibold' : 'text-gray-500 hover:text-gray-700 border-transparent'}`}>
                            {c === 'all' ? 'All' : c === 'corrections' ? 'Corrections' : c.replace(/_/g, ' ')}
                        </button>
                    ))}
                </div>

                <input
                    type="text"
                    placeholder="Search transactions..."
                    value={search}
                    onChange={e => { setSearch(e.target.value); setPage(1); }}
                    className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block w-full p-2.5 mb-4"
                />

                {canAssignBranch && (
                    <div className="mb-4 w-full sm:w-64">
                        <BranchSelect label="" value={branchFilter} onChange={v => { setBranchFilter(v); setPage(1); }} allLabel="All branches" />
                    </div>
                )}

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
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Member</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Type</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Amount</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                                    {canAssignBranch && <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Branch</th>}
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Note</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Date</th>
                                    {canEdit && <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {transactions.map((t: any) => (
                                    <tr key={t.id} className="hover:bg-gray-50">
                                        <td className="p-4 text-sm font-medium text-gray-900 truncate" title={t.transaction_number}>{t.transaction_number}</td>
                                        <td className="p-4 text-sm text-gray-900 truncate" title={t.account_holder || '—'}>{t.account_holder || '—'}{t.user_number && <div className="text-xs text-gray-500">{t.user_number}</div>}</td>
                                        <td className="p-4"><span className={`px-2.5 py-0.5 text-xs font-medium rounded ${getTypeColor(t.transaction_type)}`}>{t.reversal_of_id ? `Reversal · ${getTypeLabel(t.transaction_type)}` : getTypeLabel(t.transaction_type)}</span>{t.reversal_of_id && <div className="text-xs text-gray-500 mt-1">Reverses {t.reversal_of_transaction_number || `transaction #${t.reversal_of_id}`}</div>}</td>
                                        <td className={`p-4 text-sm font-semibold whitespace-nowrap ${(['withdrawal', 'fee'].includes(t.transaction_type) !== !!t.reversal_of_id) ? 'text-red-600' : 'text-green-600'}`}>{(['withdrawal', 'fee'].includes(t.transaction_type) !== !!t.reversal_of_id) ? '-' : '+'}₱{Number(t.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td className="p-4"><span className={`text-xs font-medium ${t.status === 'completed' ? 'text-emerald-600' : 'text-amber-600'}`}>{t.status}</span></td>
                                        {canAssignBranch && <td className="p-4 truncate" title={t.branch_name}><BranchBadge name={t.branch_name} /></td>}
                                        <td className="p-4 text-sm text-gray-500">{t.note || '—'}</td>
                                        <td className="p-4 text-sm text-gray-500">{t.transaction_date || '—'}</td>
                                        <td className="p-4">
                                            <div className="flex gap-1 items-center">
                                                {t.status === 'completed' && (
                                                    <>
                                                        <button
                                                            onClick={async () => {
                                                                try {
                                                                    const res = await api.post(`/transactions/${t.id}/receipts/reprint`);
                                                                    addToast(res.data.message, 'success');
                                                                } catch (err: any) {
                                                                    addToast(err.response?.data?.message || 'Failed to reprint', 'error');
                                                                }
                                                            }}
                                                            className="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                            title="Reprint Receipt"
                                                        >
                                                            <i className="fas fa-print text-xs"></i>
                                                        </button>
                                                        {user?.permissions?.includes('receipts.reissue') && (
                                                            <button
                                                                onClick={async () => {
                                                                    const note = prompt('Reason for certified reissue:');
                                                                    if (note) {
                                                                        try {
                                                                            const res = await api.post(`/transactions/${t.id}/receipts/reissue`, { note });
                                                                            addToast(res.data.message, 'success');
                                                                        } catch (err: any) {
                                                                            addToast(err.response?.data?.message || 'Failed to reissue', 'error');
                                                                        }
                                                                    }
                                                                }}
                                                                className="p-1.5 text-purple-600 hover:bg-purple-50 rounded-lg transition-colors"
                                                                title="Issue Certified Replacement"
                                                            >
                                                                <i className="fas fa-certificate text-xs"></i>
                                                            </button>
                                                        )}
                                                    </>
                                                )}
                                                {canEdit && (
                                                    <>
                                                        <button onClick={() => openEdit(t)} className="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"><i className="fas fa-edit text-xs"></i></button>
                                                        <button onClick={() => setConfirmState({
                                                            isOpen: true,
                                                            title: 'Delete Transaction',
                                                            message: `Are you sure you want to delete transaction "${t.transaction_number}"? This action cannot be undone.`,
                                                            confirmText: 'Delete',
                                                            variant: 'danger',
                                                            onConfirm: () => deleteMutation.mutate(t.id)
                                                        })} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"><i className="fas fa-trash text-xs"></i></button>
                                                    </>
                                                )}
                                                {canReverse && t.status === 'completed' && !t.reversal_of_id && (
                                                    <button onClick={() => setConfirmState({
                                                        isOpen: true,
                                                        title: 'Reverse Transaction',
                                                        message: `Post a linked reversing entry for transaction "${t.transaction_number}"?`,
                                                        confirmText: 'Post reversal',
                                                        variant: 'danger',
                                                        onConfirm: () => sendReversalOtpMutation.mutate(t.id),
                                                    })} className="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Post reversing entry">
                                                        <i className="fas fa-rotate-left text-xs"></i>
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {pagination && pagination.last_page > 1 && (
                    <div className="mt-4">
                        <Pagination
                            page={page}
                            totalPages={pagination.last_page || 1}
                            onPageChange={setPage}
                            total={pagination.total}
                            perPage={pagination.per_page}
                        />
                    </div>
                )}
            </div>

            {showAddModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={requestCloseGuard}>
                    <div className="w-full max-w-2xl max-h-[90vh] overflow-hidden rounded-2xl bg-white shadow-2xl flex flex-col" onClick={e => e.stopPropagation()}>
                        <div className="bg-gradient-to-r from-emerald-600 to-cyan-600 px-6 py-5 text-white shrink-0">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-bold">{editingId ? 'Edit' : 'Add'} Transaction</h2>
                                <button onClick={requestCloseGuard} className="rounded-full p-1 text-white/70 hover:bg-white/20 hover:text-white"><i className="fas fa-times"></i></button>
                            </div>
                        </div>
                        <div className="flex-1 overflow-y-auto p-6">
                            <form id="ledger-form" onSubmit={handleSave} className="space-y-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Type</label>
                                    <select value={form.transaction_type} onChange={e => setFormD(f => ({ ...f, transaction_type: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                        <option value="deposit">Deposit</option><option value="withdrawal">Withdrawal</option><option value="loan_payment">Loan Payment</option><option value="loan_disbursement">Loan Disbursement</option><option value="fee">Fee</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Amount</label>
                                    <input type="number" step="0.01" value={form.amount} onChange={e => setFormD(f => ({ ...f, amount: e.target.value }))} className={`w-full rounded-lg border px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500 ${formErrors.amount ? 'border-red-400' : 'border-gray-200'}`} />
                                    {formErrors.amount && <p className="mt-1 text-xs text-red-600">{formErrors.amount}</p>}
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Account ID</label>
                                        <input type="text" value={form.account_id} onChange={e => setFormD(f => ({ ...f, account_id: e.target.value }))} placeholder="Required for deposits/withdrawals" className={`w-full rounded-lg border px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500 ${formErrors.account_id ? 'border-red-400' : 'border-gray-200'}`} />
                                        {formErrors.account_id && <p className="mt-1 text-xs text-red-600">{formErrors.account_id}</p>}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-gray-700">Loan ID</label>
                                        <input type="text" value={form.loan_id} onChange={e => setFormD(f => ({ ...f, loan_id: e.target.value }))} placeholder="Required for loan types" className={`w-full rounded-lg border px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500 ${formErrors.loan_id ? 'border-red-400' : 'border-gray-200'}`} />
                                        {formErrors.loan_id && <p className="mt-1 text-xs text-red-600">{formErrors.loan_id}</p>}
                                    </div>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Date</label>
                                    <DatePicker value={form.transaction_date} onChange={v => setFormD(f => ({ ...f, transaction_date: v }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Note</label>
                                    <input type="text" value={form.note} onChange={e => setFormD(f => ({ ...f, note: e.target.value }))} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                </div>
                                {canAssignBranch && (
                                    <BranchSelect value={form.branch_id} onChange={v => setFormD(f => ({ ...f, branch_id: v }))} error={formErrors.branch_id} />
                                )}
                            </form>
                        </div>
                        <div className="flex shrink-0 items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 rounded-b-2xl">
                            <button type="button" onClick={requestCloseGuard} className="rounded-lg border border-gray-200 px-4 py-2 text-sm hover:bg-gray-100 transition-colors">Cancel</button>
                            <button type="submit" form="ledger-form" disabled={saveMutation.isPending} className="bg-emerald-600 hover:bg-emerald-700 rounded-lg focus:ring-4 focus:ring-emerald-200 text-white px-4 py-2 text-sm font-medium disabled:opacity-50 transition-colors">{saveMutation.isPending ? 'Saving...' : 'Save'}</button>
                        </div>
                    </div>
                    {renderDialog()}
                </div>
            )}

            <ConfirmDialog
                isOpen={confirmState.isOpen}
                title={confirmState.title}
                message={confirmState.message}
                confirmText={confirmState.confirmText}
                variant={confirmState.variant}
                onConfirm={() => {
                    confirmState.onConfirm();
                    setConfirmState(prev => ({ ...prev, isOpen: false }));
                }}
                onCancel={() => setConfirmState(prev => ({ ...prev, isOpen: false }))}
            />
            <Modal
                open={!!reversalTarget}
                onClose={() => !reverseMutation.isPending && setReversalTarget(null)}
                title="Verify transaction reversal"
                footer={<div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setReversalTarget(null)} disabled={reverseMutation.isPending}>Cancel</Button><Button onClick={() => reverseMutation.mutate()} disabled={reversalOtp.length !== 6 || reverseMutation.isPending}>{reverseMutation.isPending ? 'Posting…' : 'Verify and reverse'}</Button></div>}
            >
                <div className="space-y-3">
                    <p className="text-sm text-slate-600">Enter the one-time code sent to your account email to post a reversing entry for transaction <strong>{reversalTarget?.transactionNumber}</strong>.</p>
                    <label className="block text-sm font-medium text-slate-700">Verification code
                        <input autoComplete="one-time-code" inputMode="numeric" value={reversalOtp} onChange={event => setReversalOtp(event.target.value.replace(/\D/g, '').slice(0, 6))} maxLength={6} className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-center tracking-[0.4em] focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200" />
                    </label>
                </div>
            </Modal>
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
