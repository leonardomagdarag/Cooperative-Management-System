import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function TreasurerOverview() {
    const { data, isLoading } = useQuery({ queryKey: ['overview-treasurer'], queryFn: () => api.get('/api/dashboard') });
    const d = data?.data || {};
    if (isLoading) return <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">{[...Array(4)].map((_, i) => <div key={i} className="h-28 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />)}</div>;
    return (
        <div className="space-y-6">
            <div><h1 className="text-2xl font-bold text-slate-900 dark:text-white">Treasurer Dashboard</h1></div>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <StatCard icon="fas fa-piggy-bank" label="Total Savings" value={`₱${Number(d.total_savings || 0).toLocaleString()}`} color="emerald" />
                <StatCard icon="fas fa-landmark" label="Share Capital" value={`₱${Number(d.total_share_capital || 0).toLocaleString()}`} color="blue" />
            </div>
            {d.recent_transactions && (
                <div className="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                    <h2 className="mb-4 font-semibold text-slate-900 dark:text-white">Recent Transactions</h2>
                    <div className="space-y-2">
                        {d.recent_transactions.map((t: any) => (
                            <div key={t.id} className="flex items-center justify-between rounded-lg border border-slate-100 p-3 dark:border-slate-700">
                                <div><p className="text-sm font-medium text-slate-900 dark:text-white">{t.transaction_number}</p><p className="text-xs text-slate-500">{t.transaction_type.replace('_', ' ')}</p></div>
                                <p className="font-medium text-slate-900 dark:text-white">₱{Number(t.amount).toLocaleString()}</p>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
function StatCard({ icon, label, value, color }: { icon: string; label: string; value: any; color: string }) {
    const c: Record<string, string> = { emerald: 'bg-emerald-100 text-emerald-600', blue: 'bg-blue-100 text-blue-600' };
    return (<div className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800"><div className="flex items-center gap-3"><div className={`flex h-10 w-10 items-center justify-center rounded-lg ${c[color]}`}><i className={icon}></i></div><div><p className="text-xs text-slate-500">{label}</p><p className="text-lg font-bold text-slate-900 dark:text-white">{value}</p></div></div></div>);
}
