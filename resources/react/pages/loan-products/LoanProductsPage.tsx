import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

interface LoanProduct {
    id: number; code: string; name: string; description: string; base_type: string;
    icon: string; interest_rate: number; max_amount: number; max_term: number;
    is_active: boolean; requires_no_existing_loan: boolean; minimum_share_capital: number;
    minimum_membership_years: number; required_co_makers: number; requires_interview: boolean;
    requires_special_eligibility: boolean; sort_order: number;
}

const emptyProduct: Partial<LoanProduct> = {
    code: '', name: '', description: '', base_type: 'business', icon: 'wallet',
    interest_rate: 0, max_amount: 0, max_term: 1, is_active: true,
    requires_no_existing_loan: false, minimum_share_capital: 0, minimum_membership_years: 0,
    required_co_makers: 0, requires_interview: false, requires_special_eligibility: false, sort_order: 0,
};

export default function LoanProductsPage() {
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState<LoanProduct | null>(null);
    const [form, setForm] = useState<Partial<LoanProduct>>(emptyProduct);
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['loan-products'],
        queryFn: () => api.get('/api/loan-products'),
    });

    const saveMutation = useMutation({
        mutationFn: (payload: any) => editing
            ? api.put(`/api/loan-products/${editing.id}`, payload)
            : api.post('/api/loan-products', payload),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['loan-products'] });
            setShowModal(false); setEditing(null); setForm(emptyProduct);
            addToast(editing ? 'Loan product updated' : 'Loan product created', 'success');
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to save', 'error'),
    });

    const toggleMutation = useMutation({
        mutationFn: (id: number) => api.post(`/api/loan-products/${id}/toggle`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['loan-products'] }); addToast('Status updated', 'success'); },
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/api/loan-products/${id}`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['loan-products'] }); addToast('Loan product deleted', 'success'); },
    });

    const products: LoanProduct[] = data?.data?.data ?? [];
    const canManage = data?.data?.can_manage;

    const openEdit = (p: LoanProduct) => { setEditing(p); setForm(p); setShowModal(true); };
    const openCreate = () => { setEditing(null); setForm(emptyProduct); setShowModal(true); };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        saveMutation.mutate(form);
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Loan Products</h1>
                {canManage && <button onClick={openCreate} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"><i className="fas fa-plus mr-1"></i> Add Product</button>}
            </div>

            {isLoading ? (
                <div className="space-y-3">{[...Array(3)].map((_, i) => <div key={i} className="h-20 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />)}</div>
            ) : (
                <div className="space-y-3">
                    {products.map(p => (
                        <div key={p.id} className={`rounded-xl border bg-white p-4 dark:bg-slate-800 ${p.is_active ? 'border-slate-200 dark:border-slate-700' : 'border-slate-200 opacity-60 dark:border-slate-700'}`}>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                                        <i className="fas fa-wallet text-emerald-600"></i>
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-slate-900 dark:text-white">{p.name}</h3>
                                        <p className="text-xs text-slate-500">{p.code} · {p.base_type} · {p.interest_rate}% interest · Max ₱{Number(p.max_amount).toLocaleString()} · {p.max_term}mo</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <button onClick={() => toggleMutation.mutate(p.id)} className={`rounded-full px-3 py-1 text-xs font-medium ${p.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                                        {p.is_active ? 'Active' : 'Inactive'}
                                    </button>
                                    {canManage && (
                                        <>
                                            <button onClick={() => openEdit(p)} className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700"><i className="fas fa-edit"></i></button>
                                            <button onClick={() => { if (confirm('Delete this product?')) deleteMutation.mutate(p.id); }} className="rounded-lg p-2 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"><i className="fas fa-trash"></i></button>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setShowModal(false)}>
                    <div className="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800" onClick={e => e.stopPropagation()}>
                        <h2 className="mb-4 text-lg font-bold text-slate-900 dark:text-white">{editing ? 'Edit' : 'New'} Loan Product</h2>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="Code" value={form.code} onChange={v => setForm(f => ({ ...f, code: v }))} required />
                                <Field label="Name" value={form.name} onChange={v => setForm(f => ({ ...f, name: v }))} required />
                                <Field label="Base Type" value={form.base_type} onChange={v => setForm(f => ({ ...f, base_type: v }))} type="select" options={['business', 'group', 'special']} />
                                <Field label="Icon" value={form.icon} onChange={v => setForm(f => ({ ...f, icon: v }))} />
                                <Field label="Interest Rate (%)" value={form.interest_rate} onChange={v => setForm(f => ({ ...f, interest_rate: Number(v) }))} type="number" />
                                <Field label="Max Amount" value={form.max_amount} onChange={v => setForm(f => ({ ...f, max_amount: Number(v) }))} type="number" />
                                <Field label="Max Term (months)" value={form.max_term} onChange={v => setForm(f => ({ ...f, max_term: Number(v) }))} type="number" />
                                <Field label="Sort Order" value={form.sort_order} onChange={v => setForm(f => ({ ...f, sort_order: Number(v) }))} type="number" />
                                <Field label="Min Share Capital" value={form.minimum_share_capital} onChange={v => setForm(f => ({ ...f, minimum_share_capital: Number(v) }))} type="number" />
                                <Field label="Min Membership Years" value={form.minimum_membership_years} onChange={v => setForm(f => ({ ...f, minimum_membership_years: Number(v) }))} type="number" />
                                <Field label="Required Co-Makers" value={form.required_co_makers} onChange={v => setForm(f => ({ ...f, required_co_makers: Number(v) }))} type="number" />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Description</label>
                                <textarea value={form.description || ''} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} rows={2} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" />
                            </div>
                            <div className="flex flex-wrap gap-4">
                                <Toggle label="Active" checked={form.is_active} onChange={v => setForm(f => ({ ...f, is_active: v }))} />
                                <Toggle label="Requires No Existing Loan" checked={form.requires_no_existing_loan} onChange={v => setForm(f => ({ ...f, requires_no_existing_loan: v }))} />
                                <Toggle label="Requires Interview" checked={form.requires_interview} onChange={v => setForm(f => ({ ...f, requires_interview: v }))} />
                                <Toggle label="Requires Special Eligibility" checked={form.requires_special_eligibility} onChange={v => setForm(f => ({ ...f, requires_special_eligibility: v }))} />
                            </div>
                            <div className="flex justify-end gap-3">
                                <button type="button" onClick={() => setShowModal(false)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                                <button type="submit" disabled={saveMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                    {saveMutation.isPending ? 'Saving...' : 'Save'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

function Field({ label, value, onChange, type = 'text', options, required }: { label: string; value: any; onChange: (v: any) => void; type?: string; options?: string[]; required?: boolean }) {
    if (type === 'select') return (
        <div><label className="mb-1 block text-sm font-medium text-slate-700">{label}</label>
            <select value={value} onChange={e => onChange(e.target.value)} required={required} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700">
                {options?.map(o => <option key={o} value={o}>{o}</option>)}
            </select></div>
    );
    return (
        <div><label className="mb-1 block text-sm font-medium text-slate-700">{label}</label>
            <input type={type} value={value ?? ''} onChange={e => onChange(type === 'number' ? Number(e.target.value) : e.target.value)} required={required} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700" /></div>
    );
}

function Toggle({ label, checked, onChange }: { label: string; checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" checked={checked} onChange={e => onChange(e.target.checked)} className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
            {label}
        </label>
    );
}
