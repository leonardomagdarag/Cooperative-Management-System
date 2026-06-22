import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useToast } from '../../contexts/ToastContext';

export default function TransactionApproval() {
    const queryClient = useQueryClient();
    const { addToast } = useToast();

    const { data, isLoading } = useQuery({
        queryKey: ['pending-transactions'],
        queryFn: () => api.get('/api/services/pending'),
    });

    const approveMutation = useMutation({
        mutationFn: ({ id, action, reason }: { id: number; action: string; reason?: string }) =>
            api.post(`/api/services/approve/${id}`, { action, reason }),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['pending-transactions'] }); addToast('Transaction processed', 'success'); },
        onError: (err: any) => addToast(err.response?.data?.message || 'Failed', 'error'),
    });

    const transactions = data?.data?.data ?? [];

    const getTypeColor = (t: string) => {
        const c: Record<string, string> = { deposit: 'bg-emerald-100 text-emerald-700', withdrawal: 'bg-red-100 text-red-700', loan_payment: 'bg-blue-100 text-blue-700' };
        return c[t] || 'bg-slate-100 text-slate-700';
    };

    return (
        <div className="space-y-6">
            <h2 className="text-lg font-bold text-slate-900 dark:text-white">Pending Approvals</h2>

            {isLoading ? (
                <div className="space-y-3">{[...Array(3)].map((_, i) => <div key={i} className="h-20 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />)}</div>
            ) : transactions.length === 0 ? (
                <div className="rounded-lg bg-slate-50 p-8 text-center dark:bg-slate-700">
                    <i className="fas fa-check-circle text-3xl text-slate-300 dark:text-slate-500"></i>
                    <p className="mt-2 text-sm text-slate-500">No pending transactions</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {transactions.map((t: any) => (
                        <div key={t.id} className="rounded-lg border border-slate-200 p-4 dark:border-slate-600">
                            <div className="flex items-center justify-between">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${getTypeColor(t.transaction_type)}`}>{t.transaction_type.replace('_', ' ')}</span>
                                        <span className="text-sm font-medium text-slate-900 dark:text-white">{t.transaction_number}</span>
                                    </div>
                                    <p className="mt-1 text-sm text-slate-500">{t.account_holder || 'Unknown'} · {new Date(t.created_at).toLocaleDateString()}</p>
                                </div>
                                <div className="text-right">
                                    <p className="font-bold text-slate-900 dark:text-white">₱{Number(t.amount).toLocaleString()}</p>
                                    <div className="mt-2 flex gap-1">
                                        <button onClick={() => approveMutation.mutate({ id: t.id, action: 'approve' })} disabled={approveMutation.isPending} className="rounded bg-emerald-600 px-3 py-1 text-xs font-medium text-white hover:bg-emerald-700">Approve</button>
                                        <button onClick={() => { const reason = prompt('Rejection reason:'); if (reason !== null) approveMutation.mutate({ id: t.id, action: 'reject', reason }); }} disabled={approveMutation.isPending} className="rounded bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700">Reject</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
