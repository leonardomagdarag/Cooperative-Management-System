import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getBranches, createBranch, updateBranch, deleteBranch, Branch } from '../../api/branches';
import { useToast } from '../../contexts/ToastContext';
import Button from '../../components/ui/Button';
import Badge from '../../components/ui/Badge';
import EmptyState from '../../components/ui/EmptyState';
import ConfirmDialog from '../../components/ui/ConfirmDialog';
import PageHeader from '../../components/ui/PageHeader';
import { useAuth } from '../../contexts/AuthContext';

interface BranchForm {
    name: string;
    is_head_office: boolean;
    address: string;
    contact_number: string;
    email: string;
    is_active: boolean;
}

const emptyForm: BranchForm = { name: '', is_head_office: false, address: '', contact_number: '', email: '', is_active: true };

export default function BranchesPage() {
    const [search, setSearch] = useState('');
    const [expandedBranches, setExpandedBranches] = useState<number[]>([]);
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState<Branch | null>(null);
    const [form, setForm] = useState<BranchForm>(emptyForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [confirmState, setConfirmState] = useState<{
        isOpen: boolean;
        title: string;
        message: string;
        confirmText?: string;
        variant?: 'danger' | 'warning' | 'primary';
        onConfirm: () => void;
    }>({ isOpen: false, title: '', message: '', onConfirm: () => {} });
    const queryClient = useQueryClient();
    const { addToast } = useToast();
    const { user } = useAuth();
    const canCreate = !!user?.permissions?.includes('branches.create');
    const canUpdate = !!user?.permissions?.includes('branches.update');
    const canDelete = !!user?.permissions?.includes('branches.delete');

    const { data, isLoading } = useQuery({
        queryKey: ['branches', search],
        queryFn: () => getBranches({ search }),
    });

    const branches: Branch[] = data?.data?.data ?? [];

    const invalidate = () => {
        queryClient.invalidateQueries({ queryKey: ['branches'] });
        queryClient.invalidateQueries({ queryKey: ['branch-options'] });
    };

    const saveMutation = useMutation({
        mutationFn: (payload: BranchForm) => editing
            ? updateBranch(editing.id, payload)
            : createBranch(payload),
        onSuccess: () => {
            invalidate();
            setShowModal(false);
            addToast(editing ? 'Branch updated' : 'Branch created', 'success');
        },
        onError: (err: any) => {
            const msg = err?.response?.data?.message || 'Failed to save branch';
            addToast(msg, 'error');
            const fieldErrors = err?.response?.data?.errors;
            if (fieldErrors) setErrors(fieldErrors);
        },
    });

    const deleteMutation = useMutation({
        mutationFn: deleteBranch,
        onSuccess: () => {
            invalidate();
            addToast('Branch deleted', 'success');
        },
        onError: (err: any) => addToast(err?.response?.data?.message || 'Failed to delete branch', 'error'),
    });

    const openCreate = () => { setEditing(null); setForm(emptyForm); setErrors({}); setShowModal(true); };
    const openEdit = (b: Branch) => {
        setEditing(b);
        setForm({
            name: b.name,
            is_head_office: b.is_head_office,
            address: b.address ?? '',
            contact_number: b.contact_number ?? '',
            email: b.email ?? '',
            is_active: b.is_active,
        });
        setErrors({});
        setShowModal(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!form.name.trim()) {
            setErrors({ name: 'Branch name is required' });
            return;
        }
        setErrors({});
        saveMutation.mutate(form);
    };

    return (
        <div className="max-w-7xl mx-auto p-4 sm:p-6">
            <PageHeader className="mb-6" title="Branches" description="Manage cooperative offices and branch affiliations" icon="fas fa-building" action={<div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder="Search branches…"
                        className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition-colors"
                    />
                    {canCreate && <Button onClick={openCreate} icon={<i className="fas fa-plus" />}>New Branch</Button>}
                </div>} />

            {isLoading ? (
                <div className="space-y-3">
                    {[...Array(3)].map((_, i) => <div key={i} className="h-28 animate-pulse rounded-lg bg-gray-100" />)}
                </div>
            ) : branches.length === 0 ? (
                <EmptyState icon="fa-building" title="No branches found" />
            ) : (
                <div className="grid items-start gap-4 lg:grid-cols-2">
                    {branches.map(b => {
                        const expanded = expandedBranches.includes(b.id);
                        return (
                            <article key={b.id} className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div className="p-5">
                                    <div className="flex items-start justify-between gap-4">
                                        <button
                                            type="button"
                                            aria-expanded={expanded}
                                            aria-controls={`branch-details-${b.id}`}
                                            onClick={() => setExpandedBranches(current => expanded
                                                ? current.filter(id => id !== b.id)
                                                : [...current, b.id])}
                                            className="min-w-0 flex-1 text-left"
                                        >
                                            <div className="flex flex-wrap items-center gap-2">
                                                <i className={`fas fa-chevron-${expanded ? 'down' : 'right'} text-xs text-gray-400`} aria-hidden="true" />
                                                <span className="truncate text-base font-semibold text-gray-900" title={b.name}>{b.name}</span>
                                                {b.is_head_office && <Badge variant="info">Head Office</Badge>}
                                                <Badge variant={b.is_active ? 'success' : 'neutral'}>{b.is_active ? 'Active' : 'Inactive'}</Badge>
                                            </div>
                                            <span className="ml-5 mt-1 block text-xs font-mono text-gray-400">{b.code}</span>
                                        </button>
                                        <div className="flex shrink-0 gap-1">
                                            {canUpdate && <Button variant="ghost" size="sm" onClick={() => openEdit(b)} aria-label={`Edit ${b.name}`} className="hover:bg-emerald-50 hover:text-emerald-600"><i className="fas fa-edit"></i></Button>}
                                            {canDelete && <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setConfirmState({
                                                    isOpen: true,
                                                    title: 'Delete Branch',
                                                    message: `Delete "${b.name}"? Affected records will keep their branch link.`,
                                                    confirmText: 'Delete',
                                                    variant: 'danger',
                                                    onConfirm: () => deleteMutation.mutate(b.id),
                                                })}
                                                aria-label={`Delete ${b.name}`}
                                                className="hover:bg-red-50 hover:text-red-600"
                                            >
                                                <i className="fas fa-trash"></i>
                                            </Button>}
                                        </div>
                                    </div>
                                    <div className="mt-4 grid grid-cols-3 gap-3 border-t border-gray-100 pt-4 text-center">
                                        <div><div className="text-lg font-semibold text-gray-900">{b.members_count}</div><div className="text-xs text-gray-500">Members</div></div>
                                        <div><div className="text-lg font-semibold text-gray-900">{b.officers?.length ?? 0}</div><div className="text-xs text-gray-500">Officers</div></div>
                                        <div><div className="text-lg font-semibold text-gray-900">{b.loans_count}</div><div className="text-xs text-gray-500">Loans</div></div>
                                    </div>
                                    {expanded && (
                                        <div id={`branch-details-${b.id}`} className="mt-4 space-y-4 border-t border-gray-100 pt-4">
                                            <div className="grid gap-3 text-sm sm:grid-cols-2">
                                                <div><div className="text-xs font-semibold uppercase tracking-wide text-gray-400">Address</div><div className="mt-1 text-gray-700">{b.address || '—'}</div></div>
                                                <div><div className="text-xs font-semibold uppercase tracking-wide text-gray-400">Contact</div><div className="mt-1 text-gray-700">{b.contact_number || '—'}</div><div className="text-xs text-gray-500">{b.email || '—'}</div></div>
                                                <div><div className="text-xs font-semibold uppercase tracking-wide text-gray-400">Users</div><div className="mt-1 text-gray-700">{b.users_count} assigned users</div></div>
                                                <div><div className="text-xs font-semibold uppercase tracking-wide text-gray-400">Transactions</div><div className="mt-1 text-gray-700">{b.transactions_count}</div></div>
                                            </div>
                                            <section aria-label={`Officers assigned to ${b.name}`}>
                                                <h3 className="mb-2 text-sm font-semibold text-gray-800">Assigned officers</h3>
                                                {(b.officers ?? []).length === 0 ? (
                                                    <p className="rounded-lg bg-gray-50 px-3 py-4 text-sm text-gray-500">No officers assigned to this branch.</p>
                                                ) : (
                                                    <ul className="divide-y divide-gray-100 rounded-lg border border-gray-100">
                                                        {b.officers.map(officer => (
                                                            <li key={officer.id} className="flex items-center justify-between gap-3 px-3 py-2.5">
                                                                <div className="min-w-0">
                                                                    <div className="truncate text-sm font-medium text-gray-800">{officer.name}</div>
                                                                    <div className="text-xs capitalize text-gray-500">{officer.role.replaceAll('_', ' ')}</div>
                                                                </div>
                                                                <Badge variant={officer.status === 'active' ? 'success' : 'neutral'}>{officer.status}</Badge>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                )}
                                            </section>
                                        </div>
                                    )}
                                </div>
                            </article>
                        );
                    })}
                </div>
            )}

            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={() => setShowModal(false)}>
                    <div className="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden" onClick={e => e.stopPropagation()}>
                        <div className="bg-gradient-to-r from-emerald-600 to-cyan-600 px-6 py-5 text-white shrink-0">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-bold">{editing ? 'Edit Branch' : 'New Branch'}</h2>
                                <button onClick={() => setShowModal(false)} className="rounded-full p-1 text-white/70 hover:bg-white/20 hover:text-white transition-colors">
                                    <i className="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <form onSubmit={submit} className="p-6 space-y-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-gray-700">Branch name <span className="text-red-500">*</span></label>
                                <input
                                    value={form.name}
                                    onChange={e => setForm({ ...form, name: e.target.value })}
                                    className={`w-full rounded-lg border bg-white px-3 py-2.5 text-sm outline-none focus:ring-2 ${errors.name ? 'border-red-400 focus:ring-red-200' : 'border-gray-300 focus:border-emerald-500 focus:ring-emerald-100'}`}
                                />
                                {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <label className="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" checked={form.is_head_office} onChange={e => setForm({ ...form, is_head_office: e.target.checked })} className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                                    Head office
                                </label>
                                <label className="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" checked={form.is_active} onChange={e => setForm({ ...form, is_active: e.target.checked })} className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                                    Active
                                </label>
                            </div>

                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-gray-700">Address</label>
                                <input
                                    value={form.address}
                                    onChange={e => setForm({ ...form, address: e.target.value })}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-gray-700">Contact number</label>
                                    <input
                                        value={form.contact_number}
                                        onChange={e => setForm({ ...form, contact_number: e.target.value })}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-gray-700">Email</label>
                                    <input
                                        type="email"
                                        value={form.email}
                                        onChange={e => setForm({ ...form, email: e.target.value })}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    />
                                </div>
                            </div>

                            {errors.email && <p className="text-xs text-red-500">{errors.email}</p>}

                            <div className="flex justify-end gap-3 pt-2">
                                <Button variant="secondary" type="button" onClick={() => setShowModal(false)}>Cancel</Button>
                                <Button type="submit" disableOnClick isLoading={saveMutation.isPending}>
                                    {saveMutation.isPending ? 'Saving…' : editing ? 'Save Changes' : 'Create Branch'}
                                </Button>
                            </div>
                        </form>
                    </div>
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
