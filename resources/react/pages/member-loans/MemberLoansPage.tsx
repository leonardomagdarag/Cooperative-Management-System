import Pagination from '../../components/ui/Pagination';
import usePaginatedRows from '../../hooks/usePaginatedRows';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import HelpTip from '../../components/ui/HelpTip';
import AmortizationTable from '../../components/loans/AmortizationTable';
import PaymentHistoryTable from '../../components/loans/PaymentHistoryTable';
import { formatPeso } from '../../lib/format';
import LoadingIndicator from '../../components/ui/LoadingIndicator';
import { useLanguage } from '../../contexts/LanguageContext';
import PageHeader from '../../components/ui/PageHeader';

const LOAN_STATUS_STYLES: Record<string, { label: string; className: string }> = {
    active: { label: 'Active', className: 'bg-emerald-100 text-emerald-700' },
    approved: { label: 'Approved', className: 'bg-emerald-100 text-emerald-700' },
    pending: { label: 'Pending', className: 'bg-amber-100 text-amber-700' },
    paid: { label: 'Paid', className: 'bg-blue-100 text-blue-700' },
    paid_off: { label: 'Paid Off', className: 'bg-blue-100 text-blue-700' },
    rejected: { label: 'Rejected', className: 'bg-red-100 text-red-700' },
};

function loanStatusMeta(status: string) {
    return LOAN_STATUS_STYLES[status] ?? { label: status, className: 'bg-gray-100 text-gray-600' };
}

export default function MemberLoansPage() {
    const { t } = useLanguage();
    const { data, isLoading } = useQuery({ queryKey: ['member-loans'], queryFn: () => api.get('/api/member-loans') });
    const loans = data?.data?.data || [];
    const pagination = usePaginatedRows<any>(loans);
    const [selected, setSelected] = useState<any>(null);
    const [tab, setTab] = useState<'details' | 'schedule' | 'history' | 'documents'>('details');

    if (isLoading) return <LoadingIndicator message={t('Loading your loans...')} />;

    return (
        <div className="space-y-6">
            <PageHeader title={t('My Loans')} description={t('View and track your cooperative loans')} icon="fas fa-file-invoice-dollar" />

            {loans.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-16 text-center">
                    <svg className="w-20 h-20 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p className="text-gray-500">{t('No loans found')}</p>
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
                                {pagination.rows.map((l: any) => (
                                    <tr key={l.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => { setSelected(l); setTab('details'); }}>
                                        <td className="px-6 py-3 font-mono text-xs font-medium whitespace-nowrap truncate max-w-[160px]" title={l.loan_number}>{l.loan_number}</td>
                                        <td className="px-6 py-3 whitespace-nowrap truncate max-w-[220px]" title={l.loan_type_label || l.loan_type}>{l.loan_type_label || l.loan_type}</td>
                                        <td className="px-6 py-3 text-right font-medium">₱{Number(l.loan_amount).toLocaleString()}</td>
                                        <td className="px-6 py-3 text-center">{Number(l.interest_rate).toFixed(1)}%</td>
                                        <td className="px-6 py-3 text-center">
                                            <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${loanStatusMeta(l.loan_status).className}`}>{t(loanStatusMeta(l.loan_status).label)}</span>
                                        </td>
                                        <td className="px-6 py-3 text-center text-sm">{l.due_date || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="px-4"><Pagination page={pagination.page} lastPage={pagination.lastPage} total={pagination.total} perPage={pagination.perPage} onChange={pagination.setPage} /></div>
                </div>
            )}

            {selected && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4" onClick={() => setSelected(null)}>
                    <div className="w-full max-w-5xl h-[90vh] overflow-hidden rounded-2xl bg-white shadow-2xl flex flex-col" onClick={e => e.stopPropagation()}>
                        <div className="bg-linear-to-r from-emerald-600 to-cyan-600 px-6 py-4 text-white flex items-center justify-between shrink-0">
                            <div>
                                <h2 className="text-lg font-bold">{t('Loan Details')} — {selected.loan_number}</h2>
                                <p className="text-sm text-white/80">{selected.loan_type_label || selected.loan_type}</p>
                            </div>
                            <button onClick={() => setSelected(null)} aria-label="Close loan details" className="text-white/80 hover:text-white cursor-pointer"><i className="fas fa-times text-xl"></i></button>
                        </div>

                        <div className="border-b px-6 flex gap-0 overflow-x-auto shrink-0">
                            {(['details', 'schedule', 'history', 'documents'] as const).map(tabKey => (
                                <button key={tabKey} onClick={() => setTab(tabKey)} className={`px-4 py-3 text-sm font-semibold whitespace-nowrap border-b-2 transition-colors cursor-pointer ${tab === tabKey ? 'border-emerald-600 text-emerald-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                                    {t(({ details: 'Loan Details', schedule: 'Payment Schedule', history: 'Payment History', documents: 'Documents' } as const)[tabKey])}
                                </button>
                            ))}
                        </div>

                        <div className="p-4 sm:p-6 overflow-y-auto flex-1">
                            {tab === 'details' && (
                                <div className="space-y-6">
                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div className="bg-linear-to-br from-emerald-700 to-teal-800 rounded-xl p-5 text-white shadow-xs"><p className="text-sm text-white/80">{t('Loan Amount')}</p><p className="text-2xl font-bold">{formatPeso(selected.loan_amount)}</p></div>
                                        <div className="bg-linear-to-br from-emerald-600 to-teal-600 rounded-xl p-5 text-white shadow-xs"><p className="text-sm text-white/80">{t('Total Paid')}</p><p className="text-2xl font-bold">{formatPeso(selected.total_paid)}</p></div>
                                        <div className="bg-linear-to-br from-amber-600 to-orange-600 rounded-xl p-5 text-white shadow-xs"><p className="text-sm text-white/80">{t('Balance Remaining')}</p><p className="text-2xl font-bold">{formatPeso(selected.remaining_balance)}</p></div>
                                    </div>

                                    <div className="bg-gray-50 rounded-xl p-5">
                                        <h3 className="flex items-center gap-2 font-semibold text-gray-900 mb-4">{t('How your loan breaks down')} <HelpTip text="Principal is the amount you borrowed. Interest is the cost of borrowing. Added together they make up the total you repay." /></h3>
                                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                            <div className="rounded-lg border border-gray-100 bg-white p-4">
                                                <p className="flex items-center gap-1 text-xs font-medium text-gray-500">Principal <HelpTip text="The amount you borrowed, before any interest is added." /></p>
                                                <p className="mt-1 text-lg font-semibold text-gray-900">{formatPeso(selected.principal_amount || selected.loan_amount)}</p>
                                            </div>
                                            <div className="rounded-lg border border-gray-100 bg-white p-4">
                                                <p className="flex items-center gap-1 text-xs font-medium text-gray-500">+ Interest <HelpTip text="The total cost of borrowing over your whole term." /></p>
                                                <p className="mt-1 text-lg font-semibold text-gray-900">{formatPeso(selected.estimated_interest)}</p>
                                            </div>
                                            <div className="rounded-lg border border-gray-100 bg-white p-4">
                                                <p className="flex items-center gap-1 text-xs font-medium text-gray-500">= Total Repayment <HelpTip text="Principal plus interest: the total amount you will pay over the life of the loan." /></p>
                                                <p className="mt-1 text-lg font-semibold text-emerald-700">{formatPeso(selected.estimated_total_repayment)}</p>
                                            </div>
                                        </div>
                                        <div className="mt-5">
                                            <div className="mb-1.5 flex items-center justify-between text-xs text-gray-500">
                                                <span>{formatPeso(selected.total_paid)} paid</span>
                                                <span>{formatPeso(selected.remaining_balance)} remaining</span>
                                            </div>
                                            <div className="h-2.5 w-full overflow-hidden rounded-full bg-gray-200">
                                                <div className="h-full rounded-full bg-emerald-500" style={{ width: `${Math.min(100, Math.max(0, Number(selected.progress) || 0))}%` }} />
                                            </div>
                                            <p className="mt-1 text-right text-xs font-medium text-emerald-700">{Number(selected.progress || 0).toFixed(1)}% paid</p>
                                        </div>
                                    </div>

                                    <div className="grid md:grid-cols-2 gap-6">
                                        <div className="bg-gray-50 rounded-xl p-5">
                                            <h3 className="font-semibold text-gray-900 mb-3">{t('Loan Information')}</h3>
                                            <dl className="space-y-2 text-sm">
                                                <div className="flex justify-between"><dt className="text-gray-500">Type</dt><dd className="font-medium">{selected.loan_type_label || selected.loan_type}</dd></div>
                                                <div className="flex justify-between"><dt className="flex items-center gap-1 text-gray-500">Principal <HelpTip text="The amount you borrowed, before interest." /></dt><dd className="font-medium">{formatPeso(selected.principal_amount || selected.loan_amount)}</dd></div>
                                                <div className="flex justify-between"><dt className="flex items-center gap-1 text-gray-500">Interest Rate <HelpTip text="The percentage charged on the loan, applied over the term." /></dt><dd className="font-medium">{Number(selected.interest_rate).toFixed(1)}%</dd></div>
                                                <div className="flex justify-between"><dt className="flex items-center gap-1 text-gray-500">Term <HelpTip text="How long you have to repay the loan, in months." /></dt><dd className="font-medium">{selected.term_months} months</dd></div>
                                                <div className="flex justify-between"><dt className="flex items-center gap-1 text-gray-500">Payment Frequency <HelpTip text="How often an installment is due (for example, monthly)." /></dt><dd className="font-medium capitalize">{selected.payment_frequency}</dd></div>
                                                <div className="flex justify-between"><dt className="text-gray-500">Overdue Penalty</dt><dd className="font-medium">{Number(selected.penalty_amount) > 0 ? `${selected.penalty_type === 'percent' ? `${selected.penalty_amount}%` : formatPeso(selected.penalty_amount)} · ${selected.penalty_frequency === 'per_due' ? 'per overdue installment' : selected.penalty_frequency}` : 'None'}</dd></div>
                                                <div className="flex justify-between"><dt className="text-gray-500">{t('Accrued Penalties')}</dt><dd className="font-medium text-amber-700">{formatPeso(selected.accrued_penalty || 0)}</dd></div>
                                                <div className="flex justify-between"><dt className="text-gray-500">{t('Amount Due Now')}</dt><dd className="font-medium">{formatPeso(selected.amount_due || 0)}</dd></div>
                                                {selected.estimated_interest > 0 && <><div className="flex justify-between"><dt className="flex items-center gap-1 text-gray-500">Est. Interest <HelpTip text="Estimated total interest over the whole term." /></dt><dd className="font-medium">{formatPeso(selected.estimated_interest)}</dd></div><div className="flex justify-between"><dt className="flex items-center gap-1 text-gray-500">Est. Total Repayment <HelpTip text="Principal plus estimated interest." /></dt><dd className="font-medium">{formatPeso(selected.estimated_total_repayment)}</dd></div></>}
                                                <div className="flex justify-between"><dt className="text-gray-500">Status</dt><dd><span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${loanStatusMeta(selected.loan_status).className}`}>{loanStatusMeta(selected.loan_status).label}</span></dd></div>
                                            </dl>
                                        </div>
                                        <div className="bg-gray-50 rounded-xl p-5">
                                            <h3 className="font-semibold text-gray-900 mb-3">{t('Important Dates')}</h3>
                                            <dl className="space-y-2 text-sm">
                                                <div className="flex justify-between"><dt className="text-gray-500">Applied</dt><dd className="font-medium">{selected.applied_at}</dd></div>
                                                <div className="flex justify-between"><dt className="text-gray-500">Approved</dt><dd className="font-medium">{selected.approved_at || '—'}</dd></div>
                                                <div className="flex justify-between"><dt className="flex items-center gap-1 text-gray-500">Due Date <HelpTip text="The final date by which the whole loan should be repaid." /></dt><dd className="font-medium">{selected.due_date || '—'}</dd></div>
                                                {selected.approved_by && <div className="flex justify-between"><dt className="text-gray-500">Approved By</dt><dd className="font-medium">{selected.approved_by}</dd></div>}
                                            </dl>
                                        </div>
                                    </div>

                                    {selected.group_members && selected.group_members.length > 0 && (
                                        <div className="bg-gray-50 rounded-xl p-5">
                                            <h3 className="font-semibold text-gray-900 mb-3">{t('Group Loan Members')}</h3>
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
                                <div className="space-y-4">
                                    <p className="flex flex-wrap items-center gap-1 text-sm text-gray-500">
                                        {t('Each row is one installment.')} <span className="inline-flex items-center gap-1">Payment <HelpTip text="The amount due for this period. It is made up of principal plus interest." /></span>
                                        = <span className="inline-flex items-center gap-1">Principal <HelpTip text="The part of this payment that reduces the amount you borrowed." /></span>
                                        + <span className="inline-flex items-center gap-1">Interest <HelpTip text="The part of this payment that covers the cost of borrowing." /></span>.
                                    </p>
                                    <AmortizationTable schedule={selected.payment_schedule} totalPaid={Number(selected.schedule_paid ?? selected.total_paid) || 0} />
                                </div>
                            )}

                            {tab === 'history' && (
                                <div className="space-y-3">
                                    <p className="flex items-center gap-1 text-sm text-gray-500">{t('Payments you have made.')} <HelpTip text="Only completed payments reduce your balance. Pending payments still need approval." /></p>
                                    <PaymentHistoryTable payments={selected.payments} />
                                </div>
                            )}
                            {tab === 'documents' && (
                                <div className="space-y-3">
                                    <h3 className="font-semibold text-gray-900">{t('Loan Documents')}</h3>
                                    {selected.documents?.length ? selected.documents.map((document: any) => (
                                        <div key={document.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 p-3">
                                            <div className="flex min-w-0 items-center gap-3">
                                                <i className="fas fa-file-alt text-gray-400" aria-hidden="true" />
                                                <div className="min-w-0">
                                                    <p className="break-all text-sm font-medium text-gray-800">{document.original_name}</p>
                                                    {document.file_size != null && <p className="text-xs text-gray-500">{(document.file_size / 1024).toFixed(1)} KB</p>}
                                                </div>
                                            </div>
                                            <div className="flex gap-3 text-sm font-medium">
                                                <a href={document.url} target="_blank" rel="noopener noreferrer" className="text-emerald-700 hover:underline" aria-label={`View ${document.original_name}`}>View</a>
                                                <a href={`${document.url}?disposition=attachment`} className="text-emerald-700 hover:underline" aria-label={`Download ${document.original_name}`}>Download</a>
                                            </div>
                                        </div>
                                    )) : (
                                        <p className="rounded-lg bg-gray-50 p-8 text-center text-sm text-gray-500">{t('No documents have been added to this loan yet.')}</p>
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
