import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function ManagerOverview() {
    const { data, isLoading } = useQuery({ queryKey: ['overview-manager'], queryFn: () => api.get('/api/dashboard') });
    const d = data?.data || {};
    if (isLoading) return <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">{[...Array(4)].map((_, i) => <div key={i} className="h-28 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />)}</div>;
    return (
        <div className="space-y-6">
            <div><h1 className="text-2xl font-bold text-slate-900 dark:text-white">Manager Dashboard</h1></div>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <StatCard icon="fas fa-users" label="Members" value={d.total_members || 0} color="emerald" />
                <StatCard icon="fas fa-piggy-bank" label="Total Savings" value={`₱${Number(d.total_savings || 0).toLocaleString()}`} color="blue" />
                <StatCard icon="fas fa-hourglass-half" label="Pending" value={d.pending_transactions || 0} color="amber" />
                <StatCard icon="fas fa-check-circle" label="Active Loans" value={d.active_loans || 0} color="emerald" />
            </div>
        </div>
    );
}
function StatCard({ icon, label, value, color }: { icon: string; label: string; value: any; color: string }) {
    const c: Record<string, string> = { emerald: 'bg-emerald-100 text-emerald-600', blue: 'bg-blue-100 text-blue-600', amber: 'bg-amber-100 text-amber-600' };
    return (<div className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800"><div className="flex items-center gap-3"><div className={`flex h-10 w-10 items-center justify-center rounded-lg ${c[color]}`}><i className={icon}></i></div><div><p className="text-xs text-slate-500">{label}</p><p className="text-lg font-bold text-slate-900 dark:text-white">{value}</p></div></div></div>);
}
