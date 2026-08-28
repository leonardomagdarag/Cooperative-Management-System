import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';

export default function ExpensesPage() {
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const [page, setPage] = useState(1);
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState<any>(null);
    const [form, setForm] = useState({ category: '', description: '', amount: '', expense_date: new Date().toISOString().slice(0, 10) });
    const { markDirty, reset, requestClose, renderDialog } = useDiscardGuard();
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['expenses', search, category, page],
        queryFn: () => api.get('/api/expenses', { params: { search: search || undefined, category: category === 'all' ? undefined : category, page, per_page: 15 } }),
    });

    const expenses = data?.data?.data ?? [];
    const pagination = data?.data?.pagination ?? {};
    const categories: string[] = data?.data?.categories ?? [];
    const totalAmount = Number(data?.data?.total_amount ?? 0);
    const canManage = data?.data?.can_manage ?? false;

    const saveMutation = useMutation({
        mutationFn: (payload: any) => editing ? api.put(`/api/expenses/${editing.id}`, payload) : api.post('/api/expenses', payload),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['expenses'] }); setShowModal(false); setEditing(null); addToast(editing ? 'Expense updated' : 'Expense recorded', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/api/expenses/${id}`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['expenses'] }); addToast('Expense deleted', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to delete expense', 'error'),
    });

    const set = (key: string, value: string) => { markDirty(); setForm(f => ({ ...f, [key]: value })); };

    const openAdd = () => { reset(); setEditing(null); setForm({ category: '', description: '', amount: '', expense_date: new Date().toISOString().slice(0, 10) }); setShowModal(true); };
    const openEdit = (e: any) => { reset(); setEditing(e); setForm({ category: e.category, description: e.description || '', amount: String(e.amount), expense_date: e.expense_date }); setShowModal(true); };

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        if (!form.category || !form.amount) { addToast('Category and amount are required', 'error'); return; }
        saveMutation.mutate({ category: form.category, description: form.description, amount: Number(form.amount), expense_date: form.expense_date });
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
                        <div className="relative mt-1 lg:w-64">
                            <input type="text" value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="Search expenses..." />
                        </div>
                        <select value={category} onChange={e => { setCategory(e.target.value); setPage(1); }} className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 p-2.5">
                            <option value="all">All Categories</option>
                            {categories.map(c => <option key={c} value={c}>{c}</option>)}
                        </select>
                        {canManage && (
                            <button onClick={openAdd} className="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                                <i className="fas fa-plus mr-1"></i> Add Expense
                            </button>
                        )}
                    </div>
                </div>
            </div>

            <div className="p-4">
                <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p className="text-sm text-amber-700">Total recorded expenses</p>
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
                    <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-100">
                                <tr>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Date</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Category</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Description</th>
                                    <th className="p-4 text-xs font-medium text-right text-gray-500 uppercase">Amount</th>
                                    <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Recorded By</th>
                                    {canManage && <th className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>}
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-100">
                                {expenses.map((e: any) => (
                                    <tr key={e.id} className="hover:bg-gray-50">
                                        <td className="p-4 text-sm text-gray-500 whitespace-nowrap">{e.expense_date}</td>
                                        <td className="p-4"><span className="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-700">{e.category}</span></td>
                                        <td className="p-4 text-sm text-gray-700">{e.description || '—'}</td>
                                        <td className="p-4 text-sm font-medium text-right text-gray-900">₱{Number(e.amount).toLocaleString()}</td>
                                        <td className="p-4 text-sm text-gray-500">{e.recorded_by || '—'}</td>
                                        {canManage && (
                                            <td className="p-4">
                                                <div className="flex gap-1">
                                                    <button onClick={() => openEdit(e)} className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg"><i className="fas fa-edit text-xs"></i></button>
                                                    <button onClick={() => { if (confirm('Delete this expense?')) deleteMutation.mutate(e.id); }} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"><i className="fas fa-trash text-xs"></i></button>
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
                    <div className="sticky bottom-0 bg-white border-t border-gray-200 p-4 flex items-center justify-center gap-2">
                        <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm disabled:opacity-50 hover:bg-gray-50">Previous</button>
                        <span className="text-sm text-gray-500">Page {page} of {pagination.last_page}</span>
                        <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm disabled:opacity-50 hover:bg-gray-50">Next</button>
                    </div>
                )}
            </div>

            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => requestClose(() => setShowModal(false))}>
                    <div className="w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                        <div className="p-6">
                            <h2 className="mb-4 text-lg font-bold text-gray-900">{editing ? 'Edit' : 'Add'} Expense</h2>
                            <form onSubmit={handleSave} className="space-y-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Category</label>
                                    <input type="text" list="expense-categories" value={form.category} onChange={e => set('category', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="e.g., Utilities, Salaries" />
                                    <datalist id="expense-categories">
                                        {categories.map(c => <option key={c} value={c} />)}
                                    </datalist>
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Description</label>
                                    <input type="text" value={form.description} onChange={e => set('description', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="Optional" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Amount</label>
                                    <input type="number" step="0.01" value={form.amount} onChange={e => set('amount', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="0.00" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-gray-700">Date</label>
                                    <input type="date" value={form.expense_date} onChange={e => set('expense_date', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                                </div>
                                <div className="flex justify-end gap-3">
                                    <button type="button" onClick={() => requestClose(() => setShowModal(false))} className="rounded-lg border border-gray-200 px-4 py-2 text-sm">Cancel</button>
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
