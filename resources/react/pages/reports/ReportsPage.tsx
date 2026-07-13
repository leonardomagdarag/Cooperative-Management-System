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
        <>
            <div className="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
                <div>
                    <h1 className="text-xl font-semibold text-gray-900 sm:text-2xl">Reports</h1>
                    <p className="text-sm text-gray-500">Generate and export cooperative reports</p>
                </div>
                <div className="mt-3 sm:mt-0 flex gap-2">
                    <button className="bg-blue-700 hover:bg-blue-800 rounded-lg focus:ring-4 focus:ring-blue-300 text-white px-4 py-2 text-sm font-medium">
                        <i className="fas fa-download mr-1"></i> Export
                    </button>
                    <button className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i className="fas fa-file-pdf mr-1"></i> PDF
                    </button>
                </div>
            </div>

            <div className="p-4">
                {/* Report Type Tabs */}
                <div className="flex gap-0 border-b border-gray-200 mb-4">
                    {reportTypes.map(rt => (
                        <button key={rt.key} onClick={() => setReportType(rt.key)} className={`flex items-center gap-2 whitespace-nowrap px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors ${reportType === rt.key ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700 border-transparent'}`}>
                            <i className={rt.icon}></i> {rt.label}
                        </button>
                    ))}
                </div>

                {/* Date Range */}
                <div className="flex gap-2 mb-4">
                    {['today', 'week', 'month', 'year'].map(r => (
                        <button key={r} onClick={() => setDateRange(r)} className={`rounded-lg px-3 py-1.5 text-xs font-medium ${dateRange === r ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}>
                            {r === 'today' ? 'Today' : r === 'week' ? 'This Week' : r === 'month' ? 'This Month' : 'This Year'}
                        </button>
                    ))}
                </div>

                {/* Report Content */}
                {isLoading ? (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        {[...Array(4)].map((_, i) => <div key={i} className="h-32 animate-pulse rounded-lg bg-gray-100" />)}
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
        </>
    );
}

function StatCard({ label, value, icon, color }: { label: string; value: any; icon: string; color: string }) {
    const colors: Record<string, string> = {
        emerald: 'bg-emerald-100 text-emerald-600',
        blue: 'bg-blue-100 text-blue-600',
        amber: 'bg-amber-100 text-amber-600',
    };
    return (
        <div className="rounded-lg border border-gray-200 bg-white p-4">
            <div className="flex items-center gap-3">
                <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${colors[color]}`}><i className={icon}></i></div>
                <div>
                    <p className="text-xs text-gray-500">{label}</p>
                    <p className="text-lg font-bold text-gray-900">{value}</p>
                </div>
            </div>
        </div>
    );
}
