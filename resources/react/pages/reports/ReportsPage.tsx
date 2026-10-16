import HelpTip from '../../components/ui/HelpTip';
import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Bar, Doughnut } from 'react-chartjs-2';
import { Chart as ChartJS, ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js';
import { api } from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';
import { useToast } from '../../contexts/ToastContext';
import InterestDisbursementsReport from './InterestDisbursementsReport';

ChartJS.register(ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const RANGE_PRESETS: Record<string, string> = {
    day: 'Daily',
    week: 'Weekly',
    month: 'Monthly',
    year: 'Yearly',
    custom: 'Custom Range',
};

function txBadgeClass(type: string): string {
    const c: Record<string, string> = { deposit: 'bg-blue-100 text-blue-800', withdrawal: 'bg-orange-100 text-orange-800', loan_payment: 'bg-green-100 text-green-800', loan_disbursement: 'bg-purple-100 text-purple-800', interest: 'bg-teal-100 text-teal-800', fee: 'bg-red-100 text-red-800' };
    return c[type] || 'bg-gray-100 text-gray-800';
}

function txBadgeText(type: string): string {
    const c: Record<string, string> = { deposit: 'Deposit', withdrawal: 'Withdrawal', loan_payment: 'Loan Payment', loan_disbursement: 'Loan Disbursement', interest: 'Interest', fee: 'Fee' };
    return c[type] || type?.replace(/_/g, ' ') || 'Unknown';
}

function activityTypeLabel(type: string): string {
    const c: Record<string, string> = { deposit: 'Deposit', withdrawal: 'Withdrawal', loan_payment: 'Payment', loan_disbursement: 'Loan', fee: 'Fee' };
    return c[type] || type?.replace(/_/g, ' ') || 'Unknown';
}

function loanStatusBadge(title: string): string {
    const c: Record<string, string> = { Completed: 'bg-green-100 text-green-800', Overdue: 'bg-red-100 text-red-800', Active: 'bg-blue-100 text-blue-800' };
    return c[title] || 'bg-gray-100 text-gray-800';
}

function formatCurrency(v: any): string {
    return `₱${Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatDate(v?: string | null): string {
    if (!v) return '—';
    const parts = v.slice(0, 10).split('-');
    if (parts.length !== 3) return v;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const month = months[Number(parts[1]) - 1];
    return month ? `${month} ${Number(parts[2])}, ${parts[0]}` : v;
}

function toISO(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function reportWindow(preset: string, startDate: string, endDate: string): { from?: string; to?: string } {
    const now = new Date();
    switch (preset) {
        case 'day':
            return { from: toISO(now), to: toISO(now) };
        case 'week': {
            const start = new Date(now);
            start.setDate(now.getDate() - now.getDay());
            const end = new Date(start);
            end.setDate(start.getDate() + 6);
            return { from: toISO(start), to: toISO(end) };
        }
        case 'month':
            return { from: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`, to: toISO(new Date(now.getFullYear(), now.getMonth() + 1, 0)) };
        case 'year':
            return { from: `${now.getFullYear()}-01-01`, to: `${now.getFullYear()}-12-31` };
        default:
            return { from: startDate || undefined, to: endDate || undefined };
    }
}

function Pagination({ meta, page, setPage }: { meta: any; page: number; setPage: (n: number) => void }) {
    if (!meta || meta.total === 0) return null;
    const from = (page - 1) * meta.per_page + 1;
    const to = Math.min(page * meta.per_page, meta.total);
    const handlePage = (n: number) => {
        setPage(n);
        const main = document.querySelector('main');
        if (main) main.scrollTo({ top: 0, behavior: 'smooth' });
        else window.scrollTo({ top: 0, behavior: 'smooth' });
    };
    return (
        <div className="p-4 bg-white border-t border-gray-200 flex items-center justify-center gap-2">
            <button onClick={() => handlePage(Math.max(1, page - 1))} disabled={page === 1} className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg disabled:opacity-50 hover:bg-gray-50">Previous</button>
            <span className="text-sm font-normal text-gray-500">Showing <span className="font-semibold text-gray-900">{from}-{to}</span> of <span className="font-semibold text-gray-900">{meta.total}</span></span>
            <button onClick={() => handlePage(Math.min(meta.last_page, page + 1))} disabled={page === meta.last_page} className="px-3 py-1.5 text-sm border border-gray-300 rounded-lg disabled:opacity-50 hover:bg-gray-50">Next</button>
        </div>
    );
}

const selectCls = 'bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2';
const thCls = 'p-4 text-xs font-medium text-left text-gray-500 uppercase';

export default function ReportsPage() {
    const { user } = useAuth();
    const { addToast } = useToast();
    const perms = user?.permissions || [];
    const canViewFinancial = perms.includes('reports.financial');
    const canViewMembers = perms.includes('members.view');
    const canViewOfficers = perms.includes('officers.view') || canViewMembers;
    const canViewInterest = perms.includes('interest.view');

    const now = new Date();
    const [reportType, setReportType] = useState('financial');
    const [rangePreset, setRangePreset] = useState('month');
    const [startDate, setStartDate] = useState(() => `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`);
    const [endDate, setEndDate] = useState(() => toISO(new Date(now.getFullYear(), now.getMonth() + 1, 0)));
    const [memberId, setMemberId] = useState('');
    const [loanFilter, setLoanFilter] = useState('all');
    const [expenseCategory, setExpenseCategory] = useState('');
    const [activityType, setActivityType] = useState('all');
    const [refreshKey, setRefreshKey] = useState(0);
    const [reportPages, setReportPages] = useState<Record<string, number>>({});

    const page = reportPages[reportType] || 1;
    const setPage = (n: number) => setReportPages(p => ({ ...p, [reportType]: n }));
    const changeReportType = (key: string) => {
        setReportType(key);
        setReportPages(p => ({ ...p, [key]: 1 }));
    };

    useEffect(() => {
        const main = document.querySelector('main');
        if (main) main.scrollTop = 0;
        else window.scrollTo({ top: 0, behavior: 'instant' });
    }, [reportType]);

    const { from, to } = reportWindow(rangePreset, startDate, endDate);
    const rangeParams = { from, to };

    const reportTypes = [
        { key: 'financial', label: 'Financial Summary', icon: 'fas fa-chart-line' },
        { key: 'member_savings', label: 'Member Savings', icon: 'fas fa-users' },
        { key: 'loans', label: 'Loan Report', icon: 'fas fa-file-invoice-dollar' },
        { key: 'payments', label: 'Payment Collection', icon: 'fas fa-money-bill-wave' },
        { key: 'expenses', label: 'Expense Report', icon: 'fas fa-receipt' },
        { key: 'member_activity', label: 'Member Activity', icon: 'fas fa-user-clock' },
        ...(canViewFinancial ? [{ key: 'balance_sheet', label: 'Balance Sheet', icon: 'fas fa-balance-scale' }] : []),
        ...(canViewMembers ? [{ key: 'members', label: 'Members Report', icon: 'fas fa-id-card' }] : []),
        ...(canViewOfficers ? [{ key: 'officers', label: 'Officers Report', icon: 'fas fa-user-tie' }] : []),
        ...(canViewInterest ? [{ key: 'interest', label: 'Interest Disbursements', icon: 'fas fa-coins' }] : []),
    ];

    const needsOptions = ['member_savings', 'member_activity', 'expenses'].includes(reportType);

    const { data: financialData } = useQuery({
        queryKey: ['reports-financial', from, to, refreshKey],
        queryFn: () => api.get('/api/reports/financial', { params: rangeParams }),
        enabled: reportType === 'financial',
    });

    const { data: optionsData } = useQuery({
        queryKey: ['reports-options', refreshKey],
        queryFn: () => api.get('/api/reports/options'),
        enabled: needsOptions,
    });

    const { data: dashboardData } = useQuery({
        queryKey: ['reports-dashboard', refreshKey],
        queryFn: () => api.get('/api/dashboard'),
        enabled: reportType === 'loans' || reportType === 'payments',
    });

    const { data: memberSavingsData } = useQuery({
        queryKey: ['reports-member-savings', from, to, memberId, page, refreshKey],
        queryFn: () => api.get('/api/reports/member-savings', { params: { from, to, member_id: memberId || undefined, page } }),
        enabled: reportType === 'member_savings',
    });

    const { data: loansData } = useQuery({
        queryKey: ['reports-loans', from, to, loanFilter, page, refreshKey],
        queryFn: () => api.get('/api/reports/loans', { params: { from, to, filter: loanFilter, page } }),
        enabled: reportType === 'loans',
    });

    const { data: paymentsData } = useQuery({
        queryKey: ['reports-payments', from, to, page, refreshKey],
        queryFn: () => api.get('/api/reports/payments', { params: { from, to, page } }),
        enabled: reportType === 'payments',
    });

    const { data: expensesData } = useQuery({
        queryKey: ['reports-expenses', from, to, expenseCategory, page, refreshKey],
        queryFn: () => api.get('/api/reports/expenses', { params: { from, to, category: expenseCategory || undefined, page } }),
        enabled: reportType === 'expenses',
    });

    const { data: memberActivityData } = useQuery({
        queryKey: ['reports-member-activity', from, to, memberId, activityType, page, refreshKey],
        queryFn: () => api.get('/api/reports/member-activity', { params: { from, to, member_id: memberId || undefined, activity_type: activityType, page } }),
        enabled: reportType === 'member_activity',
    });

    const { data: balanceSheetData } = useQuery({
        queryKey: ['reports-balance-sheet', from, to, refreshKey],
        queryFn: () => api.get('/api/reports/balance-sheet', { params: rangeParams }),
        enabled: reportType === 'balance_sheet',
    });

    const { data: membersReportData } = useQuery({
        queryKey: ['reports-members-list', from, to, page, refreshKey],
        queryFn: () => api.get('/api/reports/members', { params: { from, to, page } }),
        enabled: reportType === 'members',
    });

    const { data: officersReportData } = useQuery({
        queryKey: ['reports-officers-list', from, to, page, refreshKey],
        queryFn: () => api.get('/api/reports/officers', { params: { from, to, page } }),
        enabled: reportType === 'officers',
    });

    const financial = financialData?.data || {};
    const balanceSheet = balanceSheetData?.data || {};
    const members = optionsData?.data?.members || [];
    const expenseCategories = optionsData?.data?.expense_categories || [];

    const memberSavingsRows = memberSavingsData?.data?.data ?? [];
    const memberSavingsMeta = memberSavingsData?.data?.meta ?? null;
    const loanRows = loansData?.data?.data ?? [];
    const loanMeta = loansData?.data?.meta ?? null;
    const paymentRows = paymentsData?.data?.data ?? [];
    const paymentMeta = paymentsData?.data?.meta ?? null;
    const expenseRows = expensesData?.data?.data ?? [];
    const expenseMeta = expensesData?.data?.meta ?? null;
    const memberActivityRows = memberActivityData?.data?.data ?? [];
    const memberActivityMeta = memberActivityData?.data?.meta ?? null;
    const membersReportRows = membersReportData?.data?.data ?? [];
    const membersReportMeta = membersReportData?.data?.meta ?? null;
    const officersReportRows = officersReportData?.data?.data ?? [];
    const officersReportMeta = officersReportData?.data?.meta ?? null;

    const monthlySeries = financial.monthly_series || [];
    const loanStatusSeries = dashboardData?.data?.loan_status_series || [];
    const pendingTransactions = dashboardData?.data?.pending_transactions_list || [];

    const inflows = Number(financial.total_deposits || 0) + Number(financial.total_loan_repayments || 0) + Number(financial.total_fees || 0);
    const outflows = Number(financial.total_withdrawals || 0) + Number(financial.total_loan_releases || 0) + Number(financial.total_expenses || 0);

    const totalsByType = [
        { label: 'Deposits', value: Number(financial.total_deposits || 0), color: '#059669' },
        { label: 'Withdrawals', value: Number(financial.total_withdrawals || 0), color: '#ef4444' },
        { label: 'Loan Releases', value: Number(financial.total_loan_releases || 0), color: '#9333ea' },
        { label: 'Loan Repayments', value: Number(financial.total_loan_repayments || 0), color: '#2563eb' },
        { label: 'Fees', value: Number(financial.total_fees || 0), color: '#f59e0b' },
        { label: 'Expenses', value: Number(financial.total_expenses || 0), color: '#f97316' },
    ];

    const inflowOutflowData = {
        labels: ['Inflows', 'Outflows'],
        datasets: [{ label: 'Amount', data: [inflows, outflows], backgroundColor: ['#059669', '#dc2626'] }],
    };
    const inflowOutflowOptions = {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { ticks: { callback: (v: any) => '₱' + Number(v).toLocaleString() } } },
    };

    const totalsByTypeData = {
        labels: totalsByType.map(t => t.label),
        datasets: [{ data: totalsByType.map(t => t.value), backgroundColor: totalsByType.map(t => t.color), borderWidth: 1 }],
    };
    const totalsByTypeOptions = { maintainAspectRatio: false, plugins: { legend: { position: 'right' } } };

    const downloadReport = async (format: 'pdf' | 'excel') => {
        const exportType = reportType === 'interest' ? 'interest_disbursements' : reportType;
        const params: any = { from, to, report_type: exportType, format };
        if (reportType === 'member_savings' || reportType === 'member_activity') params.member_id = memberId || undefined;
        if (reportType === 'loans') params.filter = loanFilter === 'all' ? undefined : loanFilter;
        if (reportType === 'expenses') params.category = expenseCategory || undefined;
        if (reportType === 'member_activity') params.activity_type = activityType === 'all' ? undefined : activityType;

        try {
            const res = await api.post('/api/reports/export', params, { responseType: 'blob' });
            const disposition = res.headers['content-disposition'] || '';
            const match = disposition.match(/filename="?([^";]+)"?/i);
            const filename = match ? match[1] : `report-${Date.now()}.${format === 'pdf' ? 'pdf' : 'xls'}`;
            const url = URL.createObjectURL(new Blob([res.data]));
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
            addToast('Report downloaded', 'success');
        } catch (err: any) {
            if (err.response?.data instanceof Blob) {
                err.response.data.text().then((text: string) => {
                    try {
                        const parsed = JSON.parse(text);
                        addToast(parsed.message || 'Export failed', 'error');
                    } catch {
                        addToast('Export failed', 'error');
                    }
                });
            } else {
                addToast(err.response?.data?.message || 'Export failed', 'error');
            }
        }
    };

    const financialCards = [
        { label: 'Total Deposits', value: financial.total_deposits, key: 'deposits' },
        { label: 'Total Withdrawals', value: financial.total_withdrawals, key: 'withdrawals' },
        { label: 'Total Loan Releases', value: financial.total_loan_releases, key: 'releases' },
        { label: 'Total Loan Repayments', value: financial.total_loan_repayments, key: 'repayments' },
        { label: 'Total Interest Earned (Collected)', value: financial.total_interest_earned, key: 'interest' },
        { label: 'Total Fees', value: financial.total_fees, key: 'fees' },
        { label: 'Total Expenses', value: financial.total_expenses, key: 'expenses' },
        { label: 'Net Balance', value: financial.net, key: 'net' },
    ];

    return (
        <div className="p-4 bg-white border-b border-gray-200 lg:mt-1.5">
            <div className="mb-4">
                <h1 className="flex items-center gap-2 text-xl font-semibold text-gray-900 sm:text-2xl">Reports <HelpTip text="Select a report and date range to view matching records. Some reports offer additional member, loan, or category filters; exports use the selected criteria." /></h1>
                <p className="text-sm text-gray-500">View financial summaries and activity reports</p>
            </div>

            <div className="mb-4 border-b border-gray-200">
                <ul className="flex flex-wrap -mb-px text-sm font-medium text-center" role="tablist">
                    {reportTypes.map(rt => (
                        <li key={rt.key} className="mr-2" role="presentation">
                            <button
                                onClick={() => changeReportType(rt.key)}
                                className={`inline-flex items-center gap-2 p-4 border-b-2 rounded-t-lg transition-colors ${reportType === rt.key ? 'text-blue-600 border-blue-600' : 'border-transparent hover:text-gray-600 hover:border-gray-300'}`}
                                type="button"
                                role="tab"
                            >
                                <i className={rt.icon}></i> {rt.label}
                            </button>
                        </li>
                    ))}
                </ul>
            </div>

            <div className="sm:flex sm:items-center sm:justify-between gap-3">
                <div className="flex items-center gap-2 flex-wrap">
                    <label htmlFor="report-range" className="text-xs font-medium text-gray-500">Range</label>
                    <select id="report-range" value={rangePreset} onChange={e => { setRangePreset(e.target.value); setPage(1); }} className={selectCls}>
                        {Object.entries(RANGE_PRESETS).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                    </select>

                    {rangePreset === 'custom' && (
                        <div className="flex items-center gap-2">
                            <label className="text-xs font-medium text-gray-500">Start</label>
                            <input type="date" value={startDate} onChange={e => { setStartDate(e.target.value); setPage(1); }} className={selectCls} />
                            <label className="text-xs font-medium text-gray-500">End</label>
                            <input type="date" value={endDate} onChange={e => { setEndDate(e.target.value); setPage(1); }} className={selectCls} />
                        </div>
                    )}

                    {(reportType === 'member_savings' || reportType === 'member_activity') && (
                        <div className="flex items-center gap-2">
                            <label htmlFor="report-member" className="text-xs font-medium text-gray-500">Member</label>
                            <select id="report-member" value={memberId} onChange={e => { setMemberId(e.target.value); setPage(1); }} className={selectCls}>
                                <option value="">All Members</option>
                                {members.map((m: any) => <option key={m.id} value={m.id}>{m.name}</option>)}
                            </select>
                        </div>
                    )}

                    {reportType === 'loans' && (
                        <div className="flex items-center gap-2">
                            <label htmlFor="loan-filter" className="text-xs font-medium text-gray-500">Loan Filter</label>
                            <select id="loan-filter" value={loanFilter} onChange={e => { setLoanFilter(e.target.value); setPage(1); }} className={selectCls}>
                                <option value="all">All</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="overdue">Overdue</option>
                            </select>
                        </div>
                    )}

                    {reportType === 'expenses' && (
                        <div className="flex items-center gap-2">
                            <label htmlFor="expense-category" className="text-xs font-medium text-gray-500">Category</label>
                            <select id="expense-category" value={expenseCategory} onChange={e => { setExpenseCategory(e.target.value); setPage(1); }} className={selectCls}>
                                <option value="">All</option>
                                {expenseCategories.map((cat: string) => <option key={cat} value={cat}>{cat}</option>)}
                            </select>
                        </div>
                    )}

                    {reportType === 'member_activity' && (
                        <div className="flex items-center gap-2">
                            <label htmlFor="activity-type" className="text-xs font-medium text-gray-500">Type</label>
                            <select id="activity-type" value={activityType} onChange={e => { setActivityType(e.target.value); setPage(1); }} className={selectCls}>
                                <option value="all">All</option>
                                <option value="deposit">Deposit</option>
                                <option value="withdrawal">Withdrawal</option>
                                <option value="loan_disbursement">Loan</option>
                                <option value="loan_payment">Payment</option>
                                <option value="interest">Interest</option>
                                <option value="fee">Fee</option>
                            </select>
                        </div>
                    )}
                </div>

                <div className="flex items-center gap-2 ml-auto">
                    {(reportType !== 'balance_sheet' || canViewFinancial) ? (
                        <>
                            <button onClick={() => downloadReport('pdf')} className="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-white bg-red-700 border border-red-700 rounded-lg hover:bg-red-800 focus:ring-4 focus:ring-red-300">
                                <i className="fas fa-file-pdf mr-1"></i> PDF
                            </button>
                            <button onClick={() => downloadReport('excel')} className="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-white bg-green-700 border border-green-700 rounded-lg hover:bg-green-800 focus:ring-4 focus:ring-green-300">
                                <i className="fas fa-file-excel mr-1"></i> Excel
                            </button>
                        </>
                    ) : null}
                    <button onClick={() => setRefreshKey(k => k + 1)} title="Refresh data" className="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-center text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:ring-blue-300">
                        <i className="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>

            {reportType === 'financial' && (
                <div className="p-4 bg-white border-b border-gray-200">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-sm font-semibold text-gray-900">Financial Summary</h2>
                            <p className="text-xs text-gray-500">{formatDate(financial.start)} – {formatDate(financial.end)}</p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-3 mt-3 sm:grid-cols-2 lg:grid-cols-4">
                        {financialCards.map(card => (
                            <div key={card.key} className="p-3 border border-gray-200 rounded-lg">
                                <div className="text-xs font-medium text-gray-500">{card.label}</div>
                                <div className="mt-1 text-lg font-semibold text-gray-900">{formatCurrency(card.value)}</div>
                            </div>
                        ))}
                    </div>

                    <div className="grid grid-cols-1 gap-4 mt-4 lg:grid-cols-2">
                        <div className="p-4 border border-gray-200 rounded-lg">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-semibold text-gray-900">Inflows vs Outflows</h3>
                                <span className="text-xs text-gray-500">Selected range</span>
                            </div>
                            <div className="relative mt-3 h-64">
                                <Bar data={inflowOutflowData} options={inflowOutflowOptions} />
                            </div>
                        </div>

                        <div className="p-4 border border-gray-200 rounded-lg">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-semibold text-gray-900">Totals by Type</h3>
                                <span className="text-xs text-gray-500">Selected range</span>
                            </div>
                            <div className="relative mt-3 h-64">
                                <Doughnut data={totalsByTypeData} options={totalsByTypeOptions} />
                            </div>
                        </div>
                    </div>

                    {monthlySeries.length > 0 && (
                        <div className="rounded-lg border border-gray-200 bg-white mt-4">
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
                </div>
            )}

            {reportType === 'balance_sheet' && (
                <div className="p-4 bg-white border-b border-gray-200">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-sm font-semibold text-gray-900">Balance Sheet</h2>
                            <p className="text-xs text-gray-500">As of {formatDate(balanceSheet.as_of)}</p>
                        </div>
                        <div className="text-right">
                            <div className="text-xs text-gray-500">Period Basis</div>
                            <div className="text-sm text-gray-900">{formatDate(balanceSheet.start)} - {formatDate(balanceSheet.end)}</div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 mt-4 lg:grid-cols-2">
                        <div className="p-4 border border-gray-200 rounded-lg">
                            <h3 className="text-sm font-semibold text-gray-900">Assets</h3>
                            <div className="mt-3 space-y-2">
                                {(balanceSheet.assets || []).map((line: any) => (
                                    <div key={line.label} className="flex items-center justify-between text-sm">
                                        <span className="text-gray-700">{line.label}</span>
                                        <span className="font-medium text-gray-900">{formatCurrency(line.amount)}</span>
                                    </div>
                                ))}
                            </div>
                            <div className="pt-2 mt-3 border-t border-gray-200 flex items-center justify-between">
                                <span className="text-sm font-semibold text-gray-900">Total Assets</span>
                                <span className="text-sm font-semibold text-gray-900">{formatCurrency(balanceSheet.totals?.assets)}</span>
                            </div>
                        </div>

                        <div className="p-4 border border-gray-200 rounded-lg">
                            <h3 className="text-sm font-semibold text-gray-900">Liabilities and Equity</h3>

                            <div className="mt-3">
                                <div className="text-xs font-semibold tracking-wide text-gray-500 uppercase">Liabilities</div>
                                <div className="mt-2 space-y-2">
                                    {(balanceSheet.liabilities || []).map((line: any) => (
                                        <div key={line.label} className="flex items-center justify-between text-sm">
                                            <span className="text-gray-700">{line.label}</span>
                                            <span className="font-medium text-gray-900">{formatCurrency(line.amount)}</span>
                                        </div>
                                    ))}
                                </div>
                                <div className="pt-2 mt-3 border-t border-gray-200 flex items-center justify-between">
                                    <span className="text-sm font-semibold text-gray-900">Total Liabilities</span>
                                    <span className="text-sm font-semibold text-gray-900">{formatCurrency(balanceSheet.totals?.liabilities)}</span>
                                </div>
                            </div>

                            <div className="mt-4">
                                <div className="text-xs font-semibold tracking-wide text-gray-500 uppercase">Equity</div>
                                <div className="mt-2 space-y-2">
                                    {(balanceSheet.equity || []).map((line: any) => (
                                        <div key={line.label} className="flex items-center justify-between text-sm">
                                            <span className="text-gray-700">{line.label}</span>
                                            <span className="font-medium text-gray-900">{formatCurrency(line.amount)}</span>
                                        </div>
                                    ))}
                                </div>
                                <div className="pt-2 mt-3 border-t border-gray-200 flex items-center justify-between">
                                    <span className="text-sm font-semibold text-gray-900">Total Equity</span>
                                    <span className="text-sm font-semibold text-gray-900">{formatCurrency(balanceSheet.totals?.equity)}</span>
                                </div>
                            </div>

                            <div className="pt-2 mt-4 border-t-2 border-gray-300 flex items-center justify-between">
                                <span className="text-sm font-semibold text-gray-900">Total Liabilities and Equity</span>
                                <span className="text-sm font-semibold text-gray-900">{formatCurrency(balanceSheet.totals?.liabilities_and_equity)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {reportType === 'member_savings' && (
                <div className="flex flex-col">
                    <div className="overflow-x-auto">
                        <div className="inline-block min-w-full align-middle">
                            <div className="overflow-hidden shadow">
                                <table className="min-w-[700px] w-full divide-y divide-gray-200 table-fixed">
                                    <thead className="bg-gray-100">
                                        <tr>
                                            <th scope="col" className={thCls}>Member</th>
                                            <th scope="col" className={thCls}>Total Deposits</th>
                                            <th scope="col" className={thCls}>Total Withdrawals</th>
                                            <th scope="col" className={thCls}>Current Savings Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {memberSavingsRows.length === 0 ? (
                                            <tr>
                                                <td colSpan={4} className="p-6 text-sm text-gray-500">No savings records found for the selected filters.</td>
                                            </tr>
                                        ) : memberSavingsRows.map((row: any, i: number) => (
                                            <tr key={i} className="hover:bg-gray-50">
                                                <td className="p-4 text-sm font-medium text-gray-900 whitespace-nowrap truncate" title={row.member_name}>{row.member_name}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatCurrency(row.total_deposits)}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatCurrency(row.total_withdrawals)}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatCurrency(row.current_balance)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <Pagination meta={memberSavingsMeta} page={page} setPage={setPage} />
                </div>
            )}

            {reportType === 'loans' && (
                <div className="flex flex-col">
                    <div className="overflow-x-auto">
                        <div className="inline-block min-w-full align-middle">
                            <div className="overflow-hidden shadow">
                                <table className="min-w-[850px] w-full divide-y divide-gray-200 table-fixed">
                                    <thead className="bg-gray-100">
                                        <tr>
                                            <th className={thCls}>Member</th>
                                            <th className={thCls}>Loan</th>
                                            <th className={thCls}>Interest</th>
                                            <th className={thCls}>Loan Date</th>
                                            <th className={thCls}>Due Date</th>
                                            <th className={thCls}>Paid</th>
                                            <th className={thCls}>Remaining</th>
                                            <th className={thCls}>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {loanRows.length === 0 ? (
                                            <tr>
                                                <td colSpan={8} className="p-6 text-sm text-gray-500">No loans found for the selected filters.</td>
                                            </tr>
                                        ) : loanRows.map((row: any) => (
                                            <tr key={row.loan_number || row.amount_paid} className="hover:bg-gray-50">
                                                <td className="p-4 text-sm font-medium text-gray-900 whitespace-nowrap truncate" title={row.member_name}>{row.member_name}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatCurrency(row.loan_amount)}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{Number(row.interest_rate).toFixed(2)}%</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatDate(row.loan_date)}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatDate(row.due_date)}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatCurrency(row.amount_paid)}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatCurrency(row.remaining_balance)}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">
                                                    <span className={`${loanStatusBadge(row.status)} text-xs font-medium mr-2 px-2.5 py-0.5 rounded`}>{row.status}</span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <Pagination meta={loanMeta} page={page} setPage={setPage} />

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
                </div>
            )}

            {reportType === 'payments' && (
                <div className="flex flex-col">
                    <div className="overflow-x-auto">
                        <div className="inline-block min-w-full align-middle">
                            <div className="overflow-hidden shadow">
                                <table className="min-w-[650px] w-full divide-y divide-gray-200 table-fixed">
                                    <thead className="bg-gray-100">
                                        <tr>
                                            <th className={thCls}>Member</th>
                                            <th className={thCls}>Loan ID</th>
                                            <th className={thCls}>Payment</th>
                                            <th className={thCls}>Payment Date</th>
                                            <th className={thCls}>Remaining Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {paymentRows.length === 0 ? (
                                            <tr>
                                                <td colSpan={5} className="p-6 text-sm text-gray-500">No loan payments found for the selected range.</td>
                                            </tr>
                                        ) : paymentRows.map((row: any) => (
                                            <tr key={row.payment_date + row.loan_number + row.amount} className="hover:bg-gray-50">
                                                <td className="p-4 text-sm font-medium text-gray-900 whitespace-nowrap truncate" title={row.member_name}>{row.member_name}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{row.loan_number}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatCurrency(row.amount)}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatDate(row.payment_date)}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatCurrency(row.remaining_balance)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <Pagination meta={paymentMeta} page={page} setPage={setPage} />

                    {pendingTransactions.length > 0 && (
                        <div className="rounded-lg border border-gray-200 bg-white">
                            <div className="border-b border-gray-100 px-4 py-3">
                                <h3 className="text-sm font-semibold text-gray-700">Pending Transactions</h3>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-[650px] w-full divide-y divide-gray-100">
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
                                                <td className="px-4 py-2 text-sm font-medium text-gray-900 truncate max-w-[180px]" title={t.transaction_number}>{t.transaction_number}</td>
                                                <td className="px-4 py-2 text-sm text-gray-500 truncate max-w-[200px]" title={t.member_name}>{t.member_name}</td>
                                                <td className="px-4 py-2 text-sm text-gray-500"><span className={`inline-flex rounded px-2 py-0.5 text-xs font-medium ${txBadgeClass(t.transaction_type)}`}>{txBadgeText(t.transaction_type)}</span></td>
                                                <td className="px-4 py-2 text-sm text-right font-medium text-gray-900">{formatCurrency(t.amount)}</td>
                                                <td className="px-4 py-2 text-sm text-gray-500">{formatDate(t.created_at)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            )}

            {reportType === 'expenses' && (
                <div className="flex flex-col">
                    <div className="overflow-x-auto">
                        <div className="inline-block min-w-full align-middle">
                            <div className="overflow-hidden shadow">
                                <table className="min-w-full divide-y divide-gray-200 table-fixed">
                                    <thead className="bg-gray-100">
                                        <tr>
                                            <th className={thCls}>Category</th>
                                            <th className={thCls}>Description</th>
                                            <th className={thCls}>Amount</th>
                                            <th className={thCls}>Date</th>
                                            <th className={thCls}>Recorded By</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {expenseRows.length === 0 ? (
                                            <tr>
                                                <td colSpan={5} className="p-6 text-sm text-gray-500">No expenses found for the selected filters.</td>
                                            </tr>
                                        ) : expenseRows.map((e: any) => (
                                            <tr key={e.id} className="hover:bg-gray-50">
                                                <td className="p-4 text-sm font-medium text-gray-900 whitespace-nowrap truncate" title={e.category}>{e.category}</td>
                                                <td className="p-4 text-sm text-gray-900">{e.description || '—'}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatCurrency(e.amount)}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatDate(e.expense_date)}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap truncate" title={e.recorded_by || '—'}>{e.recorded_by || '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <Pagination meta={expenseMeta} page={page} setPage={setPage} />
                </div>
            )}

            {reportType === 'member_activity' && (
                <div className="flex flex-col">
                    <div className="overflow-x-auto">
                        <div className="inline-block min-w-full align-middle">
                            <div className="overflow-hidden shadow">
                                <table className="min-w-full divide-y divide-gray-200 table-fixed">
                                    <thead className="bg-gray-100">
                                        <tr>
                                            <th className={thCls}>Member</th>
                                            <th className={thCls}>Type</th>
                                            <th className={thCls}>Amount</th>
                                            <th className={thCls}>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {memberActivityRows.length === 0 ? (
                                            <tr>
                                                <td colSpan={4} className="p-6 text-sm text-gray-500">No member activity found for the selected filters.</td>
                                            </tr>
                                        ) : memberActivityRows.map((row: any) => (
                                            <tr key={row.id} className="hover:bg-gray-50">
                                                <td className="p-4 text-sm font-medium text-gray-900 whitespace-nowrap truncate" title={row.member_name}>{row.member_name}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">
                                                    <span className={`${txBadgeClass(row.transaction_type)} text-xs font-medium mr-2 px-2.5 py-0.5 rounded`}>{activityTypeLabel(row.transaction_type)}</span>
                                                </td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatCurrency(row.amount)}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatDate(row.transaction_date)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <Pagination meta={memberActivityMeta} page={page} setPage={setPage} />
                </div>
            )}

            {reportType === 'members' && (
                <div className="flex flex-col">
                    <div className="overflow-x-auto">
                        <div className="inline-block min-w-full align-middle">
                            <div className="overflow-hidden shadow">
                                <table className="min-w-full divide-y divide-gray-200 table-fixed">
                                    <thead className="bg-gray-100">
                                        <tr>
                                            <th className={thCls}>Member ID</th>
                                            <th className={thCls}>Name</th>
                                            <th className={thCls}>Email</th>
                                            <th className={thCls}>Phone</th>
                                            <th className={thCls}>Status</th>
                                            <th className={thCls}>Branch</th>
                                            <th className={thCls}>Join Date</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {membersReportRows.length === 0 ? (
                                            <tr>
                                                <td colSpan={7} className="p-6 text-sm text-gray-500">No members found for the selected range.</td>
                                            </tr>
                                        ) : membersReportRows.map((row: any, i: number) => (
                                            <tr key={row.member_id + i} className="hover:bg-gray-50">
                                                <td className="p-4 text-sm font-medium text-gray-900 whitespace-nowrap">{row.member_id}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap truncate" title={row.name}>{row.name}</td>
                                                <td className="p-4 text-sm text-gray-500 whitespace-nowrap truncate" title={row.email}>{row.email}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{row.phone || '—'}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">
                                                    <span className={`text-xs font-medium px-2.5 py-0.5 rounded ${row.status === 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'}`}>{row.status}</span>
                                                </td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap truncate" title={row.branch || '—'}>{row.branch || '—'}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatDate(row.joined_at)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <Pagination meta={membersReportMeta} page={page} setPage={setPage} />
                </div>
            )}

            {reportType === 'officers' && (
                <div className="flex flex-col">
                    <div className="overflow-x-auto">
                        <div className="inline-block min-w-full align-middle">
                            <div className="overflow-hidden shadow">
                                <table className="min-w-full divide-y divide-gray-200 table-fixed">
                                    <thead className="bg-gray-100">
                                        <tr>
                                            <th className={thCls}>Officer ID</th>
                                            <th className={thCls}>Name</th>
                                            <th className={thCls}>Email</th>
                                            <th className={thCls}>Role</th>
                                            <th className={thCls}>Title</th>
                                            <th className={thCls}>Branch</th>
                                            <th className={thCls}>Status</th>
                                            <th className={thCls}>Join Date</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {officersReportRows.length === 0 ? (
                                            <tr>
                                                <td colSpan={8} className="p-6 text-sm text-gray-500">No officers found for the selected range.</td>
                                            </tr>
                                        ) : officersReportRows.map((row: any, i: number) => (
                                            <tr key={row.officer_id + i} className="hover:bg-gray-50">
                                                <td className="p-4 text-sm font-medium text-gray-900 whitespace-nowrap">{row.officer_id}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap truncate" title={row.name}>{row.name}</td>
                                                <td className="p-4 text-sm text-gray-500 whitespace-nowrap truncate" title={row.email}>{row.email}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">
                                                    <span className="px-2.5 py-0.5 text-xs font-medium bg-purple-100 text-purple-800 rounded capitalize">{row.role}</span>
                                                </td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap truncate" title={row.title || '—'}>{row.title || '—'}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap truncate" title={row.branch || '—'}>{row.branch || '—'}</td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">
                                                    <span className={`text-xs font-medium px-2.5 py-0.5 rounded ${row.status === 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'}`}>{row.status}</span>
                                                </td>
                                                <td className="p-4 text-sm text-gray-900 whitespace-nowrap">{formatDate(row.joined_at)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <Pagination meta={officersReportMeta} page={page} setPage={setPage} />
                </div>
            )}

            {reportType === 'interest' && <InterestDisbursementsReport />}
        </div>
    );
}