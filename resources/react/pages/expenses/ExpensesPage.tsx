import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import BranchBadge from '../../components/BranchBadge';
import BranchSelect from '../../components/BranchSelect';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { validate, expenseSchema, type FieldErrors } from '../../lib/validation';
import ConfirmDialog from '../../components/ui/ConfirmDialog';
import Pagination from '../../components/ui/Pagination';

export default function ExpensesPage() {
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const [branchFilter, setBranchFilter] = useState<number | ''>('');
    const [page, setPage] = useState(1);
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState<any>(null);
    const [form, setForm] = useState({ category: '', description: '', amount: '', expense_date: new Date().toISOString().slice(0, 10), branch_id: '' });
    const [errors, setErrors] = useState<FieldErrors>({});
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

    const { data, isLoading } = useQuery({
        queryKey: ['expenses', search, category, page, branchFilter],
        queryFn: () => api.get('/api/expenses', { params: { search: search || undefined, category: category === 'all' ? undefined : category, branch_id: branchFilter || undefined, page, per_page: 15 } }),
    });

    const expenses = data?.data?.data ?? [];
    const pagination = data?.data?.pagination ?? {};
    const categories: string[] = data?.data?.categories ?? [];
    const totalAmount = Number(data?.data?.total_amount ?? 0);
    const canManage = data?.data?.can_manage ?? false;

    const saveMutation = useMutation({
        mutationFn: (payload: any) => editing ? api.put(`/api/expenses/${editing.id}`, payload) : api.post('/api/expenses', payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['expenses'] });
            reset();
            setShowModal(false);
            setEditing(null);
            addToast(editing ? 'Expense updated' : 'Expense recorded', 'success');
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    useEffect(() => {
        if (!showModal) {
            reset();
        }
    }, [showModal, reset]);

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/api/expenses/${id}`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['expenses'] }); addToast('Expense deleted', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to delete expense', 'error'),
    });

    const set = (key: string, value: string) => { markDirty(); setForm(f => ({ ...f, [key]: value })); setErrors(prev => { if (!prev[key]) return prev; const n = { ...prev }; delete n[key]; return n; }); };

    const openAdd = () => { reset(); setErrors({}); setEditing(null); setForm({ category: '', description: '', amount: '', expense_date: new Date().toISOString().slice(0, 10), branch_id: '' }); setShowModal(true); };
    const openEdit = (e: any) => { reset(); setErrors({}); setEditing(e); setForm({ category: e.category, description: e.description || '', amount: String(e.amount), expense_date: e.expense_date, branch_id: e.branch_id || '' }); setShowModal(true); };

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        const errs = validate(expenseSchema, form);
        setErrors(errs);
        if (Object.keys(errs).length > 0) return;
        saveMutation.mutate({ category: form.category, description: form.description, amount: Number(form.amount), expense_date: form.expense_date, branch_id: form.branch_id || undefined });
    };

    return (
        <>
            <div className="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
                <div className="w-full mb-1">
                    <div className="mb-4">
                        <h1 className="text-xl font-semibold text-gray-900 sm:text-2xl">Expenses</h1>
                        <p className="text-sm text-gray-500">Track cooperative operating expenses</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative mt-1 w-full sm:w-64">
                            <input type="text" value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 block w-full p-2.5 transition-all" placeholder="Search expenses..." />
                        </div>
                        <select value={category} onChange={e => { setCategory(e.target.value); setPage(1); }} className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 p-2.5">
                            <option value="all">All Categories</option>
                            {categories.map(c => <option key={c} value={c}>{c}</option>)}
                        </select>
                        {canAssignBranch && (
                            <div className="w-full sm:w-56">
                                <BranchSelect label="" value={branchFilter} onChange={v => { setBranchFilter(v); setPage(1); }} allLabel="All branches" />
                            </div>
                        )}
                        {canManage && (
                            <button onClick={openAdd} className="inline-flex items-center justify-center px-3.5 py-2 text-sm font-medium text-center text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-xs focus:ring-2 focus:ring-emerald-500/20 transition-colors cursor-pointer">
                                <i className="fas fa-plus mr-1.5"></i> Add Expense
                            </button>
                        )}
                    </div>
                </div>
            </div>

            <div className="p-4">
                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p className="text-sm font-medium text-amber-700">Total recorded expenses</p>
                    <p className="text-2xl font-bold text-amber-900">₱{totalAmount.toLocaleString()}</p>
                </div>

                {isLoading ? (
                    <div className="space-y-3">{[...Array(5)].map((_, i) => <div key={i} className="h-14 animate-pulse rounded-lg bg-gray-100" />)}</div>
                ) : expenses.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center">
                        <i className="fas fa-receipt text-4xl text-gray-300"></i>
                        <p className="mt-4 text-sm text-gray-500">No expenses found</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-xs">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-100">
                                <tr>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Date</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Category</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Description</th>
                                    <th className="p-4 text-xs font-medium text-right text-gray-500 uppercase">Amount</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Recorded By</th>
                                    {canAssignBranch && <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Branch</th>}
                                    {canManage && <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-100">
                                {expenses.map((e: any) => (
                                    <tr key={e.id} className="hover:bg-gray-50">
                                        <td className="p-4 text-sm text-gray-500 whitespace-nowrap">{e.expense_date}</td>
                                        <td className="p-4"><span className="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-700">{e.category}</span></td>
                                        <td className="p-4 text-sm text-gray-700">{e.description || '—'}</td>
                                        <td className="p-4 text-sm font-medium text-right text-gray-900">₱{Number(e.amount).toLocaleString()}</td>
                                        <td className="p-4 text-sm text-gray-500">{e.recorded_by || '—'}</td>
                                        {canAssignBranch && <td className="p-4 whitespace-nowrap"><BranchBadge name={e.branch_name} /></td>}
                                        {canManage && (
                                            <td className="p-4">
                                                <div className="flex gap-1">
                                                    <button onClick={() => openEdit(e)} className="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer" title="Edit"><i className="fas fa-edit text-xs"></i></button>
                                                    <button onClick={() => setConfirmState({
                                                        isOpen: true,
                                                        title: 'Delete Expense',
                                                        message: `Delete this expense record of ₱${Number(e.amount).toLocaleString()} (${e.category})?`,
                                                        confirmText: 'Delete',
                                                        variant: 'danger',
                                                        onConfirm: () => deleteMutation.mutate(e.id),
                                                    })} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer" title="Delete"><i className="fas fa-trash text-xs"></i></button>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {pagination.last_page > 1 && (
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

            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={() => requestClose(() => setShowModal(false))}>
                    <div className="w-full max-w-md max-h-[90vh] overflow-hidden rounded-2xl bg-white shadow-2xl flex flex-col" onClick={e => e.stopPropagation()}>
                        <div className="bg-linear-to-r from-emerald-600 to-cyan-600 px-6 py-5 text-white shrink-0">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-bold">{editing ? 'Edit' : 'Add'} Expense</h2>
                                <button onClick={() => requestClose(() => setShowModal(false))} className="rounded-full p-1 text-white/70 hover:bg-white/20 hover:text-white cursor-pointer"><i className="fas fa-times"></i></button>
                            </div>
                        </div>
                        <div className="flex-1 overflow-y-auto p-6">
                            <form id="expense-form" onSubmit={handleSave} className="space-y-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Category <span className="text-red-500">*</span></label>
                                    <input type="text" list="expense-categories" value={form.category} onChange={e => set('category', e.target.value)} className={`w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 ${errors.category ? 'border-red-400' : 'border-gray-200'}`} placeholder="e.g., Utilities, Salaries" />
                                    <datalist id="expense-categories">
                                        {categories.map(c => <option key={c} value={c} />)}
                                    </datalist>
                                    {errors.category && <p className="mt-1 text-xs text-red-600">{errors.category}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Description</label>
                                    <input type="text" value={form.description} onChange={e => set('description', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" placeholder="Optional" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Amount <span className="text-red-500">*</span></label>
                                    <input type="number" step="0.01" min="0" value={form.amount} onChange={e => set('amount', e.target.value)} className={`w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 ${errors.amount ? 'border-red-400' : 'border-gray-200'}`} placeholder="0.00" />
                                    {errors.amount && <p className="mt-1 text-xs text-red-600">{errors.amount}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Date <span className="text-red-500">*</span></label>
                                    <input type="date" value={form.expense_date} onChange={e => set('expense_date', e.target.value)} className={`w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 ${errors.expense_date ? 'border-red-400' : 'border-gray-200'}`} />
                                    {errors.expense_date && <p className="mt-1 text-xs text-red-600">{errors.expense_date}</p>}
                                </div>
                                {canAssignBranch && (
                                    <BranchSelect value={form.branch_id} onChange={v => set('branch_id', String(v))} error={errors.branch_id} />
                                )}
                            </form>
                        </div>
                        <div className="flex shrink-0 items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 rounded-b-2xl">
                            <button type="button" onClick={() => requestClose(() => setShowModal(false))} className="rounded-lg border border-gray-200 px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">Cancel</button>
                            <button type="submit" form="expense-form" disabled={saveMutation.isPending} className="bg-emerald-600 hover:bg-emerald-700 rounded-lg focus:ring-2 focus:ring-emerald-500/20 text-white px-4 py-2 text-sm font-medium shadow-xs disabled:opacity-50 transition-colors cursor-pointer">{saveMutation.isPending ? 'Saving...' : 'Save'}</button>
                        </div>
                    </div>
                    {renderDialog()}
                </div>
            )}
            <ConfirmDialog
                isOpen={confirmState.isOpen}
                onClose={() => setConfirmState(s => ({ ...s, isOpen: false }))}
                title={confirmState.title}
                message={confirmState.message}
                confirmText={confirmState.confirmText}
                variant={confirmState.variant}
                onConfirm={confirmState.onConfirm}
            />
        </>
    );
}
