import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function SecretaryOverview() {
    const { data, isLoading } = useQuery({
        queryKey: ['overview-secretary'],
        queryFn: () => api.get('/api/profile'),
    });

    const profile = data?.data;

    if (isLoading) {
        return (
            <div className="space-y-4">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {[...Array(4)].map((_, i) => <div key={i} className="h-28 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />)}
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Secretary Dashboard</h1>
                <p className="text-slate-500">Overview of cooperative activities.</p>
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <StatCard icon="fas fa-users" label="Total Members" value="—" color="emerald" />
                <StatCard icon="fas fa-user-plus" label="New This Month" value="—" color="blue" />
                <StatCard icon="fas fa-bullhorn" label="Announcements" value="—" color="purple" />
                <StatCard icon="fas fa-check-circle" label="Active Members" value="—" color="amber" />
            </div>

            <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-600 dark:bg-slate-800">
                <p className="text-sm text-slate-500">Dashboard data will be populated when the overview API is built.</p>
            </div>
        </div>
    );
}

function StatCard({ icon, label, value, color }: { icon: string; label: string; value: string; color: string }) {
    const colors: Record<string, string> = {
        emerald: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
        blue: 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
        purple: 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400',
        amber: 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
    };
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
            <div className="flex items-center gap-3">
                <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${colors[color]}`}>
                    <i className={icon}></i>
                </div>
                <div>
                    <p className="text-xs text-slate-500">{label}</p>
                    <p className="text-lg font-bold text-slate-900 dark:text-white">{value}</p>
                </div>
            </div>
        </div>
    );
}
