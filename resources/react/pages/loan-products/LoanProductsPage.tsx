import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router';
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

const peso = (v: number) => '₱' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function LoanProductsPage() {
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState<LoanProduct | null>(null);
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [form, setForm] = useState<Partial<LoanProduct>>(emptyProduct);
    const [errors, setErrors] = useState<FieldErrors>({});
    const { markDirty, reset, requestClose, renderDialog } = useDiscardGuard();
    const queryClient = useQueryClient();
    const { addToast } = useToast();
    const navigate = useNavigate();

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
    const requestCloseGuard = () => requestClose(() => { setShowModal(false); setEditing(null); });

    const activeCount = products.filter(p => p.is_active).length;
    const inactiveCount = products.length - activeCount;
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

    return (
        <div className="space-y-6">
            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-2xl font-bold text-gray-900">Loan Products</h2>
                        <p className="mt-1 text-sm text-gray-600">
                            Configure loan caps and eligibility rules. Toggle availability per product.
                        </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        {canManage && (
                            <button
                                onClick={openCreate}
                                className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 focus:outline-none focus:ring-4 focus:ring-cyan-300"
                            >
                                <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
                                </svg>
                                Add Loan Product
                            </button>
                        )}
                        <button
                            onClick={() => navigate('/settings')}
                            className="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-100"
                        >
                            Back to Settings
                        </button>
                    </div>
                </div>
            </div>

            {!canManage && (
                <div className="rounded-lg border border-yellow-300 bg-yellow-50 p-4 text-sm text-yellow-800" role="alert">
                    <span className="font-medium">Heads up:</span> Only admins can manage loan products. You currently have
                    view-only access.
                </div>
            )}

            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p className="text-xs text-gray-500">Total</p>
                    <p className="text-2xl font-bold text-gray-900">{products.length}</p>
                </div>
                <div className="rounded-xl border border-green-200 bg-green-50 p-4 shadow-sm">
                    <p className="text-xs text-green-600">Active</p>
                    <p className="text-2xl font-bold text-green-700">{activeCount}</p>
                </div>
                <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 shadow-sm">
                    <p className="text-xs text-gray-500">Inactive</p>
                    <p className="text-2xl font-bold text-gray-600">{inactiveCount}</p>
                </div>
                <div className="rounded-xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm">
                    <p className="text-xs text-cyan-600">Loan Types</p>
                    <p className="text-2xl font-bold text-cyan-700">{typeCount}</p>
                </div>
            </div>

            {isLoading ? (
                <div className="space-y-3">{[...Array(3)].map((_, i) => <div key={i} className="h-24 animate-pulse rounded-xl bg-gray-100" />)}</div>
            ) : products.length === 0 ? (
                <div className="rounded-xl border border-dashed border-gray-300 bg-white p-16 text-center">
                    <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                        <i className="fa-solid fa-hand-holding-dollar text-2xl text-gray-400"></i>
                    </div>
                    <h3 className="mb-1 text-sm font-semibold text-gray-900">No loan products yet</h3>
                    <p className="mb-4 text-sm text-gray-500">Get started by adding your first loan product.</p>
                    {canManage && (
                        <button
                            onClick={openCreate}
                            className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 focus:outline-none focus:ring-4 focus:ring-cyan-300"
                        >
                            <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
                            </svg>
                            Add Loan Product
                        </button>
                    )}
                </div>
            ) : (
                <div className="space-y-3">
                    {products.map(p => {
                        const open = expandedId === p.id;
                        const typeBadge = p.base_type === 'group' ? 'bg-purple-50 text-purple-700' : p.base_type === 'special' ? 'bg-orange-50 text-orange-700' : 'bg-blue-50 text-blue-700';
                        const noRequirements = !p.requires_no_existing_loan && Number(p.minimum_share_capital) === 0 && Number(p.minimum_membership_years) === 0 && Number(p.required_co_makers) === 0 && !p.requires_interview && !p.requires_special_eligibility;
                        return (
                            <div
                                key={p.id}
                                className={`rounded-xl border border-gray-200 bg-white shadow-sm transition-all duration-200 ${open ? 'border-cyan-300 ring-1 ring-cyan-200 shadow-md' : ''}`}
                            >
                                <button
                                    type="button"
                                    className="flex w-full cursor-pointer items-center gap-4 px-5 py-4 text-left"
                                    onClick={() => setExpandedId(open ? null : p.id)}
                                    aria-expanded={open}
                                >
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan-50 text-cyan-600">
                                        <i className={`fa-solid fa-${p.icon || 'wallet'} text-base`}></i>
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <span className="text-sm font-semibold text-gray-900">{p.name}</span>
                                            <span className="rounded bg-cyan-50 px-2 py-0.5 text-xs font-medium text-cyan-700">
                                                {p.code.toUpperCase()}
                                            </span>
                                            <span className={`rounded px-2 py-0.5 text-xs font-medium capitalize ${typeBadge}`}>
                                                {p.base_type}
                                            </span>
                                            {p.is_active ? (
                                                <span className="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Active</span>
                                            ) : (
                                                <span className="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                                            )}
                                        </div>
                                        {p.description && <p className="mt-0.5 truncate text-xs text-gray-400">{p.description}</p>}
                                    </div>

                                    <div className="hidden shrink-0 items-center gap-6 sm:flex">
                                        <div className="text-right">
                                            <p className="text-xs text-gray-400">Max Amount</p>
                                            <p className="text-sm font-bold text-gray-900">{peso(Number(p.max_amount))}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-xs text-gray-400">Interest</p>
                                            <p className="text-sm font-bold text-gray-900">{Number(p.interest_rate).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-xs text-gray-400">Max Term</p>
                                            <p className="text-sm font-bold text-gray-900">{Number(p.max_term)} mo</p>
                                        </div>
                                    </div>

                                    <svg className={`h-5 w-5 shrink-0 text-gray-400 transition-transform duration-200 ${open ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                {open && (
                                    <div className="border-t border-gray-100 px-5 pb-5 pt-4">
                                        <div className="mb-4 grid grid-cols-3 gap-2 sm:hidden">
                                            <div className="rounded-lg bg-gray-50 p-3 text-center">
                                                <p className="text-xs text-gray-400">Max Amount</p>
                                                <p className="text-sm font-bold text-gray-900">{peso(Number(p.max_amount))}</p>
                                            </div>
                                            <div className="rounded-lg bg-gray-50 p-3 text-center">
                                                <p className="text-xs text-gray-400">Interest</p>
                                                <p className="text-sm font-bold text-gray-900">{Number(p.interest_rate).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%</p>
                                            </div>
                                            <div className="rounded-lg bg-gray-50 p-3 text-center">
                                                <p className="text-xs text-gray-400">Max Term</p>
                                                <p className="text-sm font-bold text-gray-900">{Number(p.max_term)} mo</p>
                                            </div>
                                        </div>

                                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div className="flex-1">
                                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                                    Eligibility Requirements
                                                </p>
                                                <div className="flex flex-wrap gap-2">
                                                    {p.requires_no_existing_loan && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                                                            <i className="fa-solid fa-ban text-xs"></i> No active loan
                                                        </span>
                                                    )}
                                                    {Number(p.minimum_share_capital) > 0 && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                                                            <i className="fa-solid fa-coins text-xs"></i>
                                                            Min share: {peso(Number(p.minimum_share_capital))}
                                                        </span>
                                                    )}
                                                    {Number(p.minimum_membership_years) > 0 && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">
                                                            <i className="fa-solid fa-calendar-days text-xs"></i>
                                                            {Number(p.minimum_membership_years)}+ yrs member
                                                        </span>
                                                    )}
                                                    {Number(p.required_co_makers) > 0 && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                                                            <i className="fa-solid fa-users text-xs"></i>
                                                            {Number(p.required_co_makers)} co-maker(s)
                                                        </span>
                                                    )}
                                                    {p.requires_interview && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                                                            <i className="fa-solid fa-comments text-xs"></i> Interview required
                                                        </span>
                                                    )}
                                                    {p.requires_special_eligibility && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-orange-50 px-3 py-1 text-xs font-medium text-orange-700">
                                                            <i className="fa-solid fa-star text-xs"></i> Special eligibility
                                                        </span>
                                                    )}
                                                    {noRequirements && <span className="text-xs italic text-gray-400">No special requirements</span>}
                                                </div>
                                                {p.description && <p className="mt-3 text-sm text-gray-600">{p.description}</p>}
                                            </div>
                                            <div className="shrink-0 text-right text-xs text-gray-400">
                                                <p>Sort order: {Number(p.sort_order)}</p>
                                            </div>
                                        </div>

                                        {canManage && (
                                            <div className="mt-4 flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
                                                <button
                                                    onClick={() => openEdit(p)}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-1.5 text-xs font-medium text-cyan-700 hover:bg-cyan-100 focus:outline-none focus:ring-2 focus:ring-cyan-300"
                                                >
                                                    <i className="fa-solid fa-pen-to-square text-xs"></i> Edit
                                                </button>
                                                <button
                                                    onClick={() => toggleMutation.mutate(p.id)}
                                                    disabled={toggleMutation.isPending}
                                                    className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium focus:outline-none focus:ring-2 disabled:opacity-50 ${
                                                        p.is_active
                                                            ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 focus:ring-amber-300'
                                                            : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100 focus:ring-green-300'
                                                    }`}
                                                >
                                                    <i className={`fa-solid fa-${p.is_active ? 'eye-slash' : 'eye'} text-xs`}></i>
                                                    {p.is_active ? 'Disable' : 'Enable'}
                                                </button>
                                                <button
                                                    onClick={() => { if (confirm(`Are you sure you want to delete '${p.name}'? This action cannot be undone.`)) deleteMutation.mutate(p.id); }}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-300"
                                                >
                                                    <i className="fa-solid fa-trash text-xs"></i> Delete
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            {showModal && (
                <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-16" onClick={requestCloseGuard}>
                    <div className="absolute inset-0 bg-gray-900 opacity-50"></div>
                    <div className="relative z-10 w-full max-w-2xl" onClick={e => e.stopPropagation()}>
                        <div className="relative rounded-xl bg-white shadow-2xl">
                            <div className="flex items-start justify-between rounded-t-xl border-b border-gray-200 px-6 py-4">
                                <div>
                                    <h3 id="loan-modal-title" className="text-lg font-semibold text-gray-900">
                                        {editing ? 'Edit Loan Product' : 'Add Loan Product'}
                                    </h3>
                                    <p className="mt-0.5 text-xs text-gray-500">
                                        {editing
                                            ? 'Update product limits and eligibility rules.'
                                            : 'Define a new product with limits and member eligibility rules.'}
                                    </p>
                                </div>
                                <button
                                    onClick={requestCloseGuard}
                                    className="ml-4 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-300"
                                >
                                    <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                                    </svg>
                                </button>
                            </div>

                            <div className="max-h-[70vh] overflow-y-auto px-6 py-5">
                                <form id="loan-product-form" onSubmit={handleSubmit} className="space-y-4">
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <TextInput
                                            label="Code"
                                            value={form.code}
                                            onChange={v => setField('code', v)}
                                            required
                                            maxLength={50}
                                            placeholder="agri_boost"
                                            helper="Unique slug, e.g. agri_boost"
                                            error={errors.code}
                                        />
                                        <TextInput
                                            label="Display Name"
                                            value={form.name}
                                            onChange={v => setField('name', v)}
                                            required
                                            maxLength={120}
                                            placeholder="Agriculture Boost Loan"
                                            error={errors.name}
                                        />
                                    </div>

                                    <div>
                                        <label className="mb-2 block text-sm font-medium text-gray-900">
                                            Base Type <span className="text-red-500">*</span>
                                        </label>
                                        <select
                                            value={form.base_type}
                                            onChange={e => setField('base_type', e.target.value)}
                                            className="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500"
                                        >
                                            <option value="group">Group</option>
                                            <option value="business">Business</option>
                                            <option value="special">Special</option>
                                        </select>
                                    </div>

                                    <div className="grid grid-cols-3 gap-3">
                                        <NumberInput label="Interest Rate (%)" value={form.interest_rate} onChange={v => setField('interest_rate', v)} step="0.01" min={0} error={errors.interest_rate} />
                                        <NumberInput label="Max Amount (₱)" value={form.max_amount} onChange={v => setField('max_amount', v)} step="0.01" min={0} error={errors.max_amount} />
                                        <NumberInput label="Max Term (months)" value={form.max_term} onChange={v => setField('max_term', v)} min={1} error={errors.max_term} />
                                    </div>

                                    <div className="grid grid-cols-3 gap-3">
                                        <NumberInput label="Min Share Capital (₱)" value={form.minimum_share_capital} onChange={v => setField('minimum_share_capital', v)} step="0.01" min={0} error={errors.minimum_share_capital} />
                                        <NumberInput label="Min Membership Yrs" value={form.minimum_membership_years} onChange={v => setField('minimum_membership_years', v)} min={0} error={errors.minimum_membership_years} />
                                        <NumberInput label="Required Co-Makers" value={form.required_co_makers} onChange={v => setField('required_co_makers', v)} min={0} error={errors.required_co_makers} />
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <TextInput
                                            label="Icon"
                                            value={form.icon}
                                            onChange={v => setField('icon', v)}
                                            helper="(Font Awesome name)"
                                            placeholder="wallet, briefcase, users, seedling"
                                        />
                                        <NumberInput label="Sort Order" value={form.sort_order} onChange={v => setField('sort_order', v)} min={0} error={errors.sort_order} />
                                    </div>

                                    <div>
                                        <label className="mb-2 block text-sm font-medium text-gray-900">Description</label>
                                        <textarea
                                            value={form.description || ''}
                                            onChange={e => { markDirty(); setForm(f => ({ ...f, description: e.target.value })); }}
                                            rows={3}
                                            placeholder="Brief description of this loan product..."
                                            className="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500"
                                        />
                                    </div>

                                    <fieldset className="rounded-lg border border-gray-200 p-4">
                                        <legend className="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            Flags & Requirements
                                        </legend>
                                        <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            <Checkbox label="Active product" checked={form.is_active} onChange={v => setField('is_active', v)} />
                                            <Checkbox label="No existing active loan" checked={form.requires_no_existing_loan} onChange={v => setField('requires_no_existing_loan', v)} />
                                            <Checkbox label="Require interview schedule" checked={form.requires_interview} onChange={v => setField('requires_interview', v)} />
                                            <Checkbox label="Require special-eligibility flag" checked={form.requires_special_eligibility} onChange={v => setField('requires_special_eligibility', v)} />
                                        </div>
                                    </fieldset>
                                </form>
                            </div>

                            <div className="flex items-center justify-end gap-2 rounded-b-xl border-t border-gray-200 px-6 py-4">
                                <button
                                    type="button"
                                    onClick={requestCloseGuard}
                                    className="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-100"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    form="loan-product-form"
                                    disabled={saveMutation.isPending}
                                    className="inline-flex items-center gap-2 rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 focus:outline-none focus:ring-4 focus:ring-cyan-300 disabled:opacity-60"
                                >
                                    {saveMutation.isPending ? 'Saving...' : editing ? 'Update Product' : 'Create Product'}
                                </button>
                            </div>
                        </div>
                    </div>
                    {renderDialog()}
                </div>
            )}
        </div>
    );
}

function TextInput({ label, value, onChange, required, maxLength, placeholder, helper, error }: { label: string; value: any; onChange: (v: any) => void; required?: boolean; maxLength?: number; placeholder?: string; helper?: string; error?: string }) {
    return (
        <div>
            <label className="mb-2 block text-sm font-medium text-gray-900">
                {label} {required && <span className="text-red-500">*</span>}
            </label>
            <input
                type="text"
                value={value ?? ''}
                onChange={e => onChange(e.target.value)}
                required={required}
                maxLength={maxLength}
                placeholder={placeholder}
                className={`block w-full rounded-lg border bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500 ${error ? 'border-red-400' : 'border-gray-300'}`}
            />
            {helper && <p className="mt-1 text-xs text-gray-500">{helper}</p>}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function NumberInput({ label, value, onChange, step, min, error }: { label: string; value: any; onChange: (v: any) => void; step?: string | number; min?: number; error?: string }) {
    return (
        <div>
            <label className="mb-2 block text-sm font-medium text-gray-900">{label}</label>
            <input
                type="number"
                value={value ?? ''}
                onChange={e => onChange(Number(e.target.value))}
                step={step}
                min={min}
                className={`block w-full rounded-lg border bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-cyan-500 focus:ring-cyan-500 ${error ? 'border-red-400' : 'border-gray-300'}`}
            />
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function Checkbox({ label, checked, onChange }: { label: string; checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <label className="flex items-center gap-2 text-sm text-gray-700">
            <input
                type="checkbox"
                checked={checked}
                onChange={e => onChange(e.target.checked)}
                className="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500"
            />
            {label}
        </label>
    );
}