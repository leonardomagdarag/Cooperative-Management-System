import { useState } from 'react';
import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query';
import { api } from '../../api/client';
import BranchBadge from '../../components/BranchBadge';
import BranchSelect from '../../components/BranchSelect';
import { useToast } from '../../contexts/ToastContext';
import { useAuth } from '../../contexts/AuthContext';
import OfficerFormModal from './OfficerFormModal';
import OfficerPermissionModal from './OfficerPermissionModal';
import OfficerProfileModal from './OfficerProfileModal';
import ConfirmDialog from '../../components/ui/ConfirmDialog';
import Pagination from '../../components/ui/Pagination';

export default function OfficersPage() {
    const { user } = useAuth();
    const perms = user?.permissions || [];
    const canCreate = perms.includes('officers.create');
    const canEdit = perms.includes('officers.update');
    const canDelete = perms.includes('officers.delete');
    const canManagePermissions = perms.includes('officers.manage-permissions');
    const canAssignBranch = perms.includes('branches.view');
    const hasActions = canEdit || canDelete || canManagePermissions;
    const [search, setSearch] = useState('');
    const [branchFilter, setBranchFilter] = useState<number | ''>('');
    const [page, setPage] = useState(1);
    const [viewing, setViewing] = useState<any>(null);
    const [showForm, setShowForm] = useState(false);
    const [editingOfficer, setEditingOfficer] = useState<any>(null);
    const [showPermissions, setShowPermissions] = useState(false);
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
        queryKey: ['officers', search, page, branchFilter],
        queryFn: () => api.get('/api/members', { params: { is_officer: true, search: search || undefined, branch_id: branchFilter || undefined, page, per_page: 15 } }),
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/api/members/${id}`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['officers'] }); addToast('Officer removed', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const officers = data?.data?.data ?? [];
    const pagination = data?.data?.pagination ?? {};

    return (
        <div>
            <div className="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
                <div className="w-full mb-1">
                    <div className="mb-4">
                        <h1 className="text-xl font-semibold text-gray-900 sm:text-2xl">Officers</h1>
                        <p className="text-sm text-gray-500">Manage cooperative officers and their permissions</p>
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
                                    placeholder="Search officers..."
                                />
                            </div>
                        </div>
                        {canAssignBranch && (
                            <div className="w-full sm:w-60">
                                <BranchSelect label="" value={branchFilter} onChange={v => { setBranchFilter(v); setPage(1); }} allLabel="All branches" />
                            </div>
                        )}
                        <div className="flex items-center space-x-2 sm:space-x-3 ml-auto">
                            <button type="button" onClick={() => refetch()} className="inline-flex items-center justify-center p-2 text-sm font-medium text-center text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-emerald-500/20" title="Refresh data">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </button>
                            {canCreate && (
                                <button onClick={() => { setEditingOfficer(null); setShowForm(true); }} className="inline-flex items-center justify-center px-3.5 py-2 text-sm font-medium text-center text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-xs focus:ring-2 focus:ring-emerald-500/20 transition-colors">
                                    <svg className="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg>
                                    Add Officer
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <div className="flex flex-col">
                <div className="overflow-x-auto">
                    <div className="inline-block min-w-full align-middle">
                        <div className="overflow-hidden shadow">
                            {isLoading ? (
                                <div className="bg-white p-8 text-center text-sm text-gray-500">Loading...</div>
                            ) : officers.length === 0 ? (
                                <div className="bg-white p-16 text-center">
                                    <svg className="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M9 20H2v-2a3 3 0 015.356-1.857M13 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <p className="text-sm text-gray-500 mt-4">No officers found</p>
                                </div>
                            ) : (
                                <table className="min-w-[800px] w-full divide-y divide-gray-200 table-fixed">
                                    <thead className="bg-gray-100">
                                        <tr>
                                            {canEdit && <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Officer ID</th>}
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Name</th>
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Title</th>
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Role</th>
                                            {canAssignBranch && <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Branch</th>}
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Email</th>
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {officers.map((o: any) => (
                                            <tr key={o.id} className="hover:bg-gray-50">
                                                {canEdit && <td className="p-4 text-sm font-semibold text-gray-900 whitespace-nowrap">{o.user_number}</td>}
                                                <td className="p-4 text-sm font-medium text-gray-900 truncate" title={o.name || 'Unknown'}>{o.name || 'Unknown'}</td>
                                                <td className="p-4 truncate" title={o.title}>{o.title ? <span className="px-2.5 py-0.5 text-xs font-medium bg-cyan-100 text-cyan-800 rounded">{o.title.replace(/_/g, ' ')?.replace(/\b\w/g, c => c.toUpperCase())}</span> : <span className="text-xs text-gray-400">N/A</span>}</td>
                                                <td className="p-4 whitespace-nowrap"><span className="px-2.5 py-0.5 text-xs font-medium bg-purple-100 text-purple-800 rounded capitalize">{o.primary_role || 'Officer'}</span></td>
                                                {canAssignBranch && <td className="p-4 truncate" title={o.branch_name}><BranchBadge name={o.branch_name} /></td>}
                                                <td className="p-4 whitespace-nowrap"><span className={`px-2.5 py-0.5 text-xs font-medium rounded ${o.account_status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'}`}>{o.account_status === 'active' ? 'Active' : 'Inactive'}</span></td>
                                                <td className="p-4 text-sm text-gray-500 truncate" title={o.email || '—'}>{o.email || '—'}</td>
                                                <td className="p-4 whitespace-nowrap">
                                                    <div className="flex items-center gap-1">
                                                        <button onClick={() => setViewing(o)} className="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer" title="View"><i className="fas fa-eye text-sm"></i></button>
                                                        {canEdit && <button onClick={() => { setEditingOfficer(o); setShowForm(true); }} className="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer" title="Edit"><i className="fas fa-edit text-sm"></i></button>}
                                                        {canManagePermissions && <button onClick={() => { setViewing(null); setEditingOfficer(o); setShowPermissions(true); }} className="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors cursor-pointer" title="Permissions"><i className="fas fa-user-shield text-sm"></i></button>}
                                                        {canDelete && <button onClick={() => setConfirmState({
                                                            isOpen: true,
                                                            title: 'Remove Officer',
                                                            message: `Are you sure you want to remove ${o.name || 'this officer'}?`,
                                                            confirmText: 'Remove',
                                                            variant: 'danger',
                                                            onConfirm: () => deleteMutation.mutate(o.id),
                                                        })} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer" title="Delete"><i className="fas fa-trash text-sm"></i></button>}
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
                    lastPage={pagination.last_page || 1}
                    onChange={setPage}
                    total={pagination.total}
                    perPage={pagination.per_page}
                />
            </div>

            {viewing && <OfficerProfileModal officer={viewing} onClose={() => setViewing(null)} onEdit={(fullOfficer) => { setViewing(null); setEditingOfficer(fullOfficer); setShowForm(true); }} onManagePermissions={() => { setViewing(null); setEditingOfficer(viewing); setShowPermissions(true); }} onDelete={() => { setConfirmState({ isOpen: true, title: 'Remove Officer', message: 'Are you sure you want to remove this officer?', confirmText: 'Remove', variant: 'danger', onConfirm: () => { deleteMutation.mutate(viewing.id); setViewing(null); } }); }} canEdit={canEdit} canDelete={canDelete} canManagePermissions={canManagePermissions} />}
            {showForm && <OfficerFormModal officer={editingOfficer} onClose={() => { setShowForm(false); setEditingOfficer(null); }} />}
            {showPermissions && editingOfficer && <OfficerPermissionModal officer={editingOfficer} onClose={() => { setShowPermissions(false); setEditingOfficer(null); }} />}
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
