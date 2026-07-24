import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';
import MemberWizardForm from './MemberWizardForm';
import MemberProfileModal from './MemberProfileModal';

export default function MembersPage() {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [showWizard, setShowWizard] = useState(false);
    const [editing, setEditing] = useState<any>(null);
    const [viewing, setViewing] = useState<any>(null);
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['members', search, page],
        queryFn: () => api.get('/api/members', { params: { search: search || undefined, page, per_page: 10 } }),
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => api.delete(`/api/members/${id}`),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['members'] }); addToast('Member deleted', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const members = data?.data?.data ?? [];
    const pagination = data?.data?.pagination;

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Members</h1>
                <button onClick={() => { setEditing(null); setShowWizard(true); }} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    <i className="fas fa-plus mr-1"></i> Add Member
                </button>
            </div>

            <input type="text" placeholder="Search members..." value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} className="w-full max-w-md rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800" />

            {isLoading ? (
                <div className="space-y-3">{[...Array(5)].map((_, i) => <div key={i} className="h-16 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />)}</div>
            ) : members.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-600 dark:bg-slate-800">
                    <i className="fas fa-users text-4xl text-slate-300 dark:text-slate-600"></i>
                    <p className="mt-4 text-sm text-slate-500">No members found</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {members.map((m: any) => (
                        <div key={m.id} className="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                                    <span className="text-sm font-bold text-emerald-600">{m.name?.charAt(0) || '?'}</span>
                                </div>
                                <div>
                                    <p className="font-medium text-slate-900 dark:text-white">{m.name || 'Unknown'}</p>
                                    <p className="text-xs text-slate-500">{m.user_number} · {m.email}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${m.account_status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>{m.account_status}</span>
                                <button onClick={() => setViewing(m)} className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700"><i className="fas fa-eye text-sm"></i></button>
                                <button onClick={() => { setEditing(m); setShowWizard(true); }} className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700"><i className="fas fa-edit text-sm"></i></button>
                                <button onClick={() => { if (confirm('Delete this member?')) deleteMutation.mutate(m.id); }} className="rounded-lg p-2 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"><i className="fas fa-trash text-sm"></i></button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {pagination && pagination.last_page > 1 && (
                <div className="flex items-center justify-center gap-2">
                    <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-50">Previous</button>
                    <span className="text-sm text-slate-500">Page {page} of {pagination.last_page}</span>
                    <button onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))} disabled={page === pagination.last_page} className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-50">Next</button>
                </div>
            )}

            {showWizard && <MemberWizardForm member={editing} onClose={() => { setShowWizard(false); setEditing(null); }} />}
            {viewing && <MemberProfileModal memberId={viewing.id} onClose={() => setViewing(null)} />}
        </div>
    );
}
