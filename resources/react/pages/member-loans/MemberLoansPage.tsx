import Pagination from '../../components/ui/Pagination';
import usePaginatedRows from '../../hooks/usePaginatedRows';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../api/client';
import LoanDetailsModal, { loanStatusMeta, type LoanDetails } from '../../components/loans/LoanDetailsModal';
import { formatPeso } from '../../lib/format';
import LoadingIndicator from '../../components/ui/LoadingIndicator';
import { useLanguage } from '../../contexts/LanguageContext';
import PageHeader from '../../components/ui/PageHeader';

export default function MemberLoansPage() {
    const { t } = useLanguage();
    const { data, isLoading } = useQuery({ queryKey: ['member-loans'], queryFn: () => api.get<{ data: LoanDetails[] }>('/api/member-loans') });
    const loans = data?.data?.data || [];
    const pagination = usePaginatedRows(loans);
    const [selectedLoanId, setSelectedLoanId] = useState<number | null>(null);
    const selectedLoan = loans.find(loan => loan.id === selectedLoanId);

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
                                {pagination.rows.map(l => (
                                    <tr key={l.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => setSelectedLoanId(l.id)}>
                                        <td className="px-6 py-3 font-mono text-xs font-medium whitespace-nowrap truncate max-w-[160px]" title={l.loan_number}>
                                            <button type="button" className="cursor-pointer hover:underline" onClick={event => {
                                                event.stopPropagation();
                                                setSelectedLoanId(l.id);
                                            }}>{l.loan_number}</button>
                                        </td>
                                        <td className="px-6 py-3 whitespace-nowrap truncate max-w-[220px]" title={l.loan_type_label || l.loan_type}>{l.loan_type_label || l.loan_type}</td>
                                        <td className="px-6 py-3 text-right font-medium">{formatPeso(l.loan_amount)}</td>
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

            {selectedLoan && (
                <LoanDetailsModal key={selectedLoan.id} loan={selectedLoan} onClose={() => setSelectedLoanId(null)} />
            )}
        </div>
    );
}
