import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';

export default function MemberLoansPage() {
    const { data, isLoading } = useQuery({ queryKey: ['member-loans'], queryFn: () => api.get('/api/member-loans') });
    const loans = data?.data?.data || [];
    const [selected, setSelected] = useState<any>(null);
    const [tab, setTab] = useState<'details' | 'schedule' | 'history'>('details');

    if (isLoading) return <div className="space-y-3">{[...Array(5)].map((_, i) => <div key={i} className="h-20 animate-pulse rounded-xl bg-slate-100" />)}</div>;

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-slate-900">My Loans</h1>
                <p className="text-sm text-slate-500">View and track your cooperative loans</p>
            </div>

            {loans.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-16 text-center">
                    <svg className="w-20 h-20 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p className="text-gray-500">No loans found</p>
                </div>
            ) : (
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-100">
                                <tr className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th className="px-6 py-3">Loan Number</th>
                                    <th className="px-6 py-3">Type</th>
                                    <th className="px-6 py-3 text-right">Amount</th>
                                    <th className="px-6 py-3 text-center">Interest Rate</th>
                                    <th className="px-6 py-3 text-center">Status</th>
                                    <th className="px-6 py-3 text-center">Due Date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {loans.map((l: any) => (
                                    <tr key={l.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => { setSelected(l); setTab('details'); }}>
                                        <td className="px-6 py-3 font-mono text-xs font-medium">{l.loan_number}</td>
                                        <td className="px-6 py-3">{l.loan_type_label || l.loan_type}</td>
                                        <td className="px-6 py-3 text-right font-medium">₱{Number(l.loan_amount).toLocaleString()}</td>
                                        <td className="px-6 py-3 text-center">{Number(l.interest_rate).toFixed(1)}%</td>
                                        <td className="px-6 py-3 text-center">
                                            <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${
                                                l.loan_status === 'active' || l.loan_status === 'approved' ? 'bg-emerald-100 text-emerald-700' :
                                                l.loan_status === 'paid' || l.loan_status === 'paid_off' ? 'bg-blue-100 text-blue-700' :
                                                l.loan_status === 'pending' ? 'bg-amber-100 text-amber-700' :
                                                'bg-gray-100 text-gray-600'
                                            }`}>{l.loan_status}</span>
                                        </td>
                                        <td className="px-6 py-3 text-center text-sm">{l.due_date || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {selected && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={() => setSelected(null)}>
                    <div className="w-full max-w-5xl h-[90vh] overflow-hidden rounded-2xl bg-white shadow-2xl flex flex-col" onClick={e => e.stopPropagation()}>
                        <div className="bg-linear-to-r from-emerald-600 to-cyan-600 px-6 py-4 text-white flex items-center justify-between shrink-0">
                            <div>
                                <h2 className="text-lg font-bold">Loan Details — {selected.loan_number}</h2>
                                <p className="text-sm text-white/80">{selected.loan_type_label || selected.loan_type}</p>
                            </div>
                            <button onClick={() => setSelected(null)} className="text-white/80 hover:text-white cursor-pointer"><i className="fas fa-times text-xl"></i></button>
                        </div>

                        <div className="border-b px-6 flex gap-0">
                            {(['details', 'schedule', 'history'] as const).map(t => (
                                <button key={t} onClick={() => setTab(t)} className={`px-4 py-3 text-sm font-semibold border-b-2 transition-colors cursor-pointer ${tab === t ? 'border-emerald-600 text-emerald-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                                    {t === 'details' ? 'Loan Details' : t === 'schedule' ? 'Payment Schedule' : 'Payment History'}
                                </button>
                            ))}
                        </div>

                        <div className="p-6 overflow-y-auto flex-1">
                            {tab === 'details' && (
                                <div className="space-y-6">
                                    <div className="grid grid-cols-3 gap-4">
                                        <div className="bg-linear-to-br from-emerald-700 to-teal-800 rounded-xl p-5 text-white shadow-xs"><p className="text-sm text-white/80">Loan Amount</p><p className="text-2xl font-bold">₱{Number(selected.loan_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p></div>
                                        <div className="bg-linear-to-br from-emerald-600 to-teal-600 rounded-xl p-5 text-white shadow-xs"><p className="text-sm text-white/80">Total Paid</p><p className="text-2xl font-bold">₱{Number(selected.total_paid).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p></div>
                                        <div className="bg-linear-to-br from-amber-600 to-orange-600 rounded-xl p-5 text-white shadow-xs"><p className="text-sm text-white/80">Balance Remaining</p><p className="text-2xl font-bold">₱{Number(selected.remaining_balance).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p></div>
                                    </div>

                                    <div className="grid md:grid-cols-2 gap-6">
                                        <div className="bg-gray-50 rounded-xl p-5">
                                            <h3 className="font-semibold text-gray-900 mb-3">Loan Information</h3>
                                            <dl className="space-y-2 text-sm">
                                                <div className="flex justify-between"><dt className="text-gray-500">Type</dt><dd className="font-medium">{selected.loan_type_label || selected.loan_type}</dd></div>
                                                <div className="flex justify-between"><dt className="text-gray-500">Principal</dt><dd className="font-medium">₱{Number(selected.principal_amount || selected.loan_amount).toLocaleString()}</dd></div>
                                                <div className="flex justify-between"><dt className="text-gray-500">Interest Rate</dt><dd className="font-medium">{Number(selected.interest_rate).toFixed(1)}%</dd></div>
                                                <div className="flex justify-between"><dt className="text-gray-500">Term</dt><dd className="font-medium">{selected.term_months} months</dd></div>
                                                <div className="flex justify-between"><dt className="text-gray-500">Payment Frequency</dt><dd className="font-medium capitalize">{selected.payment_frequency}</dd></div>
                                                {selected.estimated_interest > 0 && <><div className="flex justify-between"><dt className="text-gray-500">Est. Interest</dt><dd className="font-medium">₱{Number(selected.estimated_interest).toLocaleString()}</dd></div><div className="flex justify-between"><dt className="text-gray-500">Est. Total Repayment</dt><dd className="font-medium">₱{Number(selected.estimated_total_repayment).toLocaleString()}</dd></div></>}
                                                <div className="flex justify-between"><dt className="text-gray-500">Status</dt><dd><span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">{selected.loan_status}</span></dd></div>
                                            </dl>
                                        </div>
                                        <div className="bg-gray-50 rounded-xl p-5">
                                            <h3 className="font-semibold text-gray-900 mb-3">Important Dates</h3>
                                            <dl className="space-y-2 text-sm">
                                                <div className="flex justify-between"><dt className="text-gray-500">Applied</dt><dd className="font-medium">{selected.applied_at}</dd></div>
                                                <div className="flex justify-between"><dt className="text-gray-500">Approved</dt><dd className="font-medium">{selected.approved_at || '—'}</dd></div>
                                                <div className="flex justify-between"><dt className="text-gray-500">Due Date</dt><dd className="font-medium">{selected.due_date || '—'}</dd></div>
                                                {selected.approved_by && <div className="flex justify-between"><dt className="text-gray-500">Approved By</dt><dd className="font-medium">{selected.approved_by}</dd></div>}
                                            </dl>
                                        </div>
                                    </div>

                                    {selected.group_members && selected.group_members.length > 0 && (
                                        <div className="bg-gray-50 rounded-xl p-5">
                                            <h3 className="font-semibold text-gray-900 mb-3">Group Loan Members</h3>
                                            <div className="flex flex-wrap gap-2">
                                                {selected.group_members.map((gm: any, i: number) => (
                                                    <span key={i} className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${gm.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>
                                                        {gm.name} {gm.status === 'approved' ? '' : '(pending)'}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            {tab === 'schedule' && (
                                <div className="overflow-x-auto">
                                    {selected.payment_schedule ? (
                                        <table className="min-w-full text-sm"><thead className="bg-gray-50"><tr className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider"><th className="px-4 py-2">#</th><th className="px-4 py-2">Due Date</th><th className="px-4 py-2 text-right">Payment</th><th className="px-4 py-2 text-right">Principal</th><th className="px-4 py-2 text-right">Interest</th><th className="px-4 py-2 text-right">Balance</th></tr></thead>
                                            <tbody className="divide-y divide-gray-100">
                                                {selected.payment_schedule.map((s: any) => (
                                                    <tr key={s.period} className="hover:bg-gray-50"><td className="px-4 py-2">{s.period}</td><td className="px-4 py-2">{s.due_date || '—'}</td><td className="px-4 py-2 text-right font-medium">₱{Number(s.payment).toLocaleString()}</td><td className="px-4 py-2 text-right">₱{Number(s.principal).toLocaleString()}</td><td className="px-4 py-2 text-right text-gray-500">₱{Number(s.interest).toLocaleString()}</td><td className="px-4 py-2 text-right">{Number(s.balance) > 0 ? `₱${Number(s.balance).toLocaleString()}` : '—'}</td></tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    ) : (
                                        <p className="text-center py-8 text-gray-500">Schedule not available</p>
                                    )}
                                </div>
                            )}

                            {tab === 'history' && (
                                <div className="overflow-x-auto">
                                    {selected.completed_payments && selected.completed_payments.length > 0 ? (
                                        <table className="min-w-full text-sm"><thead className="bg-gray-50"><tr className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider"><th className="px-4 py-2">#</th><th className="px-4 py-2">Transaction #</th><th className="px-4 py-2 text-right">Amount</th><th className="px-4 py-2">Date</th><th className="px-4 py-2">Status</th></tr></thead>
                                            <tbody className="divide-y divide-gray-100">
                                                {selected.completed_payments.map((p: any) => (
                                                    <tr key={p.id} className="hover:bg-gray-50"><td className="px-4 py-2">{p.payment_number}</td><td className="px-4 py-2 font-mono text-xs">{p.transaction_number}</td><td className="px-4 py-2 text-right font-medium">₱{Number(p.amount).toLocaleString()}</td><td className="px-4 py-2 text-gray-500">{p.payment_date}</td><td className="px-4 py-2"><span className="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">{p.status}</span></td></tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    ) : (
                                        <div className="text-center py-12"><p className="text-gray-500">No payments recorded yet</p></div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
