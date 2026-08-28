import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { validate, loanProductSchema, type FieldErrors } from '../../lib/validation';

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
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [form, setForm] = useState<Partial<LoanProduct>>(emptyProduct);
    const [errors, setErrors] = useState<FieldErrors>({});
    const { markDirty, reset, requestClose, renderDialog } = useDiscardGuard();
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
            setShowModal(false); setEditing(null); setForm(emptyProduct); reset();
            addToast(editing ? 'Loan product updated' : 'Loan product created', 'success');
        },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to save', 'error'),
    });

    const toggleMutation = useMutation({
        mutationFn: (id: number) => api.post(`/api/loan-products/${id}/toggle`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['loan-products'] }); addToast('Status updated', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to update status', 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/api/loan-products/${id}`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['loan-products'] }); addToast('Loan product deleted', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to delete loan product', 'error'),
    });

    const products: LoanProduct[] = data?.data?.data ?? [];
    const canManage = data?.data?.can_manage;

    const openEdit = (p: LoanProduct) => { reset(); setEditing(p); setForm(p); setErrors({}); setShowModal(true); };
    const openCreate = () => { reset(); setEditing(null); setForm(emptyProduct); setErrors({}); setShowModal(true); };

    const activeCount = products.filter(p => p.is_active).length;
    const typeCount = [...new Set(products.map(p => p.base_type))].length;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const errs = validate(loanProductSchema, form);
        if (Object.keys(errs).length) { setErrors(errs); return; }
        saveMutation.mutate(form);
    };

    const setField = (key: keyof LoanProduct, value: any) => {
        markDirty();
        setErrors(prev => { const next = { ...prev }; delete next[key]; return next; });
        setForm(f => ({ ...f, [key]: value }));
    };
    const requestCloseGuard = () => requestClose(() => { setShowModal(false); setEditing(null); });

    return (
        <div className="space-y-6">
            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">Loan Products</h1>
                    <div className="flex items-center gap-3">
                        <a href="/dashboard/settings" className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                            <i className="fas fa-arrow-left mr-1"></i> Back to Settings
                        </a>
                        {canManage && (
                            <button onClick={openCreate} className="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700">
                                <i className="fas fa-plus mr-1"></i> Add Loan Product
                            </button>
                        )}
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="rounded-xl border border-gray-200 bg-green-50 p-4 shadow-sm">
                    <p className="text-sm font-medium text-green-600">Active Products</p>
                    <p className="mt-1 text-2xl font-bold text-green-700">{activeCount}</p>
                </div>
                <div className="rounded-xl border border-gray-200 bg-cyan-50 p-4 shadow-sm">
                    <p className="text-sm font-medium text-cyan-600">Product Types</p>
                    <p className="mt-1 text-2xl font-bold text-cyan-700">{typeCount}</p>
                </div>
                <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 shadow-sm">
                    <p className="text-sm font-medium text-gray-600">Total Products</p>
                    <p className="mt-1 text-2xl font-bold text-gray-700">{products.length}</p>
                </div>
            </div>

            {isLoading ? (
                <div className="space-y-3">{[...Array(3)].map((_, i) => <div key={i} className="h-20 animate-pulse rounded-xl bg-gray-100" />)}</div>
            ) : (
                <div className="space-y-3">
                    {products.map(p => (
                        <div key={p.id} className={`rounded-xl border bg-white p-4 shadow-sm transition-shadow hover:shadow ${p.is_active ? 'border-gray-200' : 'border-gray-200 opacity-60'}`}>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100">
                                        <i className="fas fa-wallet text-emerald-600"></i>
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-gray-900">{p.name}</h3>
                                        <p className="text-xs text-gray-500">{p.code} &middot; {p.base_type} &middot; {p.interest_rate}% interest &middot; Max &#8369;{Number(p.max_amount).toLocaleString()} &middot; {p.max_term}mo</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        onClick={() => toggleMutation.mutate(p.id)}
                                        className={`rounded-full px-3 py-1 text-xs font-medium ${p.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'}`}
                                    >
                                        {p.is_active ? 'Active' : 'Inactive'}
                                    </button>
                                    <button onClick={() => setExpandedId(expandedId === p.id ? null : p.id)} className="rounded-lg p-2 text-gray-400 hover:bg-gray-100">
                                        <i className={`fas fa-chevron-${expandedId === p.id ? 'up' : 'down'} text-xs`}></i>
                                    </button>
                                    {canManage && (
                                        <>
                                            <button onClick={() => openEdit(p)} className="rounded-lg p-2 text-gray-400 hover:bg-gray-100"><i className="fas fa-edit"></i></button>
                                            <button onClick={() => { if (confirm('Delete this product?')) deleteMutation.mutate(p.id); }} className="rounded-lg p-2 text-red-400 hover:bg-red-50"><i className="fas fa-trash"></i></button>
                                        </>
                                    )}
                                </div>
                            </div>
                            {expandedId === p.id && (
                                <div className="mt-4 grid grid-cols-2 gap-3 border-t border-gray-100 pt-4 text-sm sm:grid-cols-4">
                                    <div><span className="text-gray-500">Min Share Capital</span><p className="font-medium text-gray-900">&#8369;{Number(p.minimum_share_capital).toLocaleString()}</p></div>
                                    <div><span className="text-gray-500">Min Membership</span><p className="font-medium text-gray-900">{p.minimum_membership_years} years</p></div>
                                    <div><span className="text-gray-500">Co-Makers</span><p className="font-medium text-gray-900">{p.required_co_makers}</p></div>
                                    <div><span className="text-gray-500">Sort Order</span><p className="font-medium text-gray-900">{p.sort_order}</p></div>
                                    <div className="col-span-2"><span className="text-gray-500">Description</span><p className="font-medium text-gray-900">{p.description || '\u2014'}</p></div>
                                    <div className="col-span-2 flex flex-wrap gap-2">
                                        {p.requires_no_existing_loan && <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">No Existing Loan</span>}
                                        {p.requires_interview && <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Interview Required</span>}
                                        {p.requires_special_eligibility && <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Special Eligibility</span>}
                                    </div>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={requestCloseGuard}>
                    <div className="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" onClick={e => e.stopPropagation()}>
                        <h2 className="mb-4 text-lg font-bold text-gray-900">{editing ? 'Edit' : 'New'} Loan Product</h2>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="Code" value={form.code} onChange={v => setField('code', v)} error={errors.code} required />
                                <Field label="Name" value={form.name} onChange={v => setField('name', v)} error={errors.name} required />
                                <Field label="Base Type" value={form.base_type} onChange={v => setField('base_type', v)} error={errors.base_type} type="select" options={['business', 'group', 'special']} />
                                <Field label="Icon" value={form.icon} onChange={v => setField('icon', v)} error={errors.icon} />
                                <Field label="Interest Rate (%)" value={form.interest_rate} onChange={v => setField('interest_rate', v)} error={errors.interest_rate} type="number" />
                                <Field label="Max Amount" value={form.max_amount} onChange={v => setField('max_amount', v)} error={errors.max_amount} type="number" />
                                <Field label="Max Term (months)" value={form.max_term} onChange={v => setField('max_term', v)} error={errors.max_term} type="number" />
                                <Field label="Sort Order" value={form.sort_order} onChange={v => setField('sort_order', v)} error={errors.sort_order} type="number" />
                                <Field label="Min Share Capital" value={form.minimum_share_capital} onChange={v => setField('minimum_share_capital', v)} error={errors.minimum_share_capital} type="number" />
                                <Field label="Min Membership Years" value={form.minimum_membership_years} onChange={v => setField('minimum_membership_years', v)} error={errors.minimum_membership_years} type="number" />
                                <Field label="Required Co-Makers" value={form.required_co_makers} onChange={v => setField('required_co_makers', v)} error={errors.required_co_makers} type="number" />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Description</label>
                                <textarea value={form.description || ''} onChange={e => { markDirty(); setForm(f => ({ ...f, description: e.target.value })); }} rows={2} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                            </div>
                            <div className="flex flex-wrap gap-4">
                                <Toggle label="Active" checked={form.is_active} onChange={v => setField('is_active', v)} />
                                <Toggle label="Requires No Existing Loan" checked={form.requires_no_existing_loan} onChange={v => setField('requires_no_existing_loan', v)} />
                                <Toggle label="Requires Interview" checked={form.requires_interview} onChange={v => setField('requires_interview', v)} />
                                <Toggle label="Requires Special Eligibility" checked={form.requires_special_eligibility} onChange={v => setField('requires_special_eligibility', v)} />
                            </div>
                            <div className="flex justify-end gap-3">
                                <button type="button" onClick={requestCloseGuard} className="rounded-lg border border-gray-200 px-4 py-2 text-sm">Cancel</button>
                                <button type="submit" disabled={saveMutation.isPending} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                    {saveMutation.isPending ? 'Saving...' : 'Save'}
                                </button>
                            </div>
                        </form>
                    </div>
                    {renderDialog()}
                </div>
            )}
        </div>
    );
}

function Field({ label, value, onChange, type = 'text', options, required, error }: { label: string; value: any; onChange: (v: any) => void; type?: string; options?: string[]; required?: boolean; error?: string }) {
    const border = error ? 'border-red-400 focus:border-red-500' : 'border-gray-200';
    if (type === 'select') return (
        <div><label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            <select value={value} onChange={e => onChange(e.target.value)} required={required} className={`w-full rounded-lg border ${border} px-3 py-2 text-sm`}>
                {options?.map(o => <option key={o} value={o}>{o}</option>)}
            </select>
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
    return (
        <div><label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            <input type={type} value={value ?? ''} onChange={e => onChange(type === 'number' ? Number(e.target.value) : e.target.value)} required={required} className={`w-full rounded-lg border ${border} px-3 py-2 text-sm`} />
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}

function Toggle({ label, checked, onChange }: { label: string; checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <label className="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" checked={checked} onChange={e => onChange(e.target.checked)} className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
            {label}
        </label>
    );
}
