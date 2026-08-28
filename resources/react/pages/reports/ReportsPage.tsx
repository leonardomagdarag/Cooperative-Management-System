import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';

export default function ReportsPage() {
    const { user } = useAuth();
    const { addToast } = useToast();
    const perms = user?.permissions || [];
    const canViewFinancial = perms.includes('reports.financial');
    const [reportType, setReportType] = useState('financial');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const { data: financialData, isLoading } = useQuery({
        queryKey: ['reports-financial', dateFrom, dateTo],
        queryFn: () => api.get('/api/reports/financial', { params: { from: dateFrom || undefined, to: dateTo || undefined } }),
    });

    const { data: dashboardData } = useQuery({
        queryKey: ['reports-dashboard'],
        queryFn: () => api.get('/api/dashboard'),
    });

    const { data: expensesData } = useQuery({
        queryKey: ['reports-expenses', dateFrom, dateTo],
        queryFn: () => api.get('/api/reports/expenses', { params: { from: dateFrom || undefined, to: dateTo || undefined } }),
        enabled: reportType === 'expenses',
    });

    const { data: balanceSheetData } = useQuery({
        queryKey: ['reports-balance-sheet', dateFrom, dateTo],
        queryFn: () => api.get('/api/reports/balance-sheet', { params: { from: dateFrom || undefined, to: dateTo || undefined } }),
        enabled: reportType === 'balance_sheet',
    });

    const { data: membersData } = useQuery({
        queryKey: ['reports-members'],
        queryFn: () => api.get('/api/members', { params: { per_page: 10 } }),
        enabled: reportType === 'members',
    });

    const overview = dashboardData?.data || {};
    const financial = financialData?.data || {};
    const balanceSheet = balanceSheetData?.data || {};

    const reportTypes = [
        { key: 'financial', label: 'Financial Summary', icon: 'fas fa-chart-line' },
        { key: 'members', label: 'Member Savings', icon: 'fas fa-users' },
        { key: 'loans', label: 'Loan Report', icon: 'fas fa-file-invoice-dollar' },
        { key: 'payments', label: 'Payment Collection', icon: 'fas fa-money-bill-wave' },
        { key: 'expenses', label: 'Expense Report', icon: 'fas fa-receipt' },
        ...(canViewFinancial ? [{ key: 'balance_sheet', label: 'Balance Sheet', icon: 'fas fa-balance-scale' }] : []),
    ];

    const monthlySeries = financial.monthly_series || [];
    const loanStatusSeries = overview.loan_status_series || [];
    const pendingTransactions = overview.pending_transactions_list || [];
    const members = membersData?.data?.data || [];
    const expenseRows = expensesData?.data?.data ?? [];

    const formatCurrency = (v: any) => `₱${Number(v || 0).toLocaleString()}`;

    const downloadBalanceSheet = async () => {
        try {
            const res = await api.get('/api/reports/balance-sheet/download', {
                params: { from: dateFrom || undefined, to: dateTo || undefined },
                responseType: 'blob',
            });
            const url = URL.createObjectURL(res.data);
            const a = document.createElement('a');
            a.href = url;
            a.download = `balance-sheet-${(dateTo || new Date().toISOString().slice(0, 10))}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch (err: any) {
            addToast('PDF download failed', 'error');
        }
    };

    return (
        <>
            <div className="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200 lg:mt-1.5">
                <div>
                    <h1 className="text-xl font-semibold text-gray-900 sm:text-2xl">Reports</h1>
                    <p className="text-sm text-gray-500">Generate and export cooperative reports</p>
                </div>
                {canViewFinancial && (
                    <div className="mt-3 sm:mt-0 flex gap-2">
                        <button onClick={downloadBalanceSheet} className="bg-blue-700 hover:bg-blue-800 rounded-lg focus:ring-4 focus:ring-blue-300 text-white px-4 py-2 text-sm font-medium">
                            <i className="fas fa-file-pdf mr-1"></i> Balance Sheet PDF
                        </button>
                    </div>
                )}
            </div>

            <div className="p-4">
                {/* Report Type Tabs */}
                <div className="flex flex-wrap gap-0 border-b border-gray-200 mb-4">
                    {reportTypes.map(rt => (
                        <button key={rt.key} onClick={() => setReportType(rt.key)} className={`flex items-center gap-2 whitespace-nowrap px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors ${reportType === rt.key ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700 border-transparent'}`}>
                            <i className={rt.icon}></i> {rt.label}
                        </button>
                    ))}
                </div>

                {/* Date Range */}
                <div className="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-500">From</label>
                            <input type="date" value={dateFrom} onChange={e => setDateFrom(e.target.value)} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm" />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-gray-500">To</label>
                            <input type="date" value={dateTo} onChange={e => setDateTo(e.target.value)} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm" />
                        </div>
                        <button onClick={() => { setDateFrom(''); setDateTo(''); }} className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-500 hover:bg-gray-100">Clear</button>
                    </div>
                </div>

                {isLoading ? (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        {[...Array(4)].map((_, i) => <div key={i} className="h-32 animate-pulse rounded-lg bg-gray-100" />)}
                    </div>
                ) : (
                    <div className="space-y-6">
                        {/* Financial Summary */}
                        {reportType === 'financial' && (
                            <>
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    <StatCard label="Total Deposits" value={formatCurrency(financial.total_deposits)} icon="fas fa-arrow-down" color="emerald" />
                                    <StatCard label="Total Withdrawals" value={formatCurrency(financial.total_withdrawals)} icon="fas fa-arrow-up" color="red" />
                                    <StatCard label="Total Expenses" value={formatCurrency(financial.total_expenses)} icon="fas fa-receipt" color="amber" />
                                    <StatCard label="Net Cash Flow" value={formatCurrency(financial.net)} icon="fas fa-scale-balanced" color="blue" />
                                </div>
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    <StatCard label="Loan Releases" value={formatCurrency(financial.total_loan_releases)} icon="fas fa-hand-holding-usd" color="purple" />
                                    <StatCard label="Loan Repayments" value={formatCurrency(financial.total_loan_repayments)} icon="fas fa-money-bill-wave" color="blue" />
                                    <StatCard label="Fees" value={formatCurrency(financial.total_fees)} icon="fas fa-coins" color="emerald" />
                                    <StatCard label="Savings Total" value={formatCurrency(overview.total_savings || 0)} icon="fas fa-piggy-bank" color="emerald" />
                                </div>
                                {monthlySeries.length > 0 && (
                                    <div className="rounded-lg border border-gray-200 bg-white">
                                        <div className="border-b border-gray-100 px-4 py-3">
                                            <h3 className="text-sm font-semibold text-gray-700">Monthly Breakdown</h3>
                                        </div>
                                        <div className="overflow-x-auto">
                                            <table className="min-w-full divide-y divide-gray-100">
                                                <thead className="bg-gray-50">
                                                    <tr>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Deposits</th>
                                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Withdrawals</th>
                                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Expenses</th>
                                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Net</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-50">
                                                    {monthlySeries.map((m: any, i: number) => (
                                                        <tr key={i} className="hover:bg-gray-50">
                                                            <td className="px-4 py-2 text-sm font-medium text-gray-900">{m.label}</td>
                                                            <td className="px-4 py-2 text-sm text-right text-emerald-600">{formatCurrency(m.deposits)}</td>
                                                            <td className="px-4 py-2 text-sm text-right text-red-500">{formatCurrency(m.withdrawals)}</td>
                                                            <td className="px-4 py-2 text-sm text-right text-amber-600">{formatCurrency(m.expenses)}</td>
                                                            <td className={`px-4 py-2 text-sm text-right font-medium ${Number(m.net) >= 0 ? 'text-emerald-600' : 'text-red-500'}`}>{formatCurrency(m.net)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                )}
                            </>
                        )}

                        {/* Expense Report */}
                        {reportType === 'expenses' && (
                            <div className="rounded-lg border border-gray-200 bg-white">
                                <div className="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
                                    <h3 className="text-sm font-semibold text-gray-700">Expense Report</h3>
                                    <span className="text-sm font-bold text-amber-700">Total: {formatCurrency(expensesData?.data?.total)}</span>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-100">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                                <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Recorded By</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-50">
                                            {expenseRows.length === 0 ? (
                                                <tr><td colSpan={5} className="px-4 py-8 text-center text-sm text-gray-500">No expenses in range</td></tr>
                                            ) : expenseRows.map((e: any) => (
                                                <tr key={e.id} className="hover:bg-gray-50">
                                                    <td className="px-4 py-2 text-sm text-gray-500">{e.expense_date}</td>
                                                    <td className="px-4 py-2 text-sm text-gray-700">{e.category}</td>
                                                    <td className="px-4 py-2 text-sm text-gray-700">{e.description || '—'}</td>
                                                    <td className="px-4 py-2 text-sm text-right font-medium text-gray-900">{formatCurrency(e.amount)}</td>
                                                    <td className="px-4 py-2 text-sm text-gray-500">{e.recorded_by || '—'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* Member Savings */}
                        {reportType === 'members' && (
                            <>
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    <StatCard label="Total Members" value={overview.total_members || 0} icon="fas fa-users" color="emerald" />
                                    <StatCard label="Total Officers" value={overview.total_officers || 0} icon="fas fa-user-tie" color="blue" />
                                    <StatCard label="Pending Transactions" value={overview.pending_transactions || 0} icon="fas fa-hourglass-half" color="amber" />
                                    <StatCard label="Total Savings" value={formatCurrency(overview.total_savings || 0)} icon="fas fa-piggy-bank" color="emerald" />
                                </div>
                                {members.length > 0 && (
                                    <div className="rounded-lg border border-gray-200 bg-white">
                                        <div className="border-b border-gray-100 px-4 py-3">
                                            <h3 className="text-sm font-semibold text-gray-700">Member Savings (Top 10)</h3>
                                        </div>
                                        <div className="overflow-x-auto">
                                            <table className="min-w-full divide-y divide-gray-100">
                                                <thead className="bg-gray-50">
                                                    <tr>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Savings Balance</th>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-50">
                                                    {members.map((m: any) => {
                                                        const savings = m.accounts?.find((a: any) => a.type === 'savings')?.balance ?? 0;
                                                        return (
                                                            <tr key={m.id} className="hover:bg-gray-50">
                                                                <td className="px-4 py-2 text-sm text-gray-900">{m.name || m.user_number}</td>
                                                                <td className="px-4 py-2 text-sm text-right font-medium text-gray-900">{formatCurrency(savings)}</td>
                                                                <td className="px-4 py-2"><span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${m.account_status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700'}`}>{m.account_status}</span></td>
                                                            </tr>
                                                        );
                                                    })}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                )}
                            </>
                        )}

                        {/* Loan Report */}
                        {reportType === 'loans' && (
                            <>
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    <StatCard label="Pending" value={overview.pending_loans || 0} icon="fas fa-clock" color="amber" />
                                    <StatCard label="Active" value={overview.active_loans || 0} icon="fas fa-check-circle" color="emerald" />
                                    <StatCard label="Outstanding" value={formatCurrency(overview.loans_outstanding || 0)} icon="fas fa-exclamation-triangle" color="red" />
                                    <StatCard label="Overdue" value={overview.loans_overdue || 0} icon="fas fa-exclamation-circle" color="red" />
                                </div>
                                {loanStatusSeries.length > 0 && (
                                    <div className="rounded-lg border border-gray-200 bg-white">
                                        <div className="border-b border-gray-100 px-4 py-3">
                                            <h3 className="text-sm font-semibold text-gray-700">Loan Distribution by Status</h3>
                                        </div>
                                        <div className="overflow-x-auto">
                                            <table className="min-w-full divide-y divide-gray-100">
                                                <thead className="bg-gray-50">
                                                    <tr>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Count</th>
                                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Percent</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-50">
                                                    {loanStatusSeries.map((s: any) => (
                                                        <tr key={s.status} className="hover:bg-gray-50">
                                                            <td className="px-4 py-2">
                                                                <div className="flex items-center gap-2">
                                                                    <span className={`inline-block h-2.5 w-2.5 rounded-full ${s.color}`}></span>
                                                                    <span className="text-sm text-gray-900">{s.label}</span>
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-2 text-sm text-right font-medium text-gray-900">{s.count}</td>
                                                            <td className="px-4 py-2 text-sm text-right text-gray-500">{s.percent}%</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                )}
                            </>
                        )}

                        {/* Payment Collection */}
                        {reportType === 'payments' && (
                            <>
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    <StatCard label="Total Savings" value={formatCurrency(overview.total_savings || 0)} icon="fas fa-piggy-bank" color="emerald" />
                                    <StatCard label="Pending Transactions" value={overview.pending_transactions || 0} icon="fas fa-hourglass-half" color="amber" />
                                </div>
                                {pendingTransactions.length > 0 && (
                                    <div className="rounded-lg border border-gray-200 bg-white">
                                        <div className="border-b border-gray-100 px-4 py-3">
                                            <h3 className="text-sm font-semibold text-gray-700">Pending Transactions</h3>
                                        </div>
                                        <div className="overflow-x-auto">
                                            <table className="min-w-full divide-y divide-gray-100">
                                                <thead className="bg-gray-50">
                                                    <tr>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Transaction #</th>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Member</th>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-50">
                                                    {pendingTransactions.map((t: any) => (
                                                        <tr key={t.id} className="hover:bg-gray-50">
                                                            <td className="px-4 py-2 text-sm font-medium text-gray-900">{t.transaction_number}</td>
                                                            <td className="px-4 py-2 text-sm text-gray-500">{t.member_name}</td>
                                                            <td className="px-4 py-2 text-sm text-gray-500">{t.transaction_type}</td>
                                                            <td className="px-4 py-2 text-sm text-right font-medium text-gray-900">{formatCurrency(t.amount)}</td>
                                                            <td className="px-4 py-2 text-sm text-gray-500">{t.created_at}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                )}
                            </>
                        )}

                        {/* Balance Sheet */}
                        {reportType === 'balance_sheet' && (
                            <>
                                <div className="grid gap-4 md:grid-cols-3">
                                    <StatCard label="Total Assets" value={formatCurrency(balanceSheet.totals?.assets)} icon="fas fa-coins" color="emerald" />
                                    <StatCard label="Total Liabilities" value={formatCurrency(balanceSheet.totals?.liabilities)} icon="fas fa-credit-card" color="amber" />
                                    <StatCard label="Total Equity" value={formatCurrency(balanceSheet.totals?.equity)} icon="fas fa-landmark" color="blue" />
                                </div>
                                <div className="rounded-lg border border-gray-200 bg-white">
                                    <div className="border-b border-gray-100 px-4 py-3">
                                        <h3 className="text-sm font-semibold text-gray-700">Balance Sheet Summary</h3>
                                    </div>
                                    <div className="p-4 space-y-4">
                                        <div>
                                            <h4 className="mb-2 text-xs font-semibold uppercase text-gray-500">Assets</h4>
                                            <div className="rounded-lg bg-gray-50 p-3 space-y-2">
                                                {(balanceSheet.assets || []).map((line: any) => (
                                                    <div key={line.label} className="flex justify-between"><span className="text-sm text-gray-600">{line.label}</span><span className="text-sm font-medium text-gray-900">{formatCurrency(line.amount)}</span></div>
                                                ))}
                                            </div>
                                        </div>
                                        <div>
                                            <h4 className="mb-2 text-xs font-semibold uppercase text-gray-500">Liabilities</h4>
                                            <div className="rounded-lg bg-gray-50 p-3 space-y-2">
                                                {(balanceSheet.liabilities || []).map((line: any) => (
                                                    <div key={line.label} className="flex justify-between"><span className="text-sm text-gray-600">{line.label}</span><span className="text-sm font-medium text-gray-900">{formatCurrency(line.amount)}</span></div>
                                                ))}
                                            </div>
                                        </div>
                                        <div>
                                            <h4 className="mb-2 text-xs font-semibold uppercase text-gray-500">Equity</h4>
                                            <div className="rounded-lg bg-gray-50 p-3 space-y-2">
                                                {(balanceSheet.equity || []).map((line: any) => (
                                                    <div key={line.label} className="flex justify-between"><span className="text-sm text-gray-600">{line.label}</span><span className="text-sm font-medium text-gray-900">{formatCurrency(line.amount)}</span></div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </>
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
        red: 'bg-red-100 text-red-600',
        purple: 'bg-purple-100 text-purple-600',
    };
    return (
        <div className="rounded-lg border border-gray-200 bg-white p-4">
            <div className="flex items-center gap-3">
                <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${colors[color] || colors.emerald}`}><i className={icon}></i></div>
                <div>
                    <p className="text-xs text-gray-500">{label}</p>
                    <p className="text-lg font-bold text-gray-900">{value}</p>
                </div>
            </div>
        </div>
    );
}
