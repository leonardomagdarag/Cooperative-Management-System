import SortableHeader from '../../components/ui/SortableHeader';
import HelpTip from '../../components/ui/HelpTip';
import PageHeader from '../../components/ui/PageHeader';
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import DatePicker from '../../components/DatePicker';
import BranchSelect from '../../components/BranchSelect';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { validate, ledgerSchema, type FieldErrors } from '../../lib/validation';
import ConfirmDialog from '../../components/ui/ConfirmDialog';
import Pagination from '../../components/ui/Pagination';
import Modal from '../../components/ui/Modal';
import Button from '../../components/ui/Button';
import TransactionReceiptModal, { type TransactionReceiptData } from '../../components/TransactionReceiptModal';
import DateRangeFilter from '../../components/ui/DateRangeFilter';

/** Mirrors the backend's editable list in LedgerController/CountersignService. */
const EDITABLE_TYPES = ['deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'fee'];

export default function LedgerPage() {
    const [category, setCategory] = useState('all');
    const [search, setSearch] = useState('');
    const [branchFilter, setBranchFilter] = useState<number | ''>('');
    const [page, setPage] = useState(1);
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [status, setStatus] = useState('all');
    const [sort, setSort] = useState('created_at');
    const [direction, setDirection] = useState<'asc' | 'desc'>('desc');
    const [showAddModal, setShowAddModal] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [reversalTarget, setReversalTarget] = useState<{ id: number; transactionNumber: string } | null>(null);
    const [reversalOtp, setReversalOtp] = useState('');
    const [receipt, setReceipt] = useState<TransactionReceiptData | null>(null);
    const [viewingTransaction, setViewingTransaction] = useState<any | null>(null);
    const [reissueTarget, setReissueTarget] = useState<{ id: number; transactionNumber: string } | null>(null);
    const [reissueNote, setReissueNote] = useState('');
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
        queryKey: ['ledger', category, search, page, branchFilter, from, to, status, sort, direction],
        queryFn: () => api.get('/api/ledger', { params: { category: category === 'all' || category === 'corrections' ? undefined : category, corrections: category === 'corrections' ? 1 : undefined, search: search || undefined, branch_id: branchFilter || undefined, from: from || undefined, to: to || undefined, status: status === 'all' ? undefined : status, sort, direction, page, per_page: 10 } }),
    });

    const { data: summaryData } = useQuery({
        queryKey: ['ledger-summary', branchFilter],
        queryFn: () => api.get('/api/ledger/summary', { params: { range: 'all', branch_id: branchFilter || undefined } }),
    });

    const saveMutation = useMutation({
        mutationFn: (payload: any) => editingId
            ? api.put(`/api/ledger/${editingId}`, payload)
            : api.post('/api/ledger', payload),
        onSuccess: (res: any) => {
            queryClient.invalidateQueries({ queryKey: ['ledger'] });
            setShowAddModal(false);
            setEditingId(null);
            const isPending = res?.data?.status === 'pending' || res?.status === 202;
            const message = res?.data?.message || (editingId ? (isPending ? 'Transaction edit is awaiting chairman approval.' : 'Transaction updated') : 'Saved');
            addToast(message, isPending ? 'info' : 'success', isPending ? 8000 : undefined);
        },
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

    const reprintMutation = useMutation({
        mutationFn: (transactionId: number) => api.post(`/api/transactions/${transactionId}/receipts/reprint`),
        onSuccess: (response) => setReceipt(response.data.data),
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to reprint receipt', 'error'),
    });

    const reissueMutation = useMutation({
        mutationFn: ({ transactionId, note }: { transactionId: number; note: string }) =>
            api.post(`/api/transactions/${transactionId}/receipts/reissue`, { note }),
        onSuccess: (response) => {
            setReissueTarget(null);
            setReissueNote('');
            setReceipt(response.data.data);
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to issue certified replacement', 'error'),
    });

    const transactions = data?.data?.data ?? [];
    const pagination = data?.data?.pagination;
    const canEdit = data?.data?.can_edit;
    const summary = summaryData?.data;

    const getTypeColor = (t: string) => {
        const c: Record<string, string> = { deposit: 'bg-blue-100 text-blue-800', withdrawal: 'bg-orange-100 text-orange-800', loan_payment: 'bg-green-100 text-green-800', loan_disbursement: 'bg-purple-100 text-purple-800', interest: 'bg-teal-100 text-teal-800', fee: 'bg-red-100 text-red-800' };
        return c[t] || 'bg-gray-100 text-gray-800';
    };

    const getTypeLabel = (t: string) => {
        const c: Record<string, string> = { deposit: 'Deposit', withdrawal: 'Withdrawal', loan_payment: 'Loan Payment', loan_disbursement: 'Loan Disbursement', interest: 'Interest Credit', fee: 'Fee' };
        return c[t] || t.replace(/_/g, ' ');
    };

    const openAdd = () => { reset(); setEditingId(null); setForm({ transaction_type: 'deposit', amount: '', note: '', account_id: '', loan_id: '', branch_id: '', transaction_date: new Date().toISOString().slice(0, 10) }); setShowAddModal(true); };
    /** Reasons a row cannot be edited; kept alongside the button tooltips in the table. */
    const editBlockReason = (t: any): string | null => {
        if (t.status !== 'pending') return 'Only pending transactions can be edited.';
        if (!EDITABLE_TYPES.includes(t.transaction_type)) return `${getTypeLabel(t.transaction_type)} entries are system-generated and cannot be edited.`;
        return null;
    };

    const openEdit = (t: any) => {
        const reason = editBlockReason(t);
        if (reason) {
            addToast(reason, 'error');
            return;
        }
        reset(); setEditingId(t.id); setForm({ transaction_type: t.transaction_type, amount: t.amount, note: t.note || '', account_id: t.account_id != null ? String(t.account_id) : '', loan_id: t.loan_id != null ? String(t.loan_id) : '', branch_id: t.branch_id || '', transaction_date: t.transaction_date || new Date().toISOString().slice(0, 10) }); setShowAddModal(true);
    };

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

    const handleColumnSort = (field: string) => {
        setDirection(sort === field && direction === 'asc' ? 'desc' : 'asc');
        setSort(field);
        setPage(1);
    };

    return (
        <>
            <div className="p-4">
                <PageHeader title={<>Ledger <HelpTip text="The ledger is the financial transaction record. Use filters to narrow the entries; exports follow the currently selected filters and your branch access." /></>} description="Track all financial transactions" icon="fas fa-book" action={<div className="flex gap-2">
                    <button onClick={exportCsv} className="bg-emerald-600 hover:bg-emerald-700 rounded-lg focus:ring-4 focus:ring-emerald-200 text-white px-4 py-2 text-sm font-medium transition-colors">
                        <i className="fas fa-download mr-1"></i> Export
                    </button>
                    {canEdit && (
                        <button onClick={openAdd} className="bg-emerald-600 hover:bg-emerald-700 rounded-lg focus:ring-4 focus:ring-emerald-200 text-white px-4 py-2 text-sm font-medium transition-colors">
                            <i className="fas fa-plus mr-1"></i> Add Entry
                        </button>
                    )}
                </div>} />
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
                    {['all', 'deposit', 'withdrawal', 'loan_payment', 'loan_disbursement', 'interest', 'fee', 'corrections'].map(c => (
                        <button key={c} onClick={() => { setCategory(c); setPage(1); }} className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${category === c ? 'border-b-2 border-emerald-600 text-emerald-600 font-semibold' : 'text-gray-500 hover:text-gray-700 border-transparent'}`}>
                            {c === 'all' ? 'All' : c === 'corrections' ? 'Corrections' : getTypeLabel(c)}
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
                <div className="table-toolbar mb-4">
                    <DateRangeFilter from={from} to={to} onFromChange={value => { setFrom(value); setPage(1); }} onToChange={value => { setTo(value); setPage(1); }} />
                    <select value={status} onChange={event => { setStatus(event.target.value); setPage(1); }} aria-label="Filter transactions by status" className="rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm"><option value="all">All statuses</option><option value="pending">Pending</option><option value="completed">Completed</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select>
                    {canAssignBranch && <div className="w-full sm:w-64"><BranchSelect label="" value={branchFilter} onChange={v => { setBranchFilter(v); setPage(1); }} allLabel="All branches" /></div>}
                </div>

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
                        <table className="min-w-[850px] w-full divide-y divide-gray-200 table-fixed">
                            <thead className="bg-gray-100">
                                <tr>
                                    <SortableHeader field="transaction_number" sort={sort} direction={direction} onSort={handleColumnSort} className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Txn #</SortableHeader>
                                    <SortableHeader field="member" sort={sort} direction={direction} onSort={handleColumnSort} className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Member</SortableHeader>
                                    <SortableHeader field="transaction_type" sort={sort} direction={direction} onSort={handleColumnSort} className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Type</SortableHeader>
                                    <SortableHeader field="amount" sort={sort} direction={direction} onSort={handleColumnSort} className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Amount</SortableHeader>
                                    <SortableHeader field="status" sort={sort} direction={direction} onSort={handleColumnSort} className="w-20 p-3 text-xs font-medium text-left text-gray-500 uppercase">Status</SortableHeader>
                                    <SortableHeader field="transaction_date" sort={sort} direction={direction} onSort={handleColumnSort} className="w-28 p-3 text-xs font-medium text-left text-gray-500 uppercase">Date</SortableHeader>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {transactions.map((t: any) => (
                                    <tr key={t.id} className="hover:bg-gray-50">
                                        <td className="p-4 text-sm font-medium text-gray-900"><span className="block truncate" title={t.transaction_number}>{t.transaction_number}</span></td>
                                        <td className="p-4 text-sm text-gray-900"><span className="block truncate" title={t.account_holder || '—'}>{t.account_holder || '—'}</span>{t.user_number && <div className="text-xs text-gray-500 truncate" title={t.user_number}>{t.user_number}</div>}</td>
                                        <td className="p-4 overflow-hidden"><span className={`inline-block max-w-full truncate px-2.5 py-0.5 text-xs font-medium rounded ${getTypeColor(t.transaction_type)}`}>{t.reversal_of_id ? `Reversal · ${getTypeLabel(t.transaction_type)}` : getTypeLabel(t.transaction_type)}</span>{t.installment_allocations?.some((allocation: any) => allocation.is_interest_extension) && <span className="mt-1 inline-flex items-center gap-1 rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-950 ring-1 ring-amber-300"><i className="fa-solid fa-circle-exclamation" aria-hidden="true" />Post-term interest</span>}{t.reversal_of_id && <div className="text-xs text-gray-500 mt-1 truncate" title={t.reversal_of_transaction_number}>Reverses {t.reversal_of_transaction_number || `transaction #${t.reversal_of_id}`}</div>}</td>
                                        <td className={`p-4 text-sm font-semibold whitespace-nowrap ${(["withdrawal", "fee"].includes(t.transaction_type) !== !!t.reversal_of_id) ? "text-red-600" : "text-green-600"}`}>
                                            {(["withdrawal", "fee"].includes(t.transaction_type) !== !!t.reversal_of_id) ? "-" : "+"}{'\u20b1'}{Number(t.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            {Number(t.fee_amount) > 0 && <span className="mt-1 block text-xs font-normal text-amber-700">Fee {'\u20b1'}{Number(t.fee_amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>}
                                            {Number(t.fee_amount) > 0 && <span className="block text-xs font-medium text-gray-600">{t.transaction_type === 'loan_disbursement' ? 'Net disbursed' : 'Total'} {'\u20b1'}{(Number(t.amount) + (t.transaction_type === 'loan_disbursement' ? -Number(t.fee_amount) : Number(t.fee_amount))).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>}
                                        </td>
                                        <td className="w-20 p-3"><span className={`block truncate text-xs font-medium ${t.status === 'completed' ? 'text-emerald-600' : 'text-amber-600'}`} title={t.status}>{t.status}</span></td>
                                        <td className="w-28 p-3 text-sm text-gray-500"><span className="block truncate" title={t.transaction_date || '—'}>{t.transaction_date || '—'}</span></td>
                                        <td className="p-4">
                                            <div className="flex gap-1 items-center">
                                                <button onClick={() => setViewingTransaction(t)} className="p-1.5 text-gray-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors" title="View details" aria-label="View transaction details"><i className="fas fa-eye text-xs"></i></button>
                                                {t.status === 'completed' && (
                                                    <>
                                                        <button
                                                            onClick={() => reprintMutation.mutate(t.id)}
                                                            disabled={reprintMutation.isPending}
                                                            className="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors disabled:opacity-50"
                                                            title="Reprint Receipt"
                                                        >
                                                            <i className="fas fa-print text-xs"></i>
                                                        </button>
                                                        {user?.permissions?.includes('receipts.reissue') && (
                                                            <button
                                                                onClick={() => { setReissueTarget({ id: t.id, transactionNumber: t.transaction_number }); setReissueNote(''); }}
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
                                                        {(() => {
                                                            const isSystemGenerated = !EDITABLE_TYPES.includes(t.transaction_type);
                                                            // Completed rows stay hidden to avoid a wall of inert pencils;
                                                            // system-generated types keep a blocked button so the reason is discoverable.
                                                            if (t.status !== 'pending' && !isSystemGenerated) return null;
                                                            const blockedReason = editBlockReason(t);
                                                            return (
                                                                <button
                                                                    onClick={() => openEdit(t)}
                                                                    title={blockedReason ?? 'Edit transaction'}
                                                                    aria-label={blockedReason ?? 'Edit transaction'}
                                                                    className={`p-1.5 rounded-lg transition-colors ${blockedReason ? 'text-gray-300 hover:text-amber-600 hover:bg-amber-50' : 'text-gray-400 hover:text-emerald-600 hover:bg-emerald-50'}`}
                                                                >
                                                                    <i className={`fas fa-edit text-xs ${blockedReason ? 'opacity-70' : ''}`}></i>
                                                                </button>
                                                            );
                                                        })()}
                                                        {t.status === 'pending' && (
                                                            <button onClick={() => setConfirmState({
                                                                isOpen: true,
                                                                title: 'Delete Transaction',
                                                                message: `Are you sure you want to delete transaction "${t.transaction_number}"? This action cannot be undone.`,
                                                                confirmText: 'Delete',
                                                                variant: 'danger',
                                                                onConfirm: () => deleteMutation.mutate(t.id)
                                                            })} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"><i className="fas fa-trash text-xs"></i></button>
                                                        )}
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
                            lastPage={pagination.last_page || 1}
                            onChange={setPage}
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
                                {editingId && (
                                    <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                        <i className="fas fa-user-check mr-1"></i>
                                        Edits are applied only after a chairman approves them. The transaction stays pending until then.
                                    </p>
                                )}
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
                            <button type="submit" form="ledger-form" disabled={saveMutation.isPending} className="bg-emerald-600 hover:bg-emerald-700 rounded-lg focus:ring-4 focus:ring-emerald-200 text-white px-4 py-2 text-sm font-medium disabled:opacity-50 transition-colors">{saveMutation.isPending ? 'Saving...' : editingId ? 'Submit for approval' : 'Save'}</button>
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
            <Modal open={!!viewingTransaction} onClose={() => setViewingTransaction(null)} title="Transaction details" size="xl">
                {viewingTransaction && <div className="grid grid-cols-1 gap-4 p-1 sm:grid-cols-2">
                    {[
                        ['Transaction number', viewingTransaction.transaction_number], ['Type', getTypeLabel(viewingTransaction.transaction_type)],
                        ['Amount', `₱${Number(viewingTransaction.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`],
                        ['Status', viewingTransaction.status], ['Member', viewingTransaction.account_holder || '—'],
                        ['Member number', viewingTransaction.user_number || '—'], ['Account ID', viewingTransaction.account_id ?? '—'],
                        ['Fee', `\u20b1${Number(viewingTransaction.fee_amount ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`],
                        [viewingTransaction.transaction_type === 'loan_disbursement' ? 'Net disbursed' : 'Total', `\u20b1${(Number(viewingTransaction.amount) + (viewingTransaction.transaction_type === 'loan_disbursement' ? -Number(viewingTransaction.fee_amount ?? 0) : Number(viewingTransaction.fee_amount ?? 0))).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`],
                        ['Loan number', viewingTransaction.loan_number || '—'], ['Branch', viewingTransaction.branch_name || '—'],
                        ['Transaction date', viewingTransaction.transaction_date || '—'], ['Recorded by', viewingTransaction.recorded_by || '—'],
                        ['Approved by', viewingTransaction.approved_by || '—'],
                        ['Approved at', viewingTransaction.approved_at ? new Date(viewingTransaction.approved_at).toLocaleString() : '—'],
                        ['Created at', viewingTransaction.created_at ? new Date(viewingTransaction.created_at).toLocaleString() : '—'],
                        ['Reverses', viewingTransaction.reversal_of_transaction_number || '—'],
                    ].map(([label, value]) => <div key={label} className="min-w-0">
                        <div className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</div>
                        <div className="mt-1 break-words text-sm text-gray-900">{value}</div>
                    </div>)}
                    {!!viewingTransaction.installment_allocations?.length && <div className="sm:col-span-2 rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div className="text-xs font-bold uppercase tracking-wide text-amber-950">Loan installments covered</div>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {viewingTransaction.installment_allocations.map((allocation: any) => (
                                <span key={allocation.period} className={`inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm ${allocation.is_interest_extension ? 'bg-amber-200 font-semibold text-amber-950 ring-1 ring-amber-400' : 'bg-white text-gray-800 ring-1 ring-gray-200'}`}>
                                    {allocation.is_interest_extension ? 'Post-term interest only' : 'Scheduled installment'} #{allocation.period}
                                    <strong>₱{Number(allocation.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                                </span>
                            ))}
                        </div>
                    </div>}
                    <div className="sm:col-span-2"><div className="text-xs font-medium uppercase tracking-wide text-gray-500">Note</div><div className="mt-1 whitespace-pre-wrap break-words text-sm text-gray-900">{viewingTransaction.note || '—'}</div></div>
                </div>}
            </Modal>
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

            <Modal
                open={!!reissueTarget}
                onClose={() => !reissueMutation.isPending && setReissueTarget(null)}
                title="Issue Certified Replacement"
                footer={
                    <div className="flex justify-end gap-2">
                        <Button variant="secondary" onClick={() => setReissueTarget(null)} disabled={reissueMutation.isPending}>Cancel</Button>
                        <Button
                            onClick={() => reissueTarget && reissueMutation.mutate({ transactionId: reissueTarget.id, note: reissueNote.trim() })}
                            disabled={reissueNote.trim().length < 5 || reissueMutation.isPending}
                        >
                            {reissueMutation.isPending ? 'Issuing…' : 'Issue replacement'}
                        </Button>
                    </div>
                }
            >
                <div className="space-y-3">
                    <p className="text-sm text-slate-600">
                        A certified replacement receipt will be issued for transaction <strong>{reissueTarget?.transactionNumber}</strong>.
                        The reason is stored in the audit trail and printed on the receipt.
                    </p>
                    <label className="block text-sm font-medium text-slate-700">
                        Reason for replacement
                        <textarea
                            value={reissueNote}
                            onChange={event => setReissueNote(event.target.value)}
                            rows={3}
                            placeholder="e.g. Member reported the original receipt was lost"
                            className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                        />
                    </label>
                    <p className="text-xs text-slate-500">
                        {reissueNote.trim().length < 5 ? 'At least 5 characters are required.' : `${reissueNote.trim().length} characters.`}
                    </p>
                </div>
            </Modal>

            <TransactionReceiptModal receipt={receipt} onClose={() => setReceipt(null)} />
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
