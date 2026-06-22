import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function MemberOverview() {
    const { data, isLoading } = useQuery({
        queryKey: ['overview-member'],
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

    if (!profile) return null;

    const accounts = profile.accounts || [];
    const loans = profile.loans || [];
    const activeLoans = profile.active_loans_count || 0;

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Welcome back!</h1>
                <p className="text-slate-500">Here's an overview of your accounts.</p>
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <StatCard icon="fas fa-wallet" label="Total Balance" value={`₱${Number(profile.total_balance || 0).toLocaleString()}`} color="emerald" />
                <StatCard icon="fas fa-file-invoice-dollar" label="Active Loans" value={activeLoans.toString()} color="blue" />
                <StatCard icon="fas fa-piggy-bank" label="Accounts" value={accounts.length.toString()} color="purple" />
                <StatCard icon="fas fa-clock" label="Pending Loans" value={loans.filter((l: any) => l.loan_status === 'pending').length.toString()} color="amber" />
            </div>

            {/* Account Details */}
            <div className="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                <h2 className="mb-4 font-semibold text-slate-900 dark:text-white">Your Accounts</h2>
                {accounts.length === 0 ? (
                    <p className="text-sm text-slate-500">No accounts found.</p>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {accounts.map((a: any) => (
                            <div key={a.id} className="rounded-lg border border-slate-200 p-4 dark:border-slate-600">
                                <p className="text-sm text-slate-500">{a.account_type}</p>
                                <p className="text-xl font-bold text-slate-900 dark:text-white">₱{Number(a.balance).toLocaleString()}</p>
                                <p className="text-xs text-slate-400">{a.account_number}</p>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Active Loans */}
            {loans.length > 0 && (
                <div className="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                    <h2 className="mb-4 font-semibold text-slate-900 dark:text-white">Your Loans</h2>
                    <div className="space-y-3">
                        {loans.map((l: any) => (
                            <div key={l.id} className="flex items-center justify-between rounded-lg border border-slate-100 p-3 dark:border-slate-700">
                                <div>
                                    <p className="text-sm font-medium text-slate-900 dark:text-white">{l.loan_number}</p>
                                    <p className="text-xs text-slate-500">{l.loan_type}</p>
                                </div>
                                <div className="text-right">
                                    <p className="font-medium text-slate-900 dark:text-white">₱{Number(l.principal_amount).toLocaleString()}</p>
                                    <span className={`rounded-full px-2 py-0.5 text-xs ${
                                        l.loan_status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
                                    }`}>{l.loan_status}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
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
