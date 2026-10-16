import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';

const number = (value: unknown) => (Number(value) || 0).toLocaleString();
const peso = (value: unknown) => `₱${(Number(value) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function ChairmanOverview() {
    const { user } = useAuth();
    const { data, isLoading, isError } = useQuery({ queryKey: ['overview-chairman'], queryFn: () => api.get('/api/dashboard') });
    const d = data?.data || {};
    const cards = [
        { label: 'Members', value: number(d.total_members), detail: `${number(d.members_new_month)} joined this month`, style: 'text-emerald-800 bg-emerald-50 border-emerald-100' },
        { label: 'Savings deposits', value: peso(d.total_savings), detail: `${peso(d.month_deposits)} deposited this month`, style: 'text-cyan-800 bg-cyan-50 border-cyan-100' },
        { label: 'Share capital', value: peso(d.total_share_capital), detail: `${number(d.total_officers)} officers`, style: 'text-indigo-800 bg-indigo-50 border-indigo-100' },
        { label: 'Outstanding loans', value: peso(d.loans_outstanding), detail: `${number(d.loans_active)} active loans`, style: 'text-blue-800 bg-blue-50 border-blue-100' },
        { label: 'Pending transactions', value: number(d.pending_transactions), detail: 'Awaiting review', style: 'text-amber-800 bg-amber-50 border-amber-100' },
        { label: 'Pending loan applications', value: number(d.pending_loans), detail: `${number(d.loans_overdue)} overdue loans`, style: 'text-rose-800 bg-rose-50 border-rose-100' },
    ];

    return <div className="space-y-6">
        <header className="rounded-2xl bg-gradient-to-br from-slate-900 via-cyan-900 to-emerald-800 p-7 text-white shadow-sm">
            <p className="text-sm font-medium text-cyan-100">Cooperative oversight · {d.period_label || ''}</p>
            <h1 className="mt-1 text-3xl font-bold">{d.greeting || 'Good day'}, {user?.first_name || 'Chairman'}</h1>
            <p className="mt-2 max-w-2xl text-sm text-cyan-50">A cooperative-wide view of membership, finances, lending, and work awaiting attention.</p>
        </header>

        {isLoading ? <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{cards.map(card => <div key={card.label} className="h-28 animate-pulse rounded-xl bg-slate-100" />)}</div>
            : isError ? <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">Cooperative status could not be loaded. Refresh the page to try again.</div>
                : <>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{cards.map(card => <article key={card.label} className={`rounded-xl border p-5 ${card.style}`}><p className="text-sm font-medium opacity-80">{card.label}</p><p className="mt-2 text-2xl font-bold">{card.value}</p><p className="mt-1 text-xs opacity-75">{card.detail}</p></article>)}</div>
                    <div className="grid gap-6 lg:grid-cols-2">
                        <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="text-lg font-semibold text-slate-900">Monthly financial activity</h2>
                            <p className="mt-1 text-sm text-slate-500">Net movement this month: <strong className={Number(d.month_net) >= 0 ? 'text-emerald-700' : 'text-rose-700'}>{peso(d.month_net)}</strong></p>
                            <dl className="mt-4 grid grid-cols-2 gap-3 text-sm"><div className="rounded-lg bg-slate-50 p-3"><dt className="text-slate-500">Deposits</dt><dd className="mt-1 font-semibold text-slate-900">{peso(d.month_deposits)}</dd></div><div className="rounded-lg bg-slate-50 p-3"><dt className="text-slate-500">Withdrawals</dt><dd className="mt-1 font-semibold text-slate-900">{peso(d.month_withdrawals)}</dd></div><div className="rounded-lg bg-slate-50 p-3"><dt className="text-slate-500">Expenses</dt><dd className="mt-1 font-semibold text-slate-900">{peso(d.month_expenses)}</dd></div><div className="rounded-lg bg-slate-50 p-3"><dt className="text-slate-500">Loans due soon</dt><dd className="mt-1 font-semibold text-slate-900">{number(d.loans_due_soon)}</dd></div></dl>
                        </section>
                        <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="text-lg font-semibold text-slate-900">Loan portfolio</h2>
                            <div className="mt-4 space-y-3">{(d.loan_status_series || []).length ? d.loan_status_series.map((item: any) => <div key={item.status}><div className="mb-1 flex justify-between text-sm"><span className="text-slate-600">{item.label}</span><span className="font-medium text-slate-900">{number(item.count)} · {item.percent}%</span></div><div className="h-2 overflow-hidden rounded-full bg-slate-100"><div className={`h-full ${item.color}`} style={{ width: `${item.percent}%` }} /></div></div>) : <p className="text-sm text-slate-500">No loan records yet.</p>}</div>
                        </section>
                    </div>
                    {!!d.recent_announcements?.length && <section className="rounded-xl border border-slate-200 bg-white shadow-sm"><h2 className="border-b border-slate-100 px-5 py-4 text-lg font-semibold text-slate-900">Recent announcements</h2><ul className="divide-y divide-slate-100">{d.recent_announcements.map((item: any) => <li key={item.id} className="px-5 py-4"><div className="flex justify-between gap-4"><h3 className="font-medium text-slate-900">{item.title}</h3><span className="shrink-0 text-xs text-slate-500">{item.created_at_diff}</span></div>{item.content && <p className="mt-1 line-clamp-2 text-sm text-slate-600">{item.content}</p>}</li>)}</ul></section>}
                </>}
    </div>;
}
