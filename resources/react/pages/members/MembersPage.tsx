import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import { useAuth } from '../../contexts/AuthContext';
import MemberWizardForm from './MemberWizardForm';
import MemberProfileModal from './MemberProfileModal';
import MemberExportModal from './MemberExportModal';

export default function MembersPage() {
    const { user } = useAuth();
    const perms = user?.permissions || [];
    const canCreate = perms.includes('members.create');
    const canEdit = perms.includes('members.update');
    const canDelete = perms.includes('members.delete');
    const canExport = perms.includes('members.view');
    const canViewMemberId = perms.includes('members.view-full-details');
    const hasActions = canEdit || canDelete;
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [showWizard, setShowWizard] = useState(false);
    const [editing, setEditing] = useState<any>(null);
    const [viewing, setViewing] = useState<any>(null);
    const [showExport, setShowExport] = useState(false);
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading, refetch } = useQuery({
        queryKey: ['members', search, page],
        queryFn: () => api.get('/api/members', { params: { search: search || undefined, page, per_page: 15 } }),
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/api/members/${id}`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['members'] }); addToast('Member deleted', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const members = data?.data?.data ?? [];
    const pagination = data?.data?.pagination ?? {};

    return (
        <div>
            <div className="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
                <div className="w-full mb-1">
                    <div className="mb-4">
                        <h1 className="text-xl font-semibold text-gray-900 sm:text-2xl">Members</h1>
                        <p className="text-sm text-gray-500">Manage member profiles and information</p>
                    </div>

                    <div className="sm:flex">
                        <div className="items-center hidden mb-3 sm:flex sm:mb-0">
                            <div className="relative mt-1 lg:w-64 xl:w-96">
                                <input
                                    type="text"
                                    value={search}
                                    onChange={e => { setSearch(e.target.value); setPage(1); }}
                                    className="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    placeholder="Search members..."
                                />
                            </div>
                        </div>
                        <div className="flex items-center ml-auto space-x-2 sm:space-x-3">
                            <button type="button" onClick={() => refetch()} className="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:ring-blue-300" title="Refresh data">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </button>
                            {canExport && (
                                <button type="button" onClick={() => setShowExport(true)} className="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:ring-blue-300">
                                    <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Export
                                </button>
                            )}
                            {canCreate && (
                                <button type="button" onClick={() => { setEditing(null); setShowWizard(true); }} className="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                                    <svg className="w-5 h-5 mr-2 -ml-1" fill="currentColor" viewBox="0 0 20 20"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg>
                                    Add Member
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
                            ) : members.length === 0 ? (
                                <div className="bg-white p-16 text-center">
                                    <svg className="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <p className="text-sm text-gray-500 mt-4">No members found</p>
                                </div>
                            ) : (
                                <table className="min-w-full divide-y divide-gray-200 table-fixed">
                                    <thead className="bg-gray-100">
                                        <tr>
                                            <th scope="col" className="p-4 w-10"><div className="flex items-center"><input type="checkbox" className="w-4 h-4 bg-gray-50 rounded border-gray-300 focus:ring-3 focus:ring-blue-300" /></div></th>
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Name</th>
                                            {canViewMemberId && <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Member ID</th>}
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Status</th>
                                            <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Email</th>
                                            {hasActions && <th scope="col" className="p-4 text-xs font-medium text-left text-gray-500 uppercase">Actions</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {members.map((m: any) => (
                                            <tr key={m.id} className="hover:bg-gray-50">
                                                <td className="p-4 w-10"><input type="checkbox" className="w-4 h-4 bg-gray-50 rounded border-gray-300 focus:ring-3 focus:ring-blue-300" /></td>
                                                <td className="p-4 text-sm font-medium text-gray-900 whitespace-nowrap">{m.name || 'Unknown'}</td>
                                                {canViewMemberId && <td className="p-4 text-sm font-mono text-gray-500 whitespace-nowrap">{m.user_number || '—'}</td>}
                                                <td className="p-4 whitespace-nowrap">
                                                    <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${m.account_status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}>{m.account_status}</span>
                                                </td>
                                                <td className="p-4 text-sm text-gray-500">{m.email || '—'}</td>
                                                {hasActions && (
                                                    <td className="p-4 whitespace-nowrap">
                                                        <div className="flex items-center gap-1">
                                                            <button onClick={() => setViewing(m)} className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg" title="View"><i className="fas fa-eye text-sm"></i></button>
                                                            {canEdit && <button onClick={() => { setEditing(m); setShowWizard(true); }} className="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg" title="Edit"><i className="fas fa-edit text-sm"></i></button>}
                                                            {canDelete && <button onClick={() => { if (confirm('Delete this member?')) deleteMutation.mutate(m.id); }} className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg" title="Delete"><i className="fas fa-trash text-sm"></i></button>}
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {pagination.last_page > 1 && (
                <div className="sticky bottom-0 bg-white border-t border-gray-200 p-4 flex items-center justify-center gap-2">
                    <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg disabled:opacity-50 hover:bg-gray-50">Previous</button>
                    <span className="text-sm text-gray-500">Page {page} of {pagination.last_page}</span>
                    <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg disabled:opacity-50 hover:bg-gray-50">Next</button>
                </div>
            )}

            {showWizard && <MemberWizardForm member={editing} onClose={() => { setShowWizard(false); setEditing(null); }} />}
            {viewing && <MemberProfileModal memberId={viewing.id} onClose={() => setViewing(null)} />}
            {showExport && <MemberExportModal onClose={() => setShowExport(false)} />}
        </div>
    );
}
