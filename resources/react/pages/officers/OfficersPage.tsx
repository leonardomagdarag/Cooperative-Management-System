import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

export default function OfficersPage() {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState<any>(null);
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['officers', search, page],
        queryFn: () => api.get('/api/loans/search-members', { params: { q: search || undefined } }),
    });

    // For now, use the member search endpoint as a placeholder
    // Full officers CRUD will be built in Phase 5 (high complexity)
    const officers = data?.data?.data ?? [];

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Officers</h1>
            </div>

            <input type="text" placeholder="Search officers..." value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} className="w-full max-w-md rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800" />

            <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-600 dark:bg-slate-800">
                <i className="fas fa-user-tie text-4xl text-slate-300 dark:text-slate-600"></i>
                <p className="mt-4 text-sm text-slate-500">Full officer management will be built in a later phase.</p>
            </div>
        </div>
    );
}
