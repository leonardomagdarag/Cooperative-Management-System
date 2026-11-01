import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { useDiscardGuard } from '../../hooks/useDiscardGuard';
import { validate, loanProductSchema, type FieldErrors } from '../../lib/validation';
import ConfirmDialog from '../../components/ui/ConfirmDialog';
import ModalHeader from '../../components/ui/ModalHeader';
import HelpTip from '../../components/ui/HelpTip';
import SettingsTabHeader from '../settings/SettingsTabHeader';

interface LoanProduct {
    id: number; code: string; name: string; description: string; base_type: string;
    icon: string; interest_rate: number; max_amount: number; max_term: number;
    penalty_type: 'flat' | 'percent'; penalty_amount: number; penalty_frequency: 'daily' | 'weekly' | 'per_due';
    interest_extension_penalty: boolean;
    is_active: boolean; requires_no_existing_loan: boolean; minimum_share_capital: number;
    minimum_membership_years: number; required_co_makers: number; requires_interview: boolean;
    requires_special_eligibility: boolean; sort_order: number;
}

const emptyProduct: Partial<LoanProduct> = {
    code: '', name: '', description: '', base_type: 'business', icon: 'wallet',
    interest_rate: 0, max_amount: 0, max_term: 1, is_active: true,
    penalty_type: 'flat', penalty_amount: 0, penalty_frequency: 'per_due',
    interest_extension_penalty: false,
    requires_no_existing_loan: false, minimum_share_capital: 0, minimum_membership_years: 0,
    required_co_makers: 0, requires_interview: false, requires_special_eligibility: false, sort_order: 0,
};

const loanProductIcons = [
    { value: 'wallet', label: 'Wallet' },
    { value: 'briefcase', label: 'Business' },
    { value: 'users', label: 'Group' },
    { value: 'seedling', label: 'Agriculture' },
    { value: 'hand-holding-dollar', label: 'Support' },
];

const peso = (v: number) => '₱' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function LoanProductsPage() {
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState<LoanProduct | null>(null);
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [form, setForm] = useState<Partial<LoanProduct>>(emptyProduct);
    const [iconPickerOpen, setIconPickerOpen] = useState(false);
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

    useEffect(() => {
        if (!showModal) return;
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = previousOverflow; };
    }, [showModal]);

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
            <SettingsTabHeader
                eyebrow="Loan configuration"
                title="Loan Products"
                description="Configure loan caps and eligibility rules. Toggle availability per product."
                icon="fa-solid fa-hand-holding-dollar"
                action={canManage ? (
                    <button
                        onClick={openCreate}
                        className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200 transition-colors"
                    >
                        <i className="fas fa-plus text-xs"></i>
                        Add Loan Product
                    </button>
                ) : undefined}
            />

            {!canManage && (
                <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800" role="alert">
                    <span className="font-medium">Heads up:</span> Only admins can manage loan products. You currently have
                    view-only access.
                </div>
            )}

            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p className="text-xs text-gray-500">Total</p>
                    <p className="text-2xl font-bold text-gray-900">{products.length}</p>
                </div>
                <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                    <p className="text-xs text-emerald-600">Active</p>
                    <p className="text-2xl font-bold text-emerald-700">{activeCount}</p>
                </div>
                <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 shadow-sm">
                    <p className="text-xs text-gray-500">Inactive</p>
                    <p className="text-2xl font-bold text-gray-600">{inactiveCount}</p>
                </div>
                <div className="rounded-xl border border-teal-200 bg-teal-50 p-4 shadow-sm">
                    <p className="text-xs text-teal-600">Loan Types</p>
                    <p className="text-2xl font-bold text-teal-700">{typeCount}</p>
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
                            className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200 transition-colors"
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
                        const typeBadge = p.base_type === 'group' ? 'bg-purple-50 text-purple-700' : p.base_type === 'special' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700';
                        const noRequirements = !p.requires_no_existing_loan && Number(p.minimum_share_capital) === 0 && Number(p.minimum_membership_years) === 0 && Number(p.required_co_makers) === 0 && !p.requires_interview && !p.requires_special_eligibility;
                        return (
                            <div
                                key={p.id}
                                className={`rounded-xl border border-gray-200 bg-white shadow-sm transition-all duration-200 ${open ? 'border-emerald-300 ring-1 ring-emerald-200 shadow-md' : ''}`}
                            >
                                <button
                                    type="button"
                                    className="flex w-full cursor-pointer items-center gap-4 px-5 py-4 text-left"
                                    onClick={() => setExpandedId(open ? null : p.id)}
                                    aria-expanded={open}
                                >
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                        <i className={`fa-solid fa-${p.icon || 'wallet'} text-base`}></i>
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <span className="text-sm font-semibold text-gray-900">{p.name}</span>
                                            <span className="rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                                {p.code.toUpperCase()}
                                            </span>
                                            <span className={`rounded px-2 py-0.5 text-xs font-medium capitalize ${typeBadge}`}>
                                                {p.base_type}
                                            </span>
                                            {p.is_active ? (
                                                <span className="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Active</span>
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
                                                    {Number(p.penalty_amount) > 0 ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                                                            <i className="fa-solid fa-triangle-exclamation text-xs"></i>
                                                            {p.penalty_type === 'percent' ? `${Number(p.penalty_amount)}%` : peso(Number(p.penalty_amount))} {p.penalty_frequency === 'per_due' ? 'per overdue installment' : p.penalty_frequency}
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-gray-50 px-3 py-1 text-xs text-gray-500">No overdue penalty</span>
                                                    )}
                                                    <span className={`inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide ${p.interest_extension_penalty ? 'bg-amber-100 text-amber-900 ring-1 ring-amber-300' : 'bg-gray-100 text-gray-600'}`}>
                                                        <i className={`fa-solid ${p.interest_extension_penalty ? 'fa-circle-exclamation' : 'fa-circle-minus'}`} aria-hidden="true"></i>
                                                        Post-term interest: {p.interest_extension_penalty ? 'On' : 'Off'}
                                                    </span>
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
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-300 transition-colors"
                                                >
                                                    <i className="fa-solid fa-pen-to-square text-xs"></i> Edit
                                                </button>
                                                <button
                                                    onClick={() => toggleMutation.mutate(p.id)}
                                                    disabled={toggleMutation.isPending}
                                                    className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium focus:outline-none focus:ring-2 disabled:opacity-50 transition-colors ${
                                                        p.is_active
                                                            ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 focus:ring-amber-300'
                                                            : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 focus:ring-emerald-300'
                                                    }`}
                                                >
                                                    <i className={`fa-solid fa-${p.is_active ? 'eye-slash' : 'eye'} text-xs`}></i>
                                                    {p.is_active ? 'Disable' : 'Enable'}
                                                </button>
                                                <button
                                                    onClick={() => setConfirmState({
                                                        isOpen: true,
                                                        title: 'Delete Loan Product',
                                                        message: `Are you sure you want to delete '${p.name}'? This action cannot be undone.`,
                                                        confirmText: 'Delete',
                                                        variant: 'danger',
                                                        onConfirm: () => deleteMutation.mutate(p.id),
                                                    })}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-300 transition-colors"
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
                <div className="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" onClick={requestCloseGuard}>
                    <div className="absolute inset-0 bg-gray-900 opacity-50"></div>
                    <div className="relative z-10 max-h-[calc(100dvh-2rem)] w-full max-w-2xl" onClick={e => e.stopPropagation()}>
                        <div className="relative flex max-h-[calc(100dvh-2rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                            <ModalHeader
                                title={editing ? 'Edit Loan Product' : 'Add Loan Product'}
                                subtitle={editing ? 'Update product limits and eligibility rules.' : 'Define a new product with limits and member eligibility rules.'}
                                icon="fa-solid fa-hand-holding-dollar"
                                onClose={requestCloseGuard}
                            />

                            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 py-5">
                                <form id="loan-product-form" onSubmit={handleSubmit} className="space-y-5">
                                    <fieldset className="space-y-4 rounded-xl border border-gray-200 p-4">
                                        <legend className="px-2 text-sm font-semibold text-gray-900">Loan Details</legend>
                                        <p className="-mt-2 text-xs text-gray-500">Set the product name, loan limits, and how it appears in the system.</p>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <TextInput
                                            label="Code"
                                            value={form.code}
                                            onChange={v => setField('code', v)}
                                            required
                                            maxLength={50}
                                            placeholder="agri_boost"
                                            helper="Unique slug, e.g. agri_boost"
                                            helpText="A short unique identifier used by the system. It cannot contain spaces."
                                            error={errors.code}
                                        />
                                        <TextInput
                                            label="Display Name"
                                            value={form.name}
                                            onChange={v => setField('name', v)}
                                            required
                                            maxLength={120}
                                            placeholder="Agriculture Boost Loan"
                                            helpText="The name members and officers see when choosing this loan product."
                                            error={errors.name}
                                        />
                                    </div>

                                    <div>
                                        <label className="mb-2 flex items-center gap-2 text-sm font-medium text-gray-900">
                                            Base Type <span className="text-red-500">*</span>
                                            <HelpTip text="Groups products into Group, Business, or Special loans. This type is used on member applications." />
                                        </label>
                                        <select
                                            value={form.base_type}
                                            onChange={e => setField('base_type', e.target.value)}
                                            className="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-emerald-500 focus:ring-emerald-500"
                                        >
                                            <option value="group">Group</option>
                                            <option value="business">Business</option>
                                            <option value="special">Special</option>
                                        </select>
                                    </div>

                                    <div className="grid grid-cols-3 gap-3">
                                        <NumberInput label="Interest Rate (%)" value={form.interest_rate} onChange={v => setField('interest_rate', v)} step="0.01" min={0} helpText="The monthly rate used to estimate interest over the loan term." error={errors.interest_rate} />
                                        <NumberInput label="Max Amount (₱)" value={form.max_amount} onChange={v => setField('max_amount', v)} step="0.01" min={0} error={errors.max_amount} />
                                        <NumberInput label="Max Term (months)" value={form.max_term} onChange={v => setField('max_term', v)} min={1} helpText="The longest repayment term a member may select." error={errors.max_term} />
                                    </div>

                                    <div className="space-y-4">
                                        <div>
                                            <label className="mb-2 flex items-center gap-2 text-sm font-medium text-gray-900">Product Icon <HelpTip text="Choose the symbol shown beside this product in loan lists and selection screens." /></label>
                                            <div className="relative">
                                                <button type="button" aria-haspopup="listbox" aria-expanded={iconPickerOpen} onClick={() => setIconPickerOpen(open => !open)} className="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm text-gray-800 hover:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                                    <span className="flex items-center gap-2"><i className={`fa-solid fa-${form.icon || 'wallet'} w-5 text-center text-emerald-700`} aria-hidden="true" />{loanProductIcons.find(option => option.value === form.icon)?.label ?? 'Choose an icon'}</span>
                                                    <i className={`fa-solid fa-chevron-down text-xs transition-transform ${iconPickerOpen ? 'rotate-180' : ''}`} aria-hidden="true" />
                                                </button>
                                                {iconPickerOpen && <div role="listbox" aria-label="Product icon choices" className="absolute left-0 right-0 top-full z-30 mt-1 grid grid-cols-2 gap-1 rounded-lg border border-gray-200 bg-white p-2 shadow-lg sm:grid-cols-1">
                                                    {loanProductIcons.map(option => (
                                                        <button key={option.value} type="button" role="option" aria-selected={form.icon === option.value} onClick={() => { setField('icon', option.value); setIconPickerOpen(false); }} className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm ${form.icon === option.value ? 'bg-emerald-50 font-medium text-emerald-800' : 'text-gray-700 hover:bg-gray-50'}`}>
                                                            <i className={`fa-solid fa-${option.value} w-5 text-center text-base`} aria-hidden="true" />{option.label}
                                                        </button>
                                                    ))}
                                                </div>}
                                            </div>
                                        </div>
                                        <div className="max-w-sm">
                                            <NumberInput label="Sort Order" value={form.sort_order} onChange={v => setField('sort_order', v)} min={0} helpText="Lower numbers appear earlier in product lists." error={errors.sort_order} />
                                        </div>
                                        <div>
                                            <label className="mb-2 flex items-center gap-2 text-sm font-medium text-gray-900">Description <HelpTip text="A short explanation of who the product is for or what it is intended to support." /></label>
                                            <textarea value={form.description || ''} onChange={e => { markDirty(); setForm(f => ({ ...f, description: e.target.value })); }} rows={4} maxLength={500} placeholder="Briefly describe this loan product..." className="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-emerald-500 focus:ring-emerald-500" />
                                        </div>
                                    </div>
                                    </fieldset>

                                    <fieldset className="rounded-lg border border-gray-200 p-4">
                                        <legend className="px-1 text-sm font-semibold text-gray-900">Overdue Penalty</legend>
                                        <p className="mb-3 text-xs text-gray-500">Set to zero to disable penalties for this product. The policy is copied to each loan when it is created.</p>
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div>
                                                <label className="mb-2 flex items-center gap-2 text-sm font-medium text-gray-900">Charge type <HelpTip text="Choose a fixed currency amount or a percentage of the unpaid scheduled installment." /></label>
                                                <select value={form.penalty_type} onChange={e => setField('penalty_type', e.target.value)} className="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm">
                                                    <option value="flat">Flat amount (₱)</option>
                                                    <option value="percent">Percentage (%)</option>
                                                </select>
                                            </div>
                                            <NumberInput label={form.penalty_type === 'percent' ? 'Rate (%)' : 'Amount (₱)'} value={form.penalty_amount} onChange={v => setField('penalty_amount', v)} step="0.01" min={0} helpText={form.penalty_type === 'percent' ? 'Applied to the unpaid installment. The percentage does not compound.' : 'The fixed charge applied at the selected frequency.'} error={errors.penalty_amount} />
                                            <div>
                                                <label className="mb-2 flex items-center gap-2 text-sm font-medium text-gray-900">Apply <HelpTip text="Daily charges accrue each overdue day, weekly charges after each full week, and per installment applies once to each overdue installment." /></label>
                                                <select value={form.penalty_frequency} onChange={e => setField('penalty_frequency', e.target.value)} className="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm">
                                                    <option value="per_due">Once per overdue installment</option>
                                                    <option value="daily">Daily after due date</option>
                                                    <option value="weekly">Weekly after due date</option>
                                                </select>
                                            </div>
                                        </div>
                                    </fieldset>

                                    <fieldset className={`rounded-xl border-2 p-5 transition-colors ${form.interest_extension_penalty ? 'border-amber-500 bg-amber-50' : 'border-amber-200 bg-white'}`}>
                                        <legend className="px-2 text-sm font-extrabold uppercase tracking-wide text-amber-900">Post-term interest amortization</legend>
                                        <div className="flex items-start gap-4">
                                            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800" aria-hidden="true">
                                                <i className="fa-solid fa-calendar-plus text-lg"></i>
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <label htmlFor="interest-extension-penalty" className="block text-base font-bold text-gray-900">Add interest-only installments after the loan term</label>
                                                <p className="mt-1 text-sm leading-5 text-gray-700">If any scheduled amount is still unpaid after the final due date, add an installment for the final schedule's interest at every payment interval. Weekly loans add one each week.</p>
                                                <label htmlFor="interest-extension-penalty" className="mt-4 inline-flex cursor-pointer items-center gap-3 rounded-lg border border-amber-300 bg-white px-4 py-3 shadow-sm hover:bg-amber-100/60 focus-within:ring-2 focus-within:ring-amber-500">
                                                    <input id="interest-extension-penalty" type="checkbox" checked={!!form.interest_extension_penalty} onChange={e => setField('interest_extension_penalty', e.target.checked)} className="h-5 w-5 rounded border-gray-400 text-amber-700 focus:ring-amber-600" />
                                                    <span className="text-sm font-bold text-gray-900">{form.interest_extension_penalty ? 'Enabled' : 'Disabled'}</span>
                                                </label>
                                            </div>
                                        </div>
                                    </fieldset>

                                    <fieldset className="space-y-4 rounded-xl border border-gray-200 p-4">
                                        <legend className="px-2 text-sm font-semibold text-gray-900">Requirements</legend>
                                        <p className="-mt-2 text-xs text-gray-500">Set member eligibility rules and any extra steps needed to apply.</p>
                                    <div className="grid grid-cols-3 gap-3">
                                        <NumberInput label="Min Share Capital (₱)" value={form.minimum_share_capital} onChange={v => setField('minimum_share_capital', v)} step="0.01" min={0} helpText="Applicants must have at least this much share capital. Set to zero for no minimum." error={errors.minimum_share_capital} />
                                        <NumberInput label="Min Membership Yrs" value={form.minimum_membership_years} onChange={v => setField('minimum_membership_years', v)} min={0} helpText="Applicants must have been members for at least this many years." error={errors.minimum_membership_years} />
                                        <NumberInput label="Required Co-Makers" value={form.required_co_makers} onChange={v => setField('required_co_makers', v)} min={0} helpText="The number of co-makers an applicant must provide. Set to zero if none are required." error={errors.required_co_makers} />
                                    </div>

                                    <fieldset className="rounded-lg border border-gray-200 p-4">
                                        <legend className="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            Eligibility Checks
                                        </legend>
                                        <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            <Checkbox label="Active product" checked={form.is_active} onChange={v => setField('is_active', v)} helpText="Inactive products are hidden from new applications but remain attached to existing loans." />
                                            <Checkbox label="No existing active loan" checked={form.requires_no_existing_loan} onChange={v => setField('requires_no_existing_loan', v)} helpText="Blocks an application if the member already has an active loan." />
                                            <Checkbox label="Require interview schedule" checked={form.requires_interview} onChange={v => setField('requires_interview', v)} helpText="Applicants must choose an interview appointment before submitting." />
                                            <Checkbox label="Require special-eligibility flag" checked={form.requires_special_eligibility} onChange={v => setField('requires_special_eligibility', v)} helpText="Restricts this product to applicants who meet the cooperative’s special eligibility checks." />
                                        </div>
                                    </fieldset>
                                    </fieldset>
                                </form>
                            </div>

                            <div className="flex items-center justify-end gap-2 rounded-b-xl border-t border-gray-200 px-6 py-4">
                                <button
                                    type="button"
                                    onClick={requestCloseGuard}
                                    className="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-100 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    form="loan-product-form"
                                    disabled={saveMutation.isPending}
                                    className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200 disabled:opacity-60 transition-colors"
                                >
                                    {saveMutation.isPending ? 'Saving...' : editing ? 'Update Product' : 'Create Product'}
                                </button>
                            </div>
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
        </div>
    );
}

function TextInput({ label, value, onChange, required, maxLength, placeholder, helper, helpText, error }: { label: string; value: any; onChange: (v: any) => void; required?: boolean; maxLength?: number; placeholder?: string; helper?: string; helpText?: string; error?: string }) {
    return (
        <div>
            <label className="mb-2 flex items-center gap-2 text-sm font-medium text-gray-900">
                {label} {required && <span className="text-red-500">*</span>} {helpText && <HelpTip text={helpText} />}
            </label>
            <input
                type="text"
                value={value ?? ''}
                onChange={e => onChange(e.target.value)}
                required={required}
                maxLength={maxLength}
                placeholder={placeholder}
                className={`block w-full rounded-lg border bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 ${error ? 'border-red-400' : 'border-gray-300'}`}
            />
            {helper && <p className="mt-1 text-xs text-gray-500">{helper}</p>}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function NumberInput({ label, value, onChange, step, min, helpText, error }: { label: string; value: any; onChange: (v: any) => void; step?: string | number; min?: number; helpText?: string; error?: string }) {
    return (
        <div>
            <label className="mb-2 flex items-center gap-2 text-sm font-medium text-gray-900">{label} {helpText && <HelpTip text={helpText} />}</label>
            <input
                type="number"
                value={value ?? ''}
                onChange={e => onChange(Number(e.target.value))}
                step={step}
                min={min}
                className={`block w-full rounded-lg border bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-emerald-500 focus:ring-emerald-500 ${error ? 'border-red-400' : 'border-gray-300'}`}
            />
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function Checkbox({ label, checked, onChange, helpText }: { label: string; checked: boolean; onChange: (v: boolean) => void; helpText?: string }) {
    return (
        <div className="flex items-center gap-2 text-sm text-gray-700">
            <label className="flex items-center gap-2">
                <input
                    type="checkbox"
                    checked={checked}
                    onChange={e => onChange(e.target.checked)}
                    className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                />
                {label}
            </label>
            {helpText && <HelpTip text={helpText} />}
        </div>
    );
}
