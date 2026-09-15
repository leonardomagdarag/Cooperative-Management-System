import { useState } from 'react';
import { useSearchParams } from 'react-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import BranchBadge from '../../components/BranchBadge';
import BranchSelect from '../../components/BranchSelect';
import { useToast } from '../../contexts/ToastContext';
import { useAuth } from '../../contexts/AuthContext';
import MemberWizardForm from './MemberWizardForm';
import MemberProfileModal from './MemberProfileModal';
import MemberExportModal from './MemberExportModal';
import DeclineApplicationModal from './DeclineApplicationModal';
import ConfirmDialog from '../../components/ui/ConfirmDialog';
import Pagination from '../../components/ui/Pagination';

const STATUS_FILTERS = [
    { value: 'all', label: 'All Members' },
    { value: 'pending', label: 'Pending Applications' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'terminated', label: 'Terminated' },
    { value: 'archived', label: 'Archived' },
] as const;

export default function MembersPage() {
    const { user } = useAuth();
    const perms = user?.permissions || [];
    const canCreate = perms.includes('members.create');
    const canEdit = perms.includes('members.update');
    const canDelete = perms.includes('members.delete');
    const canExport = perms.includes('members.view');
    const canViewMemberId = perms.includes('members.view-full-details');
    const canApprove = perms.includes('members.approve');
    const canAssignBranch = perms.includes('branches.view');
    const hasActions = canEdit || canDelete;
    const isAdmin = user?.primary_role === 'admin';
    const [searchParams, setSearchParams] = useSearchParams();
    const [status, setStatus] = useState(searchParams.get('status') && STATUS_FILTERS.some(f => f.value === searchParams.get('status')) ? searchParams.get('status')! : 'all');
    const [search, setSearch] = useState('');
    const [branchFilter, setBranchFilter] = useState<number | ''>('');
    const [page, setPage] = useState(1);
    const [showWizard, setShowWizard] = useState(false);
    const [editing, setEditing] = useState<any>(null);
    const [viewing, setViewing] = useState<any>(null);
    const [showExport, setShowExport] = useState(false);
    const [declining, setDeclining] = useState<any>(null);
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

    const { data, isLoading, refetch } = useQuery({
        queryKey: ['members', search, page, status, branchFilter],
        queryFn: () => api.get('/api/members', { params: { search: search || undefined, page, per_page: 15, status, branch_id: branchFilter || undefined } }),
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/api/members/${id}`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['members'] }); addToast('Member deleted', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const approveMutation = useMutation({
        mutationFn: (id: number) => api.post(`/api/members/${id}/approve`),
        onSuccess: (_, id) => { queryClient.invalidateQueries({ queryKey: ['members'] }); queryClient.invalidateQueries({ queryKey: ['member', id] }); addToast('Application approved. Activation email sent.', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to approve application', 'error'),
    });

    const declineMutation = useMutation({
        mutationFn: ({ id, reason }: { id: number; reason: string }) => api.post(`/api/members/${id}/decline`, { reason }),
        onSuccess: (_, { id }) => { queryClient.invalidateQueries({ queryKey: ['members'] }); queryClient.invalidateQueries({ queryKey: ['member', id] }); addToast('Application declined. Email sent to the applicant.', 'success'); setDeclining(null); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to decline application', 'error'),
    });

    const restoreMutation = useMutation({
        mutationFn: (id: number) => api.post(`/api/members/${id}/restore`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['members'] }); addToast('Member restored', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to restore member', 'error'),
    });

    const forceDeleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/api/members/${id}/force`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['members'] }); addToast('Member permanently deleted', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed to permanently delete member', 'error'),
    });

    const members = data?.data?.data ?? [];
    const pagination = data?.data?.pagination ?? {};
    const pendingCount = data?.data?.pending_count ?? 0;

    const selectStatus = (value: string) => {
        setStatus(value);
        setPage(1);
        if (value === 'pending') {
            setSearchParams({ status: 'pending' });
        } else {
            setSearchParams({});
        }
    };

    return (
        <div>
            <div className="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
                <div className="w-full mb-1">
                    <div className="mb-4">
                        <h1 className="text-xl font-semibold text-gray-900 sm:text-2xl">Members</h1>
                        <p className="text-sm text-gray-500">Manage member profiles, applications and information</p>
                    </div>

                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="w-full sm:w-72 lg:w-96">
                            <div className="relative">
                                <span className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                    <i className="fas fa-search text-sm"></i>
                                </span>
                                <input
                                    type="text"
                                    value={search}
                                    onChange={e => { setSearch(e.target.value); setPage(1); }}
                                    className="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 block w-full pl-9 pr-3 py-2 transition-all"
                                    placeholder="Search members by name, email, ID..."
                                />
                            </div>
                        </div>
                        <div className="flex items-center space-x-2 sm:space-x-3 self-end sm:self-auto">
                            <button type="button" onClick={() => refetch()} className="inline-flex items-center justify-center p-2 text-sm font-medium text-center text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-emerald-500/20" title="Refresh data">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </button>
                            {canExport && (
                                <button type="button" onClick={() => setShowExport(true)} className="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-emerald-500/20">
                                    <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Export
                                </button>
                            )}
                            {canCreate && (
                                <button type="button" onClick={() => { setEditing(null); setShowWizard(true); }} className="inline-flex items-center justify-center px-3.5 py-2 text-sm font-medium text-center text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-xs focus:ring-2 focus:ring-emerald-500/20 transition-colors">
                                    <svg className="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg>
                                    Add Member
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap items-center gap-2">
                        {STATUS_FILTERS.map(f => (
                            <button
                                key={f.value}
                                onClick={() => selectStatus(f.value)}
                                className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors ${status === f.value ? 'bg-emerald-600 border-emerald-600 text-white shadow-xs' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50'}`}
                            >
                                {f.label}
                                {f.value === 'pending' && pendingCount > 0 && (
                                    <span className={`px-1.5 py-0.5 text-[10px] font-bold rounded-full ${status === f.value ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-700'}`}>{pendingCount}</span>
                                )}
                            </button>
                        ))}
                        {canAssignBranch && (
                            <div className="sm:ml-auto w-full sm:w-64 mt-2 sm:mt-0">
                                <BranchSelect label="Filter by Branch" value={branchFilter} onChange={v => { setBranchFilter(v); setPage(1); }} allLabel="All branches" />
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <div className="flex flex-col">
                <div className="overflow-x-auto">
                    <div className="inline-block min-w-full align-middle">
                        <div className="overflow-hidden shadow">
                            {isLoading ? (
                                <div className="bg-white p-8 text-center text-sm text-gray-500">Loading...</div>
                            ) : members.length === 0 ? (
                                <div className="bg-white p-16 text-center">
                                    <svg className="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <p className="text-sm text-gray-500 mt-4">No members found</p>
                                </div>
                            ) : (
                                <table className="min-w-full divide-y divide-gray-200 table-fixed">
                                    <thead className="bg-gray-100">
                                        <tr>
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase w-[24%]">Name</th>
                                            {canViewMemberId && <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase w-32">Member ID</th>}
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase w-32">Status</th>
                                            {canAssignBranch && <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase w-28">Branch</th>}
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase w-28">Join Date</th>
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase w-[30%]">Email</th>
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase w-28">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {members.map((m: any) => (
                                            <tr key={m.id} className="hover:bg-gray-50">
                                                <td className="p-4 text-sm font-medium text-gray-900 whitespace-nowrap">{m.name || 'Unknown'}</td>
                                                {canViewMemberId && <td className="p-4 text-sm font-mono text-gray-500 whitespace-nowrap">{m.user_number || '—'}</td>}
                                                <td className="p-4 whitespace-nowrap">
                                                    {m.archived_at ? (
                                                        <span className="px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">Archived</span>
                                                    ) : m.has_pending_application ? (
                                                        <span className="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-700">Pending Approval</span>
                                                    ) : (
                                                        <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${m.account_status === 'active' ? 'bg-emerald-100 text-emerald-700' : m.account_status === 'terminated' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'}`}>{m.account_status}</span>
                                                    )}
                                                </td>
                                                {canAssignBranch && <td className="p-4 whitespace-nowrap"><BranchBadge name={m.branch_name} /></td>}
                                                <td className="p-4 text-sm text-gray-500 whitespace-nowrap">{m.created_at ? new Date(m.created_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) : '—'}</td>
                                                <td className="p-4 min-w-0">
                                                    <span className="block truncate text-sm text-gray-500" title={m.email}>{m.email || '—'}</span>
                                                </td>
                                                <td className="p-4 whitespace-nowrap">
                                                    <div className="flex items-center gap-1">
                                                        <button onClick={() => setViewing(m)} className="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer" title="View"><i className="fas fa-eye text-sm"></i></button>
                                                        {m.archived_at ? (
                                                            isAdmin && <>
                                                                <button onClick={() => setConfirmState({
                                                                    isOpen: true,
                                                                    title: 'Restore Member',
                                                                    message: `Restore archived member ${m.name || 'this member'}?`,
                                                                    confirmText: 'Restore',
                                                                    variant: 'primary',
                                                                    onConfirm: () => restoreMutation.mutate(m.id),
                                                                })} disabled={restoreMutation.isPending} className="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer" title="Restore"><i className="fas fa-undo text-sm"></i></button>
                                                                <button onClick={() => setConfirmState({
                                                                    isOpen: true,
                                                                    title: 'Permanently Delete Member',
                                                                    message: `Are you sure you want to permanently delete ${m.name || 'this member'}? This cannot be undone.`,
                                                                    confirmText: 'Permanently Delete',
                                                                    variant: 'danger',
                                                                    onConfirm: () => forceDeleteMutation.mutate(m.id),
                                                                })} disabled={forceDeleteMutation.isPending} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer" title="Permanent delete"><i className="fas fa-trash text-sm"></i></button>
                                                            </>
                                                        ) : (
                                                            <>
                                                                {canEdit && <button onClick={() => { setEditing(m); setShowWizard(true); }} className="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer" title="Edit"><i className="fas fa-edit text-sm"></i></button>}
                                                                {canDelete && <button onClick={() => setConfirmState({
                                                                    isOpen: true,
                                                                    title: 'Delete Member',
                                                                    message: `Are you sure you want to delete ${m.name || 'this member'}?`,
                                                                    confirmText: 'Delete',
                                                                    variant: 'danger',
                                                                    onConfirm: () => deleteMutation.mutate(m.id),
                                                                })} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer" title="Delete"><i className="fas fa-trash text-sm"></i></button>}
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <div className="bg-white border-t border-gray-200 px-4 py-3">
                <Pagination
                    page={page}
                    totalPages={pagination.last_page || 1}
                    onPageChange={setPage}
                    total={pagination.total}
                    perPage={pagination.per_page}
                />
            </div>

            {showWizard && <MemberWizardForm member={editing} onClose={() => { setShowWizard(false); setEditing(null); }} />}
            {viewing && <MemberProfileModal memberId={viewing.id} onClose={() => setViewing(null)} canApprove={canApprove} onApprove={(id) => approveMutation.mutate(id)} onDecline={() => setDeclining(viewing)} isApproving={approveMutation.isPending} isDeclining={declineMutation.isPending} onEdit={() => { setViewing(null); setEditing(viewing); setShowWizard(true); }} canEdit={canEdit} canViewFullId={canViewMemberId} isAdmin={isAdmin} onRestore={() => { setConfirmState({ isOpen: true, title: 'Restore Member', message: 'Restore this archived member?', confirmText: 'Restore', variant: 'primary', onConfirm: () => { restoreMutation.mutate(viewing.id); setViewing(null); } }); }} onForceDelete={() => { setConfirmState({ isOpen: true, title: 'Permanently Delete Member', message: 'Permanently delete this member? This cannot be undone.', confirmText: 'Permanently Delete', variant: 'danger', onConfirm: () => { forceDeleteMutation.mutate(viewing.id); setViewing(null); } }); }} isRestoring={restoreMutation.isPending} isForceDeleting={forceDeleteMutation.isPending} />}
            {declining && <DeclineApplicationModal member={declining} onConfirm={(reason) => declineMutation.mutate({ id: declining.id, reason })} onClose={() => setDeclining(null)} submitting={declineMutation.isPending} />}
            {showExport && <MemberExportModal onClose={() => setShowExport(false)} />}
            <ConfirmDialog
                isOpen={confirmState.isOpen}
                onClose={() => setConfirmState(s => ({ ...s, isOpen: false }))}
                title={confirmState.title}
                message={confirmState.message}
                confirmText={confirmState.confirmText}
                variant={confirmState.variant}
                onConfirm={confirmState.onConfirm}
            />
        </div>
    );
}