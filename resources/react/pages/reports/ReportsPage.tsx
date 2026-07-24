import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function ReportsPage() {
    const [reportType, setReportType] = useState('financial');
    const [dateRange, setDateRange] = useState('month');

    const { data, isLoading } = useQuery({
        queryKey: ['reports', reportType, dateRange],
        queryFn: () => api.get('/api/dashboard'),
    });

    const overview = data?.data || {};

    const reportTypes = [
        { key: 'financial', label: 'Financial Summary', icon: 'fas fa-chart-line' },
        { key: 'members', label: 'Member Savings', icon: 'fas fa-users' },
        { key: 'loans', label: 'Loan Report', icon: 'fas fa-file-invoice-dollar' },
        { key: 'payments', label: 'Payment Collection', icon: 'fas fa-money-bill-wave' },
    ];

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Reports</h1>
                <button className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300">
                    <i className="fas fa-download mr-1"></i> Export PDF
                </button>
            </div>

            {/* Report Type Tabs */}
            <div className="flex gap-2 overflow-x-auto">
                {reportTypes.map(rt => (
                    <button key={rt.key} onClick={() => setReportType(rt.key)} className={`flex items-center gap-2 whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium ${reportType === rt.key ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'}`}>
                        <i className={rt.icon}></i> {rt.label}
                    </button>
                ))}
            </div>

            {/* Date Range */}
            <div className="flex gap-2">
                {['today', 'week', 'month', 'year'].map(r => (
                    <button key={r} onClick={() => setDateRange(r)} className={`rounded-lg px-3 py-1.5 text-xs font-medium ${dateRange === r ? 'bg-slate-900 text-white dark:bg-slate-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400'}`}>
                        {r === 'today' ? 'Today' : r === 'week' ? 'This Week' : r === 'month' ? 'This Month' : 'This Year'}
                    </button>
                ))}
            </div>

            {/* Report Content */}
            {isLoading ? (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {[...Array(4)].map((_, i) => <div key={i} className="h-32 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800" />)}
                </div>
            ) : (
                <div className="space-y-6">
                    {reportType === 'financial' && (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <StatCard label="Total Savings" value={`₱${Number(overview.total_savings || 0).toLocaleString()}`} icon="fas fa-piggy-bank" color="emerald" />
                            <StatCard label="Share Capital" value={`₱${Number(overview.total_share_capital || 0).toLocaleString()}`} icon="fas fa-landmark" color="blue" />
                            <StatCard label="Pending Loans" value={overview.pending_loans || 0} icon="fas fa-clock" color="amber" />
                            <StatCard label="Active Loans" value={overview.active_loans || 0} icon="fas fa-check-circle" color="emerald" />
                        </div>
                    )}

                    {reportType === 'members' && (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <StatCard label="Total Members" value={overview.total_members || 0} icon="fas fa-users" color="emerald" />
                            <StatCard label="Total Officers" value={overview.total_officers || 0} icon="fas fa-user-tie" color="blue" />
                            <StatCard label="Pending Transactions" value={overview.pending_transactions || 0} icon="fas fa-hourglass-half" color="amber" />
                        </div>
                    )}

                    {reportType === 'loans' && (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <StatCard label="Pending" value={overview.pending_loans || 0} icon="fas fa-clock" color="amber" />
                            <StatCard label="Active" value={overview.active_loans || 0} icon="fas fa-check-circle" color="emerald" />
                            <StatCard label="Share Capital" value={`₱${Number(overview.total_share_capital || 0).toLocaleString()}`} icon="fas fa-landmark" color="blue" />
                        </div>
                    )}

                    {reportType === 'payments' && (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <StatCard label="Total Savings" value={`₱${Number(overview.total_savings || 0).toLocaleString()}`} icon="fas fa-piggy-bank" color="emerald" />
                            <StatCard label="Pending Transactions" value={overview.pending_transactions || 0} icon="fas fa-hourglass-half" color="amber" />
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function StatCard({ label, value, icon, color }: { label: string; value: any; icon: string; color: string }) {
    const colors: Record<string, string> = {
        emerald: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
        blue: 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
        amber: 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
    };
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
            <div className="flex items-center gap-3">
                <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${colors[color]}`}><i className={icon}></i></div>
                <div>
                    <p className="text-xs text-slate-500">{label}</p>
                    <p className="text-lg font-bold text-slate-900 dark:text-white">{value}</p>
                </div>
            </div>
        </div>
    );
}
